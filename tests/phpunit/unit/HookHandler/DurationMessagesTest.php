<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\HookHandler\DurationMessages;
use MediaWiki\Message\Message;
use MediaWiki\ResourceLoader\Context;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\DurationMessages
 */
class DurationMessagesTest extends MediaWikiUnitTestCase {

	/**
	 * Mocks a Message that records the durations passed to it, so that tests can assert the
	 * duration actually reaches the message instead of only checking the rendered result.
	 *
	 * text() and parse() deliberately return different strings, so that a test asserting on
	 * one of them fails if the code under test calls the other.
	 *
	 * @param int[] &$durationParams Populated with the durations passed to durationParams()
	 */
	private function createDurationMessageMock( array &$durationParams ): Message {
		$msg = $this->createMock( Message::class );
		$msg->method( 'durationParams' )
			->willReturnCallback( static function ( ...$params ) use ( &$durationParams, $msg ) {
				array_push( $durationParams, ...$params );
				// durationParams() returns $this in core, so the mock must be chainable too.
				return $msg;
			} );
		$msg->method( 'text' )->willReturn( 'text result' );
		$msg->method( 'parse' )->willReturn( 'parse result' );
		return $msg;
	}

	public function testGetTranslatedDurations(): void {
		$durationParams = [];
		$context = $this->createMock( Context::class );
		$context->method( 'msg' )
			->with( 'checkuser-ip-auto-reveal-on-dialog-select-duration' )
			->willReturn( $this->createDurationMessageMock( $durationParams ) );

		$this->assertSame(
			[
				[ 'seconds' => 3600, 'translation' => 'text result' ],
				[ 'seconds' => 86400, 'translation' => 'text result' ],
			],
			DurationMessages::getTranslatedDurations(
				$context,
				new HashConfig( [] ),
				[ 3600, 86400 ]
			)
		);
		$this->assertSame( [ 3600, 86400 ], $durationParams, 'Each duration is passed to the message' );
	}

	public function testGetTranslatedDurationsWithNoDurations(): void {
		$context = $this->createNoOpMock( Context::class );

		$this->assertSame(
			[],
			DurationMessages::getTranslatedDurations( $context, new HashConfig( [] ), [] )
		);
	}

	public function testGetTranslatedMaxDuration(): void {
		$durationParams = [];
		$context = $this->createMock( Context::class );
		$context->method( 'msg' )
			->with( 'checkuser-ip-auto-reveal-off-dialog-error-extend-limit' )
			->willReturn( $this->createDurationMessageMock( $durationParams ) );

		$this->assertSame(
			[ 'translation' => 'text result' ],
			DurationMessages::getTranslatedMaxDuration(
				$context,
				new HashConfig( [ 'CheckUserAutoRevealMaximumExpiry' => 7776000 ] )
			)
		);
		$this->assertSame(
			[ 7776000 ],
			$durationParams,
			'The configured maximum expiry is passed to the message'
		);
	}

	public function testGetAutoRevealMaximumExpiry(): void {
		$durationParams = [];
		$context = $this->createMock( Context::class );
		// phpcs:ignore Generic.Files.LineLength
		$msgKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text-with-global-preferences-with-autoreveal';
		$context->method( 'msg' )
			->with( $msgKey )
			->willReturn( $this->createDurationMessageMock( $durationParams ) );

		// This message is parsed, unlike the durations used by the auto-reveal dialogs.
		$this->assertSame(
			'parse result',
			DurationMessages::getAutoRevealMaximumExpiry(
				$context,
				new HashConfig( [ 'CheckUserAutoRevealMaximumExpiry' => 7776000 ] )
			)
		);
		$this->assertSame(
			[ 7776000 ],
			$durationParams,
			'The configured maximum expiry is passed to the message'
		);
	}
}
