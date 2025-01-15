<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use GlobalPreferences\GlobalPreferencesFactory;
use LogicException;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Jobs\UpdateUserCentralIndexJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use SpecialPageTestBase;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\SpecialGlobalContributions
 * @covers \MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager
 * @covers \MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob
 * @group CheckUser
 * @group Database
 */
class SpecialGlobalContributionsTest extends SpecialPageTestBase {

	use ContributionsRangeTrait;
	use CheckUserTempUserTestTrait;

	private static User $disallowedUser;
	private static User $checkuser;
	private static User $sysop;
	private static User $checkuserAndSysop;
	private static User $suppressedUser;
	private static User $suppressUser;
	private static User $tempUser1;
	private static User $tempUser2;
	private static User $tempUser3;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'GlobalContributions' );
	}

	protected function setup(): void {
		parent::setup();

		// Avoid holding onto stale service references
		self::$disallowedUser->clearInstanceCache();
		self::$checkuser->clearInstanceCache();
		self::$sysop->clearInstanceCache();
		self::$checkuserAndSysop->clearInstanceCache();
		self::$suppressedUser->clearInstanceCache();
		self::$suppressUser->clearInstanceCache();
		self::$tempUser1->clearInstanceCache();
		self::$tempUser2->clearInstanceCache();
		self::$tempUser3->clearInstanceCache();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->enableAutoCreateTempUser();

		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	/**
	 * Add a temporary user and a fully registered user who contributed
	 * from the same IP address. This is important to ensure we don't
	 * leak that the fully registered user edited from that IP.
	 *
	 * Also add contributions from multiple temp users and from multiple
	 * IPs, to test ranges.
	 */
	public function addDBDataOnce() {
		$this->enableAutoCreateTempUser();

		// The users must be created now because the actor table will
		// be altered when the edits are made, and added to the list
		// of tables that can't be altered again in $dbDataOnceTables.
		self::$disallowedUser = static::getTestUser()->getUser();
		self::$suppressedUser = static::getTestUser()->getUser();
		self::$suppressUser = static::getTestUser( [ 'suppress', 'sysop' ] )->getUser();
		self::$checkuser = static::getTestUser( [ 'checkuser' ] )->getUser();
		self::$sysop = static::getTestUser( [
			'sysop',
			'checkuser-temporary-account-viewer'
		] )->getUser();
		self::$checkuserAndSysop = static::getTestUser( [ 'checkuser', 'sysop' ] )->getUser();
		self::$tempUser1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-01', new FauxRequest() )->getUser();
		self::$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-02', new FauxRequest() )->getUser();
		self::$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-03', new FauxRequest() )->getUser();

		// Named user and 2 temp users edit from the first IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		$this->editPage(
			'Test page', 'Test Content 1', 'test', NS_MAIN, self::$sysop
		);
		$this->editPage(
			'Test page', 'Test Content 2', 'test', NS_MAIN, self::$tempUser1
		);

		// Do one edit at a different time, to test the pagination
		ConvertibleTimestamp::setFakeTime( '20000101000000' );
		$this->editPage(
			'Test page', 'Test Content 3', 'test', NS_MAIN, self::$tempUser2
		);
		ConvertibleTimestamp::setFakeTime( false );

		$this->editPage(
			'Test page for deletion', 'Test Content', 'test', NS_MAIN, self::$tempUser1
		);
		$title = Title::newFromText( 'Test page for deletion' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->deletePage( $page );

		// Temp user edits again from a different IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 4', 'test', NS_MAIN, self::$tempUser1
		);
		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => UpdateUserCentralIndexJob::TYPE ] );
	}

	/**
	 * @dataProvider provideTargets
	 */
	public function testExecuteTarget( $target, $expectedCount ) {
		[ $html ] = $this->executeSpecialPage(
			$target,
			null,
			null,
			self::$checkuser
		);

		// Target field should be populated
		$this->assertStringContainsString( $target, $html );

		if ( $expectedCount > 0 ) {
			$this->assertStringContainsString( 'mw-contributions-list', $html );
			// Use occurrences of data attribute in to determine how many rows,
			// to test pager.
			$this->assertSame( $expectedCount, substr_count( $html, 'data-mw-revid' ) );

			$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => LogTemporaryAccountAccessJob::TYPE ] );
			if ( $this->isValidIPOrQueryableRange( $target, $this->getServiceContainer()->getMainConfig() ) ) {
				// Test that a log entry was inserted for the viewing of this target if it was an IP.
				$this->assertSame(
					 1,
					$this->getDb()->newSelectQueryBuilder()
						->from( 'logging' )
						->where( [
							'log_type' => TemporaryAccountLogger::LOG_TYPE,
							'log_action' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP_GLOBAL,
							'log_actor' => self::$checkuser->getActorId(),
							'log_namespace' => NS_USER,
							'log_title' => IPUtils::prettifyIP( IPUtils::sanitizeRange( $target ) ),
						] )
						->fetchRowCount()
				);
			} else {
				// Test that no log entry was inserted for viewing an account
				$this->assertSame(
					0,
					$this->getDb()->newSelectQueryBuilder()
						->from( 'logging' )
						->where( [
							'log_type' => TemporaryAccountLogger::LOG_TYPE,
							'log_action' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP_GLOBAL,
							'log_actor' => self::$checkuser->getActorId(),
							'log_namespace' => NS_USER,
							'log_title' => $target,
						] )
						->fetchRowCount()
				);
			}
		} else {
			$this->assertStringNotContainsString( 'mw-contributions-list', $html );
		}
	}

	public function provideTargets() {
		return [
			'Empty target' => [ '', 0 ],
			'Valid IP' => [ '127.0.0.1', 2 ],
			'Valid IP without contributions' => [ '127.0.0.5', 0 ],
			'Valid range' => [ '127.0.0.1/24', 3 ],
			'Temp user' => [ '~check-user-test-2024-01', 0 ],
			'Nonexistent user' => [ 'Nonexistent', 0 ],
		];
	}

	public function testExecuteTargetReverse() {
		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1/24',
			new FauxRequest( [ 'dir' => 'prev' ] ),
			null,
			self::$checkuser
		);

		// Target field should be populated
		$this->assertStringContainsString( '127.0.0.1/24', $html );

		$this->assertStringContainsString( 'mw-pager-body', $html );
		// Use occurrences of data attribute in to determine how many rows,
		// to test pager.
		$this->assertSame( 3, substr_count( $html, 'data-mw-revid' ) );

		// Assert the source wiki from the template is present
		$this->assertStringContainsString( 'external mw-changeslist-sourcewiki', $html );
	}

	public function testNamespaceRestriction() {
		// Add unique namespaces to the wiki
		$this->overrideConfigValue( MainConfigNames::ExtraNamespaces, [
			3000 => 'Foo',
			3001 => 'Foo_talk'
		] );

		// Assert that the namespace exists on-wiki
		$this->assertContains(
			"Foo_talk",
			$this->getServiceContainer()->getNamespaceInfo()->getCanonicalNamespaces()
		);

		// Assert that the namespace is not an available filter
		[ $html ] = $this->executeSpecialPage(
			'',
			null,
			null,
			self::$checkuser
		);
		$this->assertStringNotContainsString( 'Foo talk', $html );
	}

	public function testDefinedTagFilters() {
		// Assert that every software defined tag is an available filter
		[ $html ] = $this->executeSpecialPage(
			'',
			null,
			null,
			self::$checkuser
		);
		$softwareDefinedTags = MediaWikiServices::getInstance()
			->getChangeTagsStore()->getSoftwareTags( true );
		foreach ( $softwareDefinedTags as $tag ) {
			$this->assertStringContainsString( 'value=\'' . $tag . '\'', $html );
		}
	}

	public function testExecuteWideRange() {
		// Ensure the range restriction comes from $wgRangeContributionsCIDRLimit,
		// not $wgCheckUserCIDRLimit
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 1, 'IPv6' => 1 ] );
		$this->overrideConfigValue( 'RangeContributionsCIDRLimit', [ 'IPv4' => 17, 'IPv6' => 20 ] );

		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1/1',
			null,
			null,
			self::$checkuser
		);

		$this->assertStringNotContainsString( 'mw-pager-body', $html );
		$this->assertStringContainsString( 'sp-contributions-outofrange', $html );
	}

	public function testExecuteNonexistentUsername() {
		[ $html ] = $this->executeSpecialPage(
			'Nonexistent user',
			null,
			'qqx',
			self::$checkuser
		);

		$this->assertStringContainsString( 'mw-pager-body', $html );
		$this->assertStringContainsString(
			'checkuser-global-contributions-no-results-no-central-user',
			$html,
			'Show error when no central user found'
		);
	}

	public function testExecuteUsername() {
		// User to be suppressed edits from a unique IP to avoid conflicts with IP searches in other tests
		RequestContext::getMain()->getRequest()->setIP( '128.0.0.1' );
		$this->editPage(
			'Test page 2', 'Test content from user to be suppressed', 'test', NS_MAIN, self::$suppressedUser
		);
		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => UpdateUserCentralIndexJob::TYPE ] );

		// Assert that the user's contributions can be seen normally
		[ $html ] = $this->executeSpecialPage(
			self::$suppressedUser->getName(),
			null,
			'qqx',
			self::$checkuser
		);
		$this->assertStringContainsString( 'mw-contributions-list', $html );
		$this->assertSame( 1, substr_count( $html, 'data-mw-revid' ) );

		// Suppress user
		$status = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$suppressedUser->getName(),
				self::$suppressUser,
				'infinity',
				'test hideuser',
				[
					'isHideUser' => true
				]
			)->placeBlock();
		$this->assertStatusGood( $status );

		// Assert a user without the 'hideuser' right can't see the suppressed user
		// Since the test is using a local centralIdLookup, this is equivalent to being centrally suppressed
		[ $html ] = $this->executeSpecialPage(
			self::$suppressedUser->getName(),
			null,
			'qqx',
			static::getTestUser()->getUser()
		);
		$this->assertStringContainsString( 'checkuser-global-contributions-no-results-no-central-user', $html );

		// Assert a user with the 'hideuser' right can see the suppressed user
		[ $html ] = $this->executeSpecialPage(
			self::$suppressedUser->getName(),
			null,
			'qqx',
			self::$suppressUser
		);
		$this->assertStringNotContainsString( 'contributions-userdoesnotexist', $html );
		$this->assertSame( 1, substr_count( $html, 'data-mw-revid' ) );
	}

	public function testExecuteUsernameNoContributions() {
		// Assert that the no-contributions state is shown for a registered user with no contributions
		[ $html ] = $this->executeSpecialPage(
			self::$tempUser3->getName(),
			null,
			'qqx',
			self::$checkuser
		);
		$this->assertStringContainsString( 'checkuser-global-contributions-no-results-no-visible-contribs', $html );
	}

	public function testExecuteIPNoContributions() {
		// Assert that the no-contributions state is not reached by IP targets with no contributions
		// and that the expected permissions message is shown instead
		[ $html ] = $this->executeSpecialPage(
			'1.2.3.4',
			null,
			'qqx',
			self::$checkuser
		);
		$this->assertStringNotContainsString( 'checkuser-global-contributions-no-results-no-visible-contribs', $html );
		$this->assertStringContainsString( 'checkuser-global-contributions-no-results-no-permissions', $html );
	}

	public function testIPRangeLimitConflict() {
		// Assert that an error is thrown if the range limit configurations are in conflict
		$this->expectException( LogicException::class );
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 32, 'IPv6' => 32 ] );
		$this->overrideConfigValue( MainConfigNames::RangeContributionsCIDRLimit, [ 'IPv4' => 8, 'IPv6' => 8 ] );
		$this->executeSpecialPage(
			'127.0.0.1/8',
			null,
			null,
			self::$checkuser
		);
	}

	public function testExecutePreference() {
		$globalPreferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
		$globalPreferencesFactory->method( 'getGlobalPreferencesValues' )
			->willReturn( [ 'checkuser-temporary-account-enable' => true ] );
		$this->setService( 'PreferencesFactory', $globalPreferencesFactory );

		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1',
			null,
			null,
			self::$sysop
		);

		// Target field should be populated
		$this->assertStringContainsString( '127.0.0.1', $html );

		$this->assertStringContainsString( 'mw-pager-body', $html );

		// Use occurrences of data attribute in to determine how many rows,
		// to test pager.
		$this->assertSame( 2, substr_count( $html, 'data-mw-revid' ) );
	}

	/**
	 * @dataProvider provideGlobalPreferences
	 */
	public function testExecuteErrorPreference( $preferences ) {
		$globalPreferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
		$globalPreferencesFactory->method( 'getGlobalPreferencesValues' )
			->willReturn( $preferences );
		$this->setService( 'PreferencesFactory', $globalPreferencesFactory );

		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1',
			null,
			null,
			self::$sysop
		);

		$this->assertStringNotContainsString( 'mw-contributions-list', $html );
		$this->assertStringContainsString( 'checkuser-global-contributions-no-results-no-global-preference', $html );
	}

	public function provideGlobalPreferences() {
		return [
			'Global preferences not found' => [ false ],
			'Global preference not present' => [ [] ],
			'Global preference set to disable' => [ [ 'checkuser-temporary-account-enable' => false ] ],
		];
	}

	public function testExecuteErrorRevealIpPermission() {
		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1',
			null,
			null,
			self::$disallowedUser
		);

		$this->assertStringNotContainsString( 'mw-contributions-list', $html );
	}

	public function testExecuteErrorBlock() {
		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$checkuser->getName(),
				self::$sysop,
				'infinity'
			)
			->placeBlock();

		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1',
			null,
			null,
			self::$checkuser
		);

		$this->assertStringNotContainsString( 'mw-contributions-list', $html );
	}

	public function testExternalApiLookupError() {
		$globalPreferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
		$globalPreferencesFactory->method( 'getGlobalPreferencesValues' )
			->willReturn( [ 'checkuser-temporary-account-enable' => true ] );
		$this->setService( 'PreferencesFactory', $globalPreferencesFactory );

		// Insert an external edit into cuci_temp_edit and cuci_wiki_map to stub out
		// a failed API call to an external wiki
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_temp_edit' )
			->row( [
				// 127.0.0.1
				'cite_ip_hex' => '7F000001',
				'cite_ciwm_id' => 2,
				'cite_timestamp' => $this->getDb()->timestamp(),
			] )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_wiki_map' )
			->row( [
				'ciwm_id' => 2,
				'ciwm_wiki' => 'otherwiki',
			] )
			->caller( __METHOD__ )
			->execute();

		// Mock the external API failure
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( [] );
			$this->setService( 'CheckUserApiRequestAggregator', $apiRequestAggregator );

		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1',
			null,
			null,
			self::$sysop
		);
		$this->assertStringContainsString( 'checkuser-global-contributions-api-lookup-error', $html );
	}
}
