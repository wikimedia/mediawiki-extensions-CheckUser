<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\TimelineService;
use MediaWiki\CheckUser\UserManager;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\TimelineService
 */
class TimelineServiceTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGetQueryInfo
	 */
	public function testGetQueryInfo( $targets, $start, $expected ) {
		$db = $this->getMockBuilder( Database::class )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
		$db->method( 'strencode' )
			->will( $this->returnArgument( 0 ) );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnectionRef' )
			->willReturn( $db );

		$userManager = $this->createMock( UserManager::class );
		$userManager->method( 'idFromName' )
			->will( $this->returnValueMap( [
				[ 'User1', 11111, ],
			] ) );

		$timelineService = new TimelineService( $loadBalancer, $userManager );

		$q = $timelineService->getQueryInfo( $targets, [], $start );

		foreach ( $expected['targets'] as $target ) {
			$this->assertStringContainsString( $target, $q['conds'][0] );
		}

		foreach ( $expected['conds'] as $cond ) {
			$this->assertStringContainsString( $cond, $q['conds'][0] );
		}

		if ( $start === '' ) {
			$this->assertCount( 1, $q['conds'] );
		} else {
			$this->assertCount( 2, $q['conds'] );
			$this->assertStringContainsString( 'cuc_timestamp >=', $q['conds'][1] );
		}
	}

	public function provideGetQueryInfo() {
		$range = IPUtils::parseRange( '127.0.0.1/24' );
		return [
			'Valid username' => [
				[ 'User1' ],
				'',
				[
					'targets' => [ '11111' ],
					'conds' => [ 'cuc_user' ],
				],
			],
			'Valid username, with start' => [
				[ 'User1' ],
				'111',
				[
					'targets' => [ '11111' ],
					'conds' => [ 'cuc_user' ],
				],
			],
			'Valid IP' => [
				[ '1.2.3.4' ],
				'',
				[
					'targets' => [ IPUtils::toHex( '1.2.3.4' ) ],
					'conds' => [ 'cuc_ip_hex' ],
				],
			],
			'Multiple valid targets' => [
				[ '1.2.3.4', 'User1' ],
				'',
				[
					'targets' => [ '11111', IPUtils::toHex( '1.2.3.4' ) ],
					'conds' => [ 'cuc_ip_hex', 'cuc_user' ],
				],
			],
			'Valid IP range' => [
				[ '127.0.0.1/24', 'User1' ],
				'',
				[
					'targets' => [ '11111' ] + $range,
					'conds' => [ 'cuc_ip_hex >=', 'cuc_ip_hex <=', 'cuc_user' ],
				],
			],
			'Some valid targets' => [
				[ 'User1', 'InvalidUser', '1.1..23', '::1' ],
				'',
				[
					'targets' => [ '11111', IPUtils::toHex( '::1' ) ],
					'conds' => [ 'cuc_user', 'cuc_ip_hex' ],
				],
			],
			'Invalid targets' => [
				[ 'InvalidUser' ],
				'',
				[
					'targets' => [],
					'conds' => [ '0' ],
				],
			]
		];
	}
}
