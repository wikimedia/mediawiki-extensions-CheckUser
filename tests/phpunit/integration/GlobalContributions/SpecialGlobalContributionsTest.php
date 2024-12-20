<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
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

	use CheckUserTempUserTestTrait;

	private static User $disallowedUser;
	private static User $checkuser;
	private static User $sysop;
	private static User $checkuserAndSysop;

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

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->enableAutoCreateTempUser();
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
		self::$checkuser = static::getTestUser( [ 'checkuser' ] )->getUser();
		self::$sysop = static::getTestUser( [
			'sysop',
			'checkuser-temporary-account-viewer'
		] )->getUser();
		self::$checkuserAndSysop = static::getTestUser( [ 'checkuser', 'sysop' ] )->getUser();

		$temp1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-01', new FauxRequest() )->getUser();
		$temp2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-02', new FauxRequest() )->getUser();

		// Named user and 2 temp users edit from the first IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		$this->editPage(
			'Test page', 'Test Content 1', 'test', NS_MAIN, self::$sysop
		);
		$this->editPage(
			'Test page', 'Test Content 2', 'test', NS_MAIN, $temp1
		);

		// Do one edit at a different time, to test the pagination
		ConvertibleTimestamp::setFakeTime( '20000101000000' );
		$this->editPage(
			'Test page', 'Test Content 3', 'test', NS_MAIN, $temp2
		);
		ConvertibleTimestamp::setFakeTime( false );

		$this->editPage(
			'Test page for deletion', 'Test Content', 'test', NS_MAIN, $temp1
		);
		$title = Title::newFromText( 'Test page for deletion' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->deletePage( $page );

		// Temp user edits again from a different IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 4', 'test', NS_MAIN, $temp1
		);
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

			// Test that a log entry was inserted for the viewing of this target.
			$this->runJobs();
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
			$this->assertStringNotContainsString( 'mw-contributions-list', $html );
		}
	}

	public function provideTargets() {
		return [
			'Empty target' => [ '', 0 ],
			'Valid IP' => [ '127.0.0.1', 2 ],
			'Valid IP without contributions' => [ '127.0.0.5', 0 ],
			'Valid range' => [ '127.0.0.1/24', 3 ],
			'Temp user' => [ '~check-user-test-2024-1', 0 ],
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

	public function testExecuteUsername() {
		[ $html ] = $this->executeSpecialPage(
			'Nonexistent user',
			null,
			'qqx',
			self::$checkuser
		);

		$this->assertStringNotContainsString( 'mw-pager-body', $html );
		$this->assertStringNotContainsString(
			'contributions-userdoesnotexist',
			$html,
			'No user does not exist error should show while usernames are not a supported input'
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
