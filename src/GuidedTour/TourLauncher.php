<?php

namespace MediaWiki\CheckUser\GuidedTour;

use ExtensionRegistry;
use HtmlArmor;
use MediaWiki\Extension\GuidedTour\GuidedTourLauncher;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;

class TourLauncher {
	/** @var ExtensionRegistry */
	private $extensionRegistry;

	/** @var LinkRenderer */
	private $linkRenderer;

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct(
		ExtensionRegistry $extensionRegistry,
		LinkRenderer $linkRenderer
	) {
		$this->extensionRegistry = $extensionRegistry;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * Launch a Guided Tour if the extension is loaded.
	 *
	 * @see GuidedTourLauncher::launchTour
	 *
	 * @param string $tourName
	 * @param string $step
	 */
	public function launchTour( string $tourName, string $step ): void {
		if ( !$this->extensionRegistry->isLoaded( 'GuidedTour' ) ) {
			return;
		}

		GuidedTourLauncher::launchTour( $tourName, $step );
	}

	/**
	 * @param string $tourName
	 * @param LinkTarget $target
	 * @param string|HtmlArmor|null $text
	 * @param array $extraAttribs
	 * @param array $query
	 * @return string HTML
	 */
	public function makeTourLink(
		string $tourName,
		LinkTarget $target,
		$text = null,
		array $extraAttribs = [],
		array $query = []
	): string {
		if ( !$this->extensionRegistry->isLoaded( 'GuidedTour' ) ) {
			return '';
		}

		return $this->linkRenderer->makeLink(
			$target,
			$text,
			$extraAttribs,
			array_merge( $query, [
				'tour' => $tourName,
			] )
		);
	}
}
