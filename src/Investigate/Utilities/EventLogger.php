<?php

namespace MediaWiki\CheckUser\Investigate\Utilities;

use ExtensionRegistry;
use MediaWiki\Extension\EventLogging\EventLogging;

class EventLogger {
	/** @var ExtensionRegistry */
	private $extensionRegistry;

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 */
	public function __construct(
		ExtensionRegistry $extensionRegistry
	) {
		$this->extensionRegistry = $extensionRegistry;
	}

	/**
	 * Log an event using the SpecialInvestigate schema.
	 *
	 * @param array $event
	 */
	public function logEvent( $event ): void {
		if ( $this->extensionRegistry->isLoaded( 'EventLogging' ) ) {
			EventLogging::logEvent(
				'SpecialInvestigate',
				// Revision ID of the schema - keep this in sync with extension.json
				20261100,
				$event
			);
		}
	}

	/**
	 * Get a timestamp in milliseconds.
	 *
	 * @return int
	 */
	public function getTime(): int {
		return (int)round( microtime( true ) * 1000 );
	}
}
