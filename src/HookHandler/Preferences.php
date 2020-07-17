<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class Preferences implements GetPreferencesHook {

	/** @var string */
	public const INVESTIGATE_TOUR_SEEN = 'checkuser-investigate-tour-seen';

	/** @var string */
	public const INVESTIGATE_FORM_TOUR_SEEN = 'checkuser-investigate-form-tour-seen';

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[self::INVESTIGATE_TOUR_SEEN] = [
			'type' => 'api',
		];

		$preferences[self::INVESTIGATE_FORM_TOUR_SEEN] = [
			'type' => 'api',
		];
	}

}
