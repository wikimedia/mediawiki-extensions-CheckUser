<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\ContributionsHandler;
use MediaWiki\Pager\ContribsPager;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\HookHandler\ContributionsHandler
 */
class ContributionsHandlerTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	private static UserIdentity $disallowedUser;
	private static UserIdentity $checkuser;
	private static UserIdentity $sysop;

	private function getHandlerForSuccess() {
		return new ContributionsHandler(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getDBLoadBalancerFactory(),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->get( 'CheckUserLookupUtils' )
		);
	}

	private function getContribsPagerForSuccess() {
		$pager = $this->createMock( ContribsPager::class );
		$pager->method( 'getTarget' )
			->willReturn( '1.2.3.4' );
		$pager->method( 'getUser' )
			->willReturn( self::$checkuser );

		return $pager;
	}

	public function testContribsPagerQueryInfoTempUserDisabled() {
		$this->disableAutoCreateTempUser();

		$pager = $this->getContribsPagerForSuccess();
		$handler = $this->getHandlerForSuccess();

		$queryInfo = [ 'conds' => [ 'actor_name' => '1.2.3.4' ] ];
		$handler->onContribsPager__getQueryInfo(
			$pager,
			$queryInfo
		);

		$this->assertSame(
			'1.2.3.4',
			$queryInfo['conds']['actor_name']
		);
	}

	public function testContribsPagerQueryInfoTargetNotIp() {
		$this->enableAutoCreateTempUser();

		$pager = $this->createMock( ContribsPager::class );
		$pager->method( 'getTarget' )
			->willReturn( 'SomeUser' );
		$pager->method( 'getUser' )
			->willReturn( self::$checkuser );

		$handler = $this->getHandlerForSuccess();

		$queryInfo = [ 'conds' => [ 'actor_name' => 'SomeUser' ] ];
		$handler->onContribsPager__getQueryInfo(
			$pager,
			$queryInfo
		);

		$this->assertSame(
			'SomeUser',
			$queryInfo['conds']['actor_name']
		);
	}

	public function testContribsPagerQueryInfoUserNotAllowed() {
		$this->enableAutoCreateTempUser();

		$pager = $this->createMock( ContribsPager::class );
		$pager->method( 'getTarget' )
			->willReturn( '1.2.3.4' );
		$pager->method( 'getUser' )
			->willReturn( self::$disallowedUser );

		$handler = $this->getHandlerForSuccess();

		$queryInfo = [ 'conds' => [ 'actor_name' => '1.2.3.4' ] ];
		$handler->onContribsPager__getQueryInfo(
			$pager,
			$queryInfo
		);

		$this->assertSame(
			'1.2.3.4',
			$queryInfo['conds']['actor_name']
		);
	}

	public function testContribsPagerQueryInfoUserBlocked() {
		$this->enableAutoCreateTempUser();

		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			self::$checkuser,
			self::$sysop,
			'infinity'
		)->placeBlock();
		$this->assertStatusGood( $blockStatus );

		$pager = $this->createMock( ContribsPager::class );
		$pager->method( 'getTarget' )
			->willReturn( '1.2.3.4' );
		$pager->method( 'getUser' )
			->willReturn( self::$checkuser );

		$handler = $this->getHandlerForSuccess();

		$queryInfo = [ 'conds' => [ 'actor_name' => '1.2.3.4' ] ];
		$handler->onContribsPager__getQueryInfo(
			$pager,
			$queryInfo
		);

		$this->assertSame(
			'1.2.3.4',
			$queryInfo['conds']['actor_name']
		);
	}

	public function testContribsPagerQueryInfoSuccessful() {
		$this->enableAutoCreateTempUser();

		$pager = $this->getContribsPagerForSuccess();
		$handler = $this->getHandlerForSuccess();

		$queryInfo = [ 'conds' => [ 'actor_name' => '1.2.3.4' ] ];
		$handler->onContribsPager__getQueryInfo(
			$pager,
			$queryInfo
		);

		$this->assertIsArray( $queryInfo['conds']['actor_name'] );
		$this->assertArrayEquals(
			[ '1.2.3.4', '~2024-1' ],
			$queryInfo['conds']['actor_name']
		);
	}

	/**
	 * Add a temporary user and a fully registered user who contributed
	 * from the same IP address. This is important to ensure we don't
	 * leak that the fully registered user edited from that IP.
	 */
	public function addDBDataOnce() {
		$actors = [
			[
				'name' => '~2024-1',
				'id' => 1231,
			],
			[
				'name' => 'FullyRegistered',
				'id' => 2342,
			],
		];

		foreach ( $actors as $actor ) {
			$this->db->newInsertQueryBuilder()
				->insertInto( 'actor' )
				->row( [
					'actor_name' => $actor['name'],
					'actor_id' => $actor['id'],
					'actor_user' => null
				] )
				->execute();

			$this->db->newInsertQueryBuilder()
				->insertInto( 'cu_changes' )
				->row( [
					'cuc_actor'      => $actor['id'],
					'cuc_ip'         => '1.2.3.4',
					'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
					'cuc_namespace'  => NS_MAIN,
					'cuc_title'      => 'Foo_Page',
					'cuc_minor'      => 0,
					'cuc_page_id'    => 1,
					'cuc_timestamp'  => $this->db->timestamp(),
					'cuc_xff'        => 0,
					'cuc_xff_hex'    => null,
					'cuc_actiontext' => '',
					'cuc_comment_id' => 0,
					'cuc_this_oldid' => 0,
					'cuc_last_oldid' => 0,
					'cuc_type'       => RC_EDIT,
					'cuc_agent'      => '',
				] )
				->execute();
		}

		self::$disallowedUser = static::getTestUser()->getUser();
		self::$checkuser = static::getTestUser( [ 'checkuser' ] )->getUser();
		self::$sysop = static::getTestSysop()->getUser();
	}
}
