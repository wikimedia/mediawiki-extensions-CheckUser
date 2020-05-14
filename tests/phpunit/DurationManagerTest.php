<?php

namespace MediaWiki\CheckUser\Tests;

use FauxRequest;
use MediaWiki\CheckUser\DurationManager;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\DurationManager
 */
class DurationManagerTest extends MediaWikiIntegrationTestCase {

	public function setUp() : void {
		parent::setUp();
		\MWTimestamp::setFakeTime( 0 );
	}

	/**
	 * @dataProvider provideDuration
	 */
	public function testGetFromRequest( string $duration, string $timestamp ) : void {
		$valid = ( $timestamp !== '' );
		$durationManager = new DurationManager();

		$request = new FauxRequest( [
			'duration' => $duration,
		] );

		$this->assertSame( $valid ? $duration : '', $durationManager->getFromRequest( $request ) );
	}

	/**
	 * @dataProvider provideDuration
	 */
	public function testIsValid( string $duration, string $timestamp ) : void {
		$valid = ( $timestamp !== '' );
		$durationManager = new DurationManager();

		$this->assertSame( $valid, $durationManager->isValid( $duration ) );
	}

	/**
	 * @dataProvider provideDuration
	 */
	public function testGetTimestampFromRequest( string $duration, string $timestamp ) : void {
		$durationManager = new DurationManager();

		$request = new FauxRequest( [
			'duration' => $duration,
		] );

		$this->assertSame( $timestamp, $durationManager->getTimestampFromRequest( $request ) );
	}

	/**
	 * Provides durations.
	 */
	public function provideDuration() : array {
		return [
			'Valid duration' => [
				'P1W',
				'19691225000000',
			],
			'Invalid duration' => [
				'fail!',
				'',
			],
		];
	}
}
