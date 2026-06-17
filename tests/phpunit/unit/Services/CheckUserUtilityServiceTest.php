<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Extension\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\Request\ProxyLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserUtilityService
 */
class CheckUserUtilityServiceTest extends MediaWikiUnitTestCase {

	/**
	 * @param string[] $configuredProxies IPs that ProxyLookup::isConfiguredProxy() returns true for
	 * @param bool $usePrivateIPs Value of the $wgUsePrivateIPs-style flag
	 * @return CheckUserUtilityService
	 */
	private function makeService( array $configuredProxies, bool $usePrivateIPs ): CheckUserUtilityService {
		$proxyLookup = $this->createMock( ProxyLookup::class );
		$proxyLookup->method( 'isConfiguredProxy' )->willReturnCallback(
			static fn ( $ip ) => in_array( $ip, $configuredProxies, true )
		);
		return new CheckUserUtilityService( $proxyLookup, $usePrivateIPs );
	}

	/**
	 * @param string|bool $xff
	 * @param string[] $configuredProxies
	 * @param bool $usePrivateIPs
	 * @param array $expected [ clientIP|null, isSquidOnly, xffString ]
	 * @dataProvider provideGetClientIPfromXFF
	 */
	public function testGetClientIPfromXFF(
		$xff,
		array $configuredProxies,
		bool $usePrivateIPs,
		array $expected
	): void {
		$service = $this->makeService( $configuredProxies, $usePrivateIPs );
		$this->assertSame( $expected, $service->getClientIPfromXFF( $xff ) );
	}

	public static function provideGetClientIPfromXFF(): array {
		return [
			'empty string returns no client' => [ '', [], false, [ null, false, '' ] ],
			'false returns no client' => [ false, [], false, [ null, false, '' ] ],
			'single public IP' => [ '1.2.3.4', [], false, [ '1.2.3.4', false, '1.2.3.4' ] ],
			'single IP that is a configured proxy is squid-only' =>
				[ '1.2.3.4', [ '1.2.3.4' ], false, [ '1.2.3.4', true, '1.2.3.4' ] ],
			'two public hops walks out to the outermost' =>
				[ '5.6.7.8, 1.2.3.4', [], false, [ '5.6.7.8', false, '5.6.7.8, 1.2.3.4' ] ],
			'private next hop with usePrivateIPs off stops at client' =>
				[ '192.168.1.1, 1.2.3.4', [], false, [ '1.2.3.4', false, '192.168.1.1, 1.2.3.4' ] ],
			'private next hop with usePrivateIPs on walks up' =>
				[ '192.168.1.1, 1.2.3.4', [], true, [ '192.168.1.1', false, '192.168.1.1, 1.2.3.4' ] ],
			'chain of configured proxies stays squid-only' =>
				[ '5.5.5.5, 6.6.6.6', [ '5.5.5.5', '6.6.6.6' ], false, [ '5.5.5.5', true, '5.5.5.5, 6.6.6.6' ] ],
			'invalid next hop breaks the walk' =>
				[ 'notanip, 1.2.3.4', [], false, [ '1.2.3.4', false, 'notanip, 1.2.3.4' ] ],
		];
	}
}
