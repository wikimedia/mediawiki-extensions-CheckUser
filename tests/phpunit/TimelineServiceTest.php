<?php

use MediaWiki\CheckUser\TimelineService;
use MediaWiki\MediaWikiServices;
use Wikimedia\IPUtils;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\TimelineService
 */
class TimelineServiceTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideGetQueryInfo
	 */
	public function testGetQueryInfo( $targets, $expected ) {
		$services = MediaWikiServices::getInstance();
		$timelineService = $this->getMockBuilder( TimelineService::class )
			->setConstructorArgs( [ $services->getDBLoadBalancer() ] )
			->setMethods( [ 'getUserId' ] )
			->getMock();

		$timelineService->method( 'getUserId' )
			->will( $this->returnValueMap( [
				[ 'User1', 11111, ],
			] ) );

		$q = $timelineService->getQueryInfo( $targets );

		foreach ( $expected['targets'] as $target ) {
			$this->assertTrue( strpos( $q['conds'][0], $target ) !== false );
		}

		foreach ( $expected['conds'] as $cond ) {
			$this->assertTrue( strpos( $q['conds'][0], $cond ) !== false );
		}
	}

	public function provideGetQueryInfo() {
		$range = IPUtils::parseRange( '127.0.0.1/24' );
		return [
			'Valid username' => [
				[ 'User1' ],
				[
					'targets' => [ '11111' ],
					'conds' => [ 'cuc_user' ],
				],
			],
			'Valid IP' => [
				[ '1.2.3.4' ],
				[
					'targets' => [ IPUtils::toHex( '1.2.3.4' ) ],
					'conds' => [ 'cuc_ip_hex' ],
				],
			],
			'Multiple valid targets' => [
				[ '1.2.3.4', 'User1' ],
				[
					'targets' => [ '11111', IPUtils::toHex( '1.2.3.4' ) ],
					'conds' => [ 'cuc_ip_hex', 'cuc_user' ],
				],
			],
			'Valid IP range' => [
				[ '127.0.0.1/24', 'User1' ],
				[
					'targets' => [ '11111' ] + $range,
					'conds' => [ 'cuc_ip_hex >=', 'cuc_ip_hex <=', 'cuc_user' ],
				],
			],
			'Some valid targets' => [
				[ 'User1', 'InvalidUser', '1.1..23', '::1' ],
				[
					'targets' => [ '11111', IPUtils::toHex( '::1' ) ],
					'conds' => [ 'cuc_user', 'cuc_ip_hex' ],
				],
			],
			'Invalid targets' => [
				[ 'InvalidUser' ],
				[
					'targets' => [],
					'conds' => [ '0' ],
				],
			]
		];
	}
}
