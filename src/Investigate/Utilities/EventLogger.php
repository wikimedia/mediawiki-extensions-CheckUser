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
				// NOTE: The 'SpecialInvestigate' event was migrated to the Event Platform, and is
				//  no longer using the legacy EventLogging schema from metawiki. $revId is actually
				//  overridden by the EventLoggingSchemas extension attribute in extension.json.
				-1,
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
