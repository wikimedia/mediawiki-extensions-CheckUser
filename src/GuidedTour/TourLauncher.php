<?php

namespace MediaWiki\CheckUser\GuidedTour;

use ExtensionRegistry;
use GuidedTourLauncher;

class TourLauncher {
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
	 * Launch a Guided Tour if the extension is loaded.
	 *
	 * @see GuidedTourLauncher::launchTour
	 *
	 * @param string $tourName
	 * @param string $step
	 */
	public function launchTour( string $tourName, string $step ) : void {
		if ( !$this->extensionRegistry->isLoaded( 'GuidedTour' ) ) {
			return;
		}

		GuidedTourLauncher::launchTour( $tourName, $step );
	}
}
