<?php

use MediaWiki\CheckUser\CompareService;
use MediaWiki\MediaWikiServices;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\CompareService
 */
class CompareServiceTest extends MediaWikiTestCase {

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
	public function testGetQueryInfo( $targets, $expected ) {
		$services = MediaWikiServices::getInstance();

		$compareService = $this->getMockBuilder( CompareService::class )
			->setConstructorArgs( [ $services->getDBLoadBalancer() ] )
			->setMethods( [ 'getUserId' ] )
			->getMock();
		$compareService->method( 'getUserId' )
			->will( $this->returnValueMap( [
				[ 'User1', 11111, ],
			] ) );

		$queryInfo = $compareService->getQueryInfo( $targets );

		foreach ( $expected['targets'] as $target ) {
			$this->assertTrue( strpos( $queryInfo['tables']['a'], $target ) !== false );
		}

		$this->assertTrue( strpos( $queryInfo['tables']['a'], $expected['limit'] ) !== false );
	}

	public function provideGetQueryInfo() {
		return [
			'Valid username' => [
				[ 'User1' ],
				[
					'targets' => [ '11111' ],
					'limit' => '100000',
				],
			],
			'Single valid IP' => [
				[ '0:0:0:0:0:0:0:1' ],
				[
					'targets' => [ 'v6-00000000000000000000000000000001' ],
					'limit' => '100000',
				],
			],
			'Two valid IPs' => [
				[ '0:0:0:0:0:0:0:1', '1.2.3.4' ],
				[
					'targets' => [
						'v6-00000000000000000000000000000001',
						'01020304'
					],
					'limit' => '50000',
				],
			],
			'Valid IP addresses and IP range' => [
				[
					'0:0:0:0:0:0:0:1',
					'1.2.3.4',
					'1.2.3.4/16',
				],
				[
					'targets' => [
						'v6-00000000000000000000000000000001',
						'01020304',
						'01020000',
						'0102FFFF',
					],
					'limit' => '33333',
				],
			],
		];
	}

	public function testGetQueryInfoNoTargets() {
		$this->expectException( \LogicException::class );

		$compareService = new CompareService(
			$this->createMock( ILoadBalancer::class )
		);

		$compareService->getQueryInfo( [] );
	}

	/**
	 * @dataProvider provideGetQueryInfoForSingleTarget
	 */
	public function testGetQueryInfoForSingleTarget( $options, $expected ) {
		$info = $this->getCompareService()->getQueryInfoForSingleTarget(
			'1.2.3.4',
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
					'orderBy' => 'cuc_timestamp',
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
			$data['ip'], $data['userAgent'], $data['excludeUser'] ?? null
		);

		$this->assertEquals( $expected, $result );
	}

	public function provideTotalEditsFromIp() {
		return [
			[
				[
					'ip' => '1.2.3.5',
					'userAgent' => 'bar user agent',
				], [
					'total_edits' => 3,
					'total_users' => 2,
				],
			],
			[
				[
					'ip' => '1.2.3.4',
					'userAgent' => 'foo user agent',
					'excludeUser' => 'User1'
				], [
					'total_edits' => 5,
					'total_users' => 3,
				],
			],
			[
				[
					'ip' => '1.2.3.5',
					'userAgent' => 'foo user agent',
					'excludeUser' => 'User1'
				], [
					'total_edits' => 3,
					'total_users' => 2,
				],
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
