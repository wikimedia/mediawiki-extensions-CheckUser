<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\CheckUserUserInfoCardService
 */
class CheckUserUserInfoCardServiceTest extends MediaWikiIntegrationTestCase {

	private User $testUser;

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

	private function getObjectUnderTest(): CheckUserUserInfoCardService {
		$services = MediaWikiServices::getInstance();
		return new CheckUserUserInfoCardService(
			$services->getService( 'GrowthExperimentsUserImpactLookup' ),
			$services->getExtensionRegistry(),
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager(),
			$services->get( 'CheckUserCentralIndexLookup' ),
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getInterwikiLookup(),
			$services->getUserEditTracker(),
			RequestContext::getMain()
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
		$userInfo = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);
		$this->assertSame( 1, $userInfo[ 'totalEditCount' ] );
		if ( MediaWikiServices::getInstance()->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			// TODO: Fix this test so that we assert that the globalEditCount is 1.
			$this->assertArrayHasKey( 'globalEditCount', $userInfo );
		}
		$this->assertSame( 0, $userInfo[ 'thanksGiven' ] );
		$this->assertSame( 0, $userInfo[ 'thanksReceived' ] );
		$this->assertSame( 1, current( $userInfo[ 'editCountByDay' ] ), 'Edit count for the current day is 1' );
		$this->assertSame( 0, $userInfo['revertedEditCount'] );
		$this->assertSame( $user->getName(), $userInfo['name'] );
		$this->assertArrayHasKey( 'localRegistration', $userInfo );
		$this->assertArrayHasKey( 'firstRegistration', $userInfo );
		$this->assertSame( 'Groups: Bureaucrats, Administrators', $userInfo['groups'] );
		$this->assertSame(
			[
				'dewiki' => 'https://de.wikipedia.org/wiki/Special:Contributions/' . $user->getName(),
				'enwiki' => 'https://en.wikipedia.org/wiki/Special:Contributions/' . $user->getName(),
			],
			$userInfo['activeWikis']
		);
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
			$services->get( 'CheckUserCentralIndexLookup' ),
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getInterwikiLookup(),
			$services->getUserEditTracker(),
			RequestContext::getMain()
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
		if ( $services->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$this->assertArrayContains( [ 'activeLocalBlocksAllWikis' => 0 ], $userInfo );
		}
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
					'log_namespace' => NS_USER,
					'log_title' => $user->getName(),
				],
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( 1,
			$this->getObjectUnderTest()->getUserInfo( $user, $user )['pastBlocksOnLocalWiki']
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
		$this->assertSame( true, $result['canAccessTemporaryAccountIPAddresses'] );

		$newUser = $this->getTestUser( [ 'noaccess' ] )->getUser();
		$result = $this->getObjectUnderTest()->getUserInfo(
			$newUser, $user
		);
		$this->assertArrayNotHasKey( 'canAccessTemporaryAccountIPAddresses', $result );
	}
}
