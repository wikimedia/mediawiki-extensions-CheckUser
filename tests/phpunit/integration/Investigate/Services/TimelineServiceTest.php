<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Services;

use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\Tests\Unit\Libs\Rdbms\AddQuoterMock;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\Platform\SQLPlatform;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\Investigate\Services\TimelineService
 */
class TimelineServiceTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGetQueryInfo
	 */
	public function testGetQueryInfo( $targets, $start, $expected ) {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )
			->willReturn( 11111 );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByName' )
			->willReturnMap(
				[
					[ 'User1', 0, $user, ],
				]
			);

		$timelineService = new TimelineService(
			new AddQuoterMock(),
			new SQLPlatform( new AddQuoterMock() ),
			$userIdentityLookup,
			$this->getServiceContainer()->getCommentStore()
		);

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

	public static function provideGetQueryInfo() {
		$range = IPUtils::parseRange( '127.0.0.1/24' );
		return [
			'Valid username' => [
				[ 'User1' ],
				'',
				[
					'targets' => [ '11111' ],
					'conds' => [ 'actor_user' ],
				],
			],
			'Valid username, with start' => [
				[ 'User1' ],
				'111',
				[
					'targets' => [ '11111' ],
					'conds' => [ 'actor_user' ],
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
					'conds' => [ 'cuc_ip_hex', 'actor_user' ],
				],
			],
			'Valid IP range' => [
				[ '127.0.0.1/24', 'User1' ],
				'',
				[
					'targets' => [ '11111' ] + $range,
					'conds' => [ 'cuc_ip_hex >=', 'cuc_ip_hex <=', 'actor_user' ],
				],
			],
			'Some valid targets' => [
				[ 'User1', 'InvalidUser', '1.1..23', '::1' ],
				'',
				[
					'targets' => [ '11111', IPUtils::toHex( '::1' ) ],
					'conds' => [ 'actor_user', 'cuc_ip_hex' ],
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
