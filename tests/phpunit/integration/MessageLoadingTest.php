<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration;

use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @coversNothing
 */
class MessageLoadingTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideMessageGroups
	 */
	public function testMessagesCanBeLoaded( array $messageKeys ): void {
		foreach ( $messageKeys as $key ) {
			$message = wfMessage( $key )->inLanguage( 'en' )->useDatabase( false );
			$this->assertTrue( $message->exists(), "Message '$key' must be available to the UI" );
		}
	}

	public static function provideMessageGroups(): iterable {
		$root = dirname( __DIR__, 3 );
		$files = array_merge( glob( "$root/i18n/en.json" ), glob( "$root/i18n/*/en.json" ) );
		foreach ( $files as $file ) {
			$messages = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
			unset( $messages['@metadata'] );
			$group = str_replace( '\\', '/', substr( dirname( $file ), strlen( $root ) + 1 ) );
			yield $group => [ array_keys( $messages ) ];
		}
	}
}
