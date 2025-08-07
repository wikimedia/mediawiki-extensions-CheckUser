<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;
use Wikimedia\Rdbms\FakeResultWrapper;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\CheckUserUserInfoCardService
 */
class CheckUserUserInfoCardServiceTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	private User $testUser;

	public function setUp(): void {
		parent::setUp();
		// The GlobalContributionsPager used in CheckuserUserInfoCardService requires CentralAuth
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
	}

	public function addDBDataOnce() {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_wiki_map' )
			->rows( [ [ 'ciwm_wiki' => 'enwiki' ], [ 'ciwm_wiki' => 'dewiki' ] ] )
			->caller( __METHOD__ )
			->execute();
		$this->testUser = $this->getTestSysop()->getUser();

		$enwikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'enwiki' ] )
			->caller( __METHOD__ )
			->fetchField();
		$dewikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'dewiki' ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->rows( [
				[
					'ciu_central_id' => $this->testUser->getId(), 'ciu_ciwm_id' => $enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240505060708' ),
				],
				[
					'ciu_central_id' => $this->testUser->getId(), 'ciu_ciwm_id' => $dewikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240506060708' ),
				],
			] )
			->caller( __METHOD__ )
			->execute();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'interwiki' )
			->rows( [
				[
					'iw_prefix' => 'en',
					'iw_url' => 'https://en.wikipedia.org/wiki/$1',
					'iw_api' => '',
					'iw_wikiid' => '',
					'iw_local' => 1,
				],
				[
					'iw_prefix' => 'de',
					'iw_url' => 'https://de.wikipedia.org/wiki/$1',
					'iw_api' => '',
					'iw_wikiid' => '',
					'iw_local' => 1,
				]
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function getObjectUnderTest( ?LoggerInterface $logger = null ): CheckUserUserInfoCardService {
		$services = MediaWikiServices::getInstance();
		return new CheckUserUserInfoCardService(
			$services->getService( 'GrowthExperimentsUserImpactLookup' ),
			$services->getExtensionRegistry(),
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager(),
			$services->get( 'CheckUserGlobalContributionsPagerFactory' ),
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getInterwikiLookup(),
			$services->getUserEditTracker(),
			RequestContext::getMain(),
			$services->getTitleFactory(),
			$services->getGenderCache(),
			new ServiceOptions(
				CheckUserUserInfoCardService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$logger ?? LoggerFactory::getInstance( 'CheckUser' )
		);
	}

	public function testExecute() {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$page = $this->getNonexistingTestPage();
		$user = $this->testUser->getUser();
		$this->assertStatusGood(
			$this->editPage( $page, 'test', '', NS_MAIN, $user )
		);
		// Run deferred updates, to ensure that globalEditCount gets populated in CentralAuth.
		$this->runDeferredUpdates();

		$this->setService( 'CheckUserGlobalContributionsPagerFactory', function () use ( $user ) {
			$globalContributionsPager = $this->createMock( GlobalContributionsPager::class );
			$globalContributionsPager->method( 'getResult' )->willReturn(
				new FakeResultWrapper( [ [ 'sourcewiki' => 'enwiki' ], [ 'sourcewiki' => 'dewiki' ] ] )
			);
			$globalContributionsPagerFactory = $this->createMock( GlobalContributionsPagerFactory::class );
			$globalContributionsPagerFactory->method( 'createPager' )->willReturn( $globalContributionsPager );
			return $globalContributionsPagerFactory;
		} );

		$userInfo = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);
		$this->assertSame( 1, $userInfo[ 'totalEditCount' ] );
		// TODO: Fix this test so that we assert that the globalEditCount is 1.
		$this->assertArrayHasKey( 'globalEditCount', $userInfo );
		$this->assertSame( 0, $userInfo[ 'thanksGiven' ] );
		$this->assertSame( 0, $userInfo[ 'thanksReceived' ] );
		$this->assertSame( 1, current( $userInfo[ 'editCountByDay' ] ), 'Edit count for the current day is 1' );
		$this->assertSame( 0, $userInfo['revertedEditCount'] );
		$this->assertSame( $user->getName(), $userInfo['name'] );
		$this->assertSame( $this->getServiceContainer()->getGenderCache()->getGenderOf( $user ), $userInfo['gender'] );
		$this->assertArrayHasKey( 'localRegistration', $userInfo );
		$this->assertArrayHasKey( 'firstRegistration', $userInfo );
		$this->assertSame( '<strong>Groups</strong>: Bureaucrats, Administrators', $userInfo['groups'] );
		$this->assertSame(
			[
				'dewiki' => 'https://de.wikipedia.org/wiki/Special:Contributions/' . $user->getName(),
				'enwiki' => 'https://en.wikipedia.org/wiki/Special:Contributions/' . $user->getName(),
			],
			$userInfo['activeWikis']
		);

		$this->setService( 'CheckUserGlobalContributionsPagerFactory', static function () use ( $user ) {
			return null;
		} );
		$userInfoWithoutGlobalContributionsPager = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);
		$this->assertSame( [], $userInfoWithoutGlobalContributionsPager['activeWikis'] );
		unset( $userInfo['activeWikis'] );
		unset( $userInfoWithoutGlobalContributionsPager['activeWikis'] );
		$this->assertArrayEquals( $userInfo, $userInfoWithoutGlobalContributionsPager );
	}

	public function testPagingLimits() {
		$user = $this->getTestUser()->getUser();
		$this->setService( 'CheckUserGlobalContributionsPagerFactory', function () use ( $user ) {
			$globalContributionsPager = $this->createMock( GlobalContributionsPager::class );
			$globalContributionsPager->method( 'getResult' )->willReturn(
				new FakeResultWrapper( [ [ 'sourcewiki' => 'enwiki' ], [ 'sourcewiki' => 'dewiki' ] ] )
			);
			$globalContributionsPager->method( 'getPagingQueries' )->willReturn(
				// Set an arbitrary offset, this is to ensure that we reach the pager iteration limit
				[ 'next' => [ 'offset' => '12345 ' ] ]
			);
			$globalContributionsPagerFactory = $this->createMock( GlobalContributionsPagerFactory::class );
			$globalContributionsPagerFactory->method( 'createPager' )->willReturn( $globalContributionsPager );
			return $globalContributionsPagerFactory;
		} );
		$logger = new TestLogger();
		$this->getObjectUnderTest( $logger )->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);
		$this->assertTrue(
			$logger->hasInfoThatContains(
				'UserInfoCard returned incomplete activeWikis for {user} due to reaching pager iteration limits'
			)
		);
		// Verify that the Prometheus data is logged as intended.
		$timing = $this->getServiceContainer()
			->getStatsFactory()
			->withComponent( 'CheckUser' )
			->getTiming( 'userinfocardservice_active_wikis' );

		$labelKeys = $timing->getLabelKeys();
		$samples = $timing->getSamples();
		$this->assertSame( 'reached_paging_limit', $labelKeys[0] );
		$this->assertSame( '1', $samples[0]->getLabelValues()[0] );
		$this->assertIsNumeric( $samples[0]->getValue() );
	}

	public function testUserImpactIsEmpty() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$this->overrideMwServices(
			null,
			[ 'GrowthExperimentsUserImpactLookup' => function () {
				$mock = $this->createMock( ComputedUserImpactLookup::class );
				$mock->method( 'getUserImpact' )->willReturn( null );
				return $mock;
			} ]
		);
		$userInfo = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$this->getTestUser()->getUser()
		);
		$this->assertArrayNotHasKey( 'thanksGiven', $userInfo );
		$this->assertArrayHasKey( 'name', $userInfo );
	}

	public function testExecuteInvalidUser() {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		// User impacts can only be retrieved for registered users
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );
		$userImpact = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$anonUser
		);
		$this->assertSame( [], $userImpact );
	}

	public function testLoadingWithoutGrowthExperiments() {
		$services = $this->getServiceContainer();
		$infoCardService = new CheckUserUserInfoCardService(
			null,
			$services->getExtensionRegistry(),
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager(),
			$services->get( 'CheckUserGlobalContributionsPagerFactory' ),
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getInterwikiLookup(),
			$services->getUserEditTracker(),
			RequestContext::getMain(),
			$services->getTitleFactory(),
			$services->getGenderCache(),
			new ServiceOptions(
				CheckUserUserInfoCardService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			LoggerFactory::getInstance( 'CheckUser' )
		);
		$targetUser = $this->getTestUser()->getUser();
		$userInfo = $infoCardService->getUserInfo(
			$this->getTestUser()->getAuthority(), $targetUser
		);
		$this->assertArrayContains( [
			'name' => $targetUser->getName(),
			'groups' => '',
			'totalEditCount' => 0,
			'activeWikis' => [],
			'pastBlocksOnLocalWiki' => 0,
		], $userInfo );
		$this->assertArrayContains( [ 'activeLocalBlocksAllWikis' => 0 ], $userInfo );
		$this->assertArrayNotHasKey( 'thanksGiven', $userInfo );
	}

	public function testCheckUserChecksDataPoint() {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$cuUserAuthority = $this->getTestUser( [ 'checkuser' ] )->getAuthority();
		$user = $this->getTestUser()->getUser();
		$this->assertSame(
			0,
			$this->getObjectUnderTest()->getUserInfo(
				$cuUserAuthority, $user
			)['checkUserChecks']
		);
		$timestamp = (int)wfTimestamp( TS_UNIX, '20250611000000' );
		$olderTimestamp = (int)wfTimestamp( TS_UNIX, '20250411000000' );
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log' )
			->rows( [
				[
					'cul_target_text' => $user->getName(),
					'cul_target_id' => $user->getId(),
					'cul_timestamp' => $this->getDb()->timestamp( $timestamp ),
					'cul_type' => 'userips',
					'cul_reason_id' => 1,
					'cul_reason_plaintext_id' => 2,
					'cul_actor' => $user->getActorId(),
				],
				[
					'cul_target_text' => $user->getName(),
					'cul_target_id' => $user->getId(),
					'cul_timestamp' => $this->getDb()->timestamp( $olderTimestamp ),
					'cul_type' => 'userips',
					'cul_reason_id' => 1,
					'cul_reason_plaintext_id' => 2,
					'cul_actor' => $user->getActorId(),
				]
			] )
			->caller( __METHOD__ )
			->execute();

		$result = $this->getObjectUnderTest()->getUserInfo(
			$cuUserAuthority, $user
		);
		$this->assertSame(
			2,
			$result['checkUserChecks']
		);
		$this->assertSame(
			$timestamp,
			(int)wfTimestamp(
				TS_UNIX,
				$result['checkUserLastCheck']
			)
		);
		// User without checkuser-log permission should not see any checkUser related output.
		$result = $this->getObjectUnderTest()->getUserInfo(
			$user, $user
		);
		$this->assertArrayNotHasKey(
			'checkuserChecks',
			$result
		);
		$this->assertArrayNotHasKey(
			'checkUserLastCheck',
			$result
		);
	}

	public function testGetPastBlocksOnLocalWiki() {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$user = $this->getTestUser()->getUser();
		$this->assertSame(
			0,
			$this->getObjectUnderTest()->getUserInfo( $user, $user )['pastBlocksOnLocalWiki']
		);
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'logging' )
			->rows( [
				[
					'log_actor' => $user->getActorId(),
					'log_comment_id' => 1,
					'log_params' => '',
					'log_type' => 'block',
					'log_action' => 'block',
					'log_namespace' => NS_USER,
					'log_title' => str_replace( ' ', '_', $user->getName() ),
				],
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( 1,
			$this->getObjectUnderTest()->getUserInfo( $user, $user )['pastBlocksOnLocalWiki']
		);
	}

	public function testGetBlocksOnLocalWikiWithSuppression() {
		$user = $this->getTestUser()->getUser();
		$sysopUser = $this->getTestSysop()->getUser();
		$this->overrideUserPermissions( $sysopUser, [ 'suppressionlog' ] );
		$this->assertSame(
			0,
			$this->getObjectUnderTest()->getUserInfo(
				$user, $user
			)['pastBlocksOnLocalWiki']
		);
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'logging' )
			->row( [
				'log_actor' => $user->getActorId(),
				'log_comment_id' => 1,
				'log_params' => '',
				'log_type' => 'suppress',
				'log_action' => 'block',
				'log_namespace' => NS_USER,
				'log_title' => str_replace( ' ', '_', $user->getName() ),
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame(
			0,
			$this->getObjectUnderTest()->getUserInfo(
				$user, $user
			)['pastBlocksOnLocalWiki'],
			'User without suppressionlog right sees 0 for the count'
		);
		$this->assertSame(
			1,
			$this->getObjectUnderTest()->getUserInfo(
				$sysopUser, $user
			)['pastBlocksOnLocalWiki'],
			'User with suppression log right sees 1 for the count'
		);
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'logging' )
			->row( [
				'log_actor' => $user->getActorId(),
				'log_comment_id' => 1,
				'log_params' => '',
				'log_type' => 'block',
				'log_action' => 'block',
				'log_namespace' => NS_USER,
				'log_title' => str_replace( ' ', '_', $user->getName() ),
			] )
			->caller( __METHOD__ )
			->execute();
		$this->assertSame(
			2,
			$this->getObjectUnderTest()->getUserInfo(
				$sysopUser, $user
			)['pastBlocksOnLocalWiki'],
			'User with suppressionlog right can see both counts'
		);
		$this->assertSame(
			1,
			$this->getObjectUnderTest()->getUserInfo(
				$user, $user
			)['pastBlocksOnLocalWiki'],
			'User without suppressionlog sees only regular block entry counts'
		);
	}

	public function testCanAccessTemporaryAccountIPAddresses() {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$this->setGroupPermissions( 'sysop', 'checkuser-temporary-account', true );
		$authority = $this->getTestUser( [ 'checkuser' ] )->getAuthority();
		$userOptionsManager->setOption(
			$authority->getUser(),
			'checkuser-temporary-account-enable',
			'1'
		);
		$userOptionsManager->saveOptions( $authority->getUser() );

		$user = $this->getTestSysop()->getUser();
		$userOptionsManager->setOption(
			$user,
			'checkuser-temporary-account-enable',
		'1'
		);
		$userOptionsManager->saveOptions( $user );
		$result = $this->getObjectUnderTest()->getUserInfo(
			$authority, $user
		);
		$this->assertSame( true, $result['canAccessTemporaryAccountIpAddresses'] );

		$newUser = $this->getTestUser( [ 'noaccess' ] )->getUser();
		$result = $this->getObjectUnderTest()->getUserInfo(
			$newUser, $user
		);
		$this->assertSame( false, $result['canAccessTemporaryAccountIpAddresses'] );
	}

	/**
	 * @dataProvider provideUserPageIsKnown
	 */
	public function testUserPageIsKnown(
		bool $PageIsKnown,
		bool $knownViaHook,
		bool $expected
	) {
		// T399252
		$this->clearHook( 'TitleIsAlwaysKnown' );

		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$user = $this->getTestUser()->getUser();

		// Simulate the case where a page does not exist but has meaningful content due to an extension
		// (T396304).
		if ( $knownViaHook ) {
			$this->setTemporaryHook(
				'TitleIsAlwaysKnown',
				static fn ( Title $title, ?bool &$isKnown ) => $isKnown = $title->equals( $user->getUserPage() ),
			);
		}

		if ( $PageIsKnown ) {
			$this->getExistingTestPage( $user->getUserPage() );
		} else {
			$this->getNonexistingTestPage( $user->getUserPage() );
		}

		$userInfo = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);

		$this->assertArrayHasKey( 'userPageIsKnown', $userInfo );
		$this->assertSame( $expected, $userInfo['userPageIsKnown'] );
	}

	public static function provideUserPageIsKnown(): iterable {
		yield 'nonexistent page' => [ false, false, false ];
		yield 'existing page' => [ true, false, true ];
		yield 'page known via hook' => [ false, true, true ];
	}

	/** @dataProvider provideExecuteWhenSpecialCentralAuthUrlDefined */
	public function testExecuteWhenSpecialCentralAuthUrlDefined( $centralWikiId, $expectedUrlWithoutUsername ) {
		$this->overrideConfigValue( 'CheckUserUserInfoCardCentralWikiId', $centralWikiId );

		$targetUser = $this->getTestUser()->getUser();

		$userInfo = $this->getObjectUnderTest()->getUserInfo( $this->mockRegisteredNullAuthority(), $targetUser );

		$this->assertArrayHasKey( 'specialCentralAuthUrl', $userInfo );
		$this->assertSame(
			$expectedUrlWithoutUsername . '/' . str_replace( ' ', '_', $targetUser->getName() ),
			$userInfo['specialCentralAuthUrl']
		);
	}

	public static function provideExecuteWhenSpecialCentralAuthUrlDefined(): array {
		return [
			'Central wiki is defined' => [ 'dewiki', 'https://de.wikipedia.org/wiki/Special:CentralAuth' ],
		];
	}

	/** @dataProvider provideExecuteWhenSpecialCentralAuthUrlDefinedAsLocalWiki */
	public function testExecuteWhenSpecialCentralAuthUrlDefinedAsLocalWiki( $centralWikiId ) {
		$expectedUrlWithoutUsername = SpecialPage::getTitleFor( 'CentralAuth' )->getLocalURL();
		$this->testExecuteWhenSpecialCentralAuthUrlDefined( $centralWikiId, $expectedUrlWithoutUsername );
	}

	public static function provideExecuteWhenSpecialCentralAuthUrlDefinedAsLocalWiki(): array {
		return [
			'Central wiki is unrecognised' => [ 'dewikiabc' ],
			'Central wiki is false' => [ false ],
		];
	}
}
