<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\CompareService;
use MediaWiki\CheckUser\UserManager;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\CompareService
 */
class CompareServiceTest extends MediaWikiIntegrationTestCase {

	/** @var CompareService */
	private $service;

	/**
	 * Lazy load CompareService
	 *
	 * @return CompareService
	 */
	private function getCompareService(): CompareService {
		if ( !$this->service ) {
			$this->service = MediaWikiServices::getInstance()->get( 'CheckUserCompareService' );
		}

		return $this->service;
	}

	/**
	 * Sanity check for the subqueries built by getQueryInfo. Checks for the presence
	 * of valid targets and the presence of the expected per-target limit. Whitespace
	 * is not always predictable so look for the bare minimum in the SQL string.
	 *
	 * Invalid targets are tested in ComparePagerTest::testDoQuery.
	 *
	 * @dataProvider provideGetQueryInfo
	 */
	public function testGetQueryInfo( $options, $expected ) {
		$db = $this->getMockBuilder( Database::class )
			->setMethods( [
				'dbSchema',
				'tablePrefix',
			] )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
		$db->method( 'strencode' )
			->will( $this->returnArgument( 0 ) );
		$db->method( 'dbSchema' )
			->willReturn( '' );
		$db->method( 'tablePrefix' )
			->willReturn( '' );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnectionRef' )
			->willReturn( $db );

		$userManager = $this->createMock( UserManager::class );
		$userManager->method( 'idFromName' )
			->will( $this->returnValueMap( [
				[ 'User1', 11111, ],
				[ 'User2', 22222, ],
			] ) );

		$compareService = new CompareService(
			$loadBalancer,
			$userManager
		);

		$queryInfo = $compareService->getQueryInfo(
			$options['targets'],
			$options['excludeTargets'],
			$options['start']
		);

		foreach ( $expected['targets'] as $target ) {
			$this->assertStringContainsString( $target, $queryInfo['tables']['a'] );
		}

		foreach ( $expected['excludeTargets'] as $excludeTarget ) {
			$this->assertStringContainsString( $excludeTarget, $queryInfo['tables']['a'] );
		}

		$this->assertStringContainsString( 'LIMIT ' . $expected['limit'], $queryInfo['tables']['a'] );

		[ 'start' => $start ] = $expected;
		if ( $start === '' ) {
			$this->assertStringNotContainsString( 'cuc_timestamp >=', $queryInfo['tables']['a'] );
		} else {
			$this->assertStringContainsString( "cuc_timestamp >= '$start'", $queryInfo['tables']['a'] );
		}
	}

	public function provideGetQueryInfo() {
		return [
			'Valid username, excluded IP' => [
				[
					'targets' => [ 'User1' ],
					'excludeTargets' => [ '0:0:0:0:0:0:0:1' ],
					'start' => ''
				],
				[
					'targets' => [ '11111' ],
					'excludeTargets' => [ 'v6-00000000000000000000000000000001' ],
					'limit' => '100000',
					'start' => ''
				],
			],
			'Valid username, excluded IP, with start' => [
				[
					'targets' => [ 'User1' ],
					'excludeTargets' => [ '0:0:0:0:0:0:0:1' ],
					'start' => '111'
				],
				[
					'targets' => [ '11111' ],
					'excludeTargets' => [ 'v6-00000000000000000000000000000001' ],
					'limit' => '100000',
					'start' => '111'
				],
			],
			'Single valid IP, excluded username' => [
				[
					'targets' => [ '0:0:0:0:0:0:0:1' ],
					'excludeTargets' => [ 'User1' ],
					'start' => ''
				],
				[
					'targets' => [ 'v6-00000000000000000000000000000001' ],
					'excludeTargets' => [ '11111' ],
					'limit' => '100000',
					'start' => ''
				],
			],
			'Valid username and IP, excluded username and IP' => [
				[
					'targets' => [ 'User1', '1.2.3.4' ],
					'excludeTargets' => [ 'User2', '1.2.3.5' ],
					'start' => ''
				],
				[
					'targets' => [ '11111', '01020304' ],
					'excludeTargets' => [ '22222', '01020305' ],
					'limit' => '50000',
					'start' => ''
				],
			],
			'Two valid IPs' => [
				[
					'targets' => [ '0:0:0:0:0:0:0:1', '1.2.3.4' ],
					'excludeTargets' => [],
					'start' => ''
				],
				[
					'targets' => [
						'v6-00000000000000000000000000000001',
						'01020304'
					],
					'excludeTargets' => [],
					'limit' => '50000',
					'start' => ''
				],
			],
			'Valid IP addresses and IP range' => [
				[
					'targets' => [
						'0:0:0:0:0:0:0:1',
						'1.2.3.4',
						'1.2.3.4/16',
					],
					'excludeTargets' => [],
					'start' => ''
				],
				[
					'targets' => [
						'v6-00000000000000000000000000000001',
						'01020304',
						'01020000',
						'0102FFFF',
					],
					'excludeTargets' => [],
					'limit' => '33333',
					'start' => ''
				],
			],
		];
	}

	public function testGetQueryInfoNoTargets() {
		$this->expectException( \LogicException::class );

		$compareService = new CompareService(
			$this->createMock( ILoadBalancer::class ),
			$this->createMock( UserManager::class )
		);

		$compareService->getQueryInfo( [], [], '' );
	}

	/**
	 * @dataProvider provideGetQueryInfoForSingleTarget
	 */
	public function testGetQueryInfoForSingleTarget( $options, $expected ) {
		$db = $this->getMockBuilder( Database::class )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
		$db->method( 'strencode' )
			->will( $this->returnArgument( 0 ) );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnectionRef' )
			->willReturn( $db );

		$compareServcice = new CompareService(
			$loadBalancer,
			$this->createMock( UserManager::class )
		);

		$info = $compareServcice->getQueryInfoForSingleTarget(
			'1.2.3.4',
			[],
			'',
			$options['limitPerTarget'],
			$options['limitCheck']
		);

		$this->assertSame( $expected['orderBy'], $info['options']['ORDER BY'] );
		$this->assertSame( $expected['limit'], $info['options']['LIMIT'] );
		$this->assertSame( $expected['offset'], $info['options']['OFFSET'] );
	}

	public function provideGetQueryInfoForSingleTarget() {
		$limitPerTarget = 100;
		return [
			'Main investigation' => [
				[
					'limitPerTarget' => $limitPerTarget,
					'limitCheck' => false,
				],
				[
					'orderBy' => 'cuc_timestamp DESC',
					'offset' => null,
					'limit' => $limitPerTarget
				]
			],
			'Limit check' => [
				[
					'limitPerTarget' => $limitPerTarget,
					'limitCheck' => true,
				],
				[
					'orderBy' => null,
					'offset' => $limitPerTarget,
					'limit' => 1
				]
			],
		];
	}

	/**
	 * @dataProvider provideTotalEditsFromIp()
	 */
	public function testGetTotalEditsFromIp( $data, $expected ) {
		$result = $this->getCompareService()->getTotalEditsFromIp(
			$data['ip'], $data['excludeUser'] ?? null
		);

		$this->assertEquals( $expected, $result );
	}

	public function provideTotalEditsFromIp() {
		return [
			'IP address with multiple users' => [
				[
					'ip' => IPUtils::toHex( '1.2.3.5' )
				],
				3,
			],
			'IP address with multiple users, excluding a user' => [
				[
					'ip' => IPUtils::toHex( '1.2.3.4' ),
					'excludeUser' => 'User1'
				],
				4,
			],
		];
	}

	public function addDBData() {
		$testData = [
			[
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.4',
				'cuc_type'       => RC_NEW,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.4',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.4',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'bar user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.5',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'bar user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.5',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 11111,
				'cuc_user_text'  => 'User1',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 22222,
				'cuc_user_text'  => 'User2',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 11111,
				'cuc_user_text'  => 'User1',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'foo user agent',
			],
		];

		$commonData = [
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_timestamp'  => '',
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_actiontext' => '',
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
		];

		foreach ( $testData as $row ) {
			$this->db->insert( 'cu_changes', $row + $commonData );
		}

		$this->tablesUsed[] = 'cu_changes';
	}
}
