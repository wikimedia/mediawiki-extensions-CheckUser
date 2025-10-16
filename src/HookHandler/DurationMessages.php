<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\ResourceLoader\Context;

/**
 * Used by ResourceLoader to generate virtual files containing translated durations for the
 * IP auto-reveal feature. This allows translations that must be done using PHP to be accessed
 * by client-side modules.
 */
class DurationMessages {
	/**
	 * Get translations of options for the select in IPAutoRevealOnDialog.vue.
	 *
	 * @param Context $context
	 * @param Config $config
	 * @param int[] $durations Durations in seconds
	 *
	 * @return array[] Array of objects specifying durations in seconds and their
	 *  associated translations:
	 *  - seconds: (int) the duration in seconds
	 *  - translation: (string) the translated duration
	 */
	public static function getTranslatedDurations(
		Context $context,
		Config $config,
		array $durations
	): array {
		$translations = [];
		foreach ( $durations as $duration ) {
			$translations[] = [
				'seconds' => $duration,
				'translation' => $context->msg( 'checkuser-ip-auto-reveal-on-dialog-select-duration' )
					->durationParams( $duration )
					->text(),
			];
		}
		return $translations;
	}
}
