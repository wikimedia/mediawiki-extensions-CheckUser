<?php

namespace MediaWiki\CheckUser\Logging;

use LogFormatter;

class TemporaryAccountLogFormatter extends LogFormatter {
	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();

		// Update the logline depending on if the user had their access enabled or disabled
		if ( $this->entry->getSubtype() === 'change-access' ) {
			// Message keys used:
			// - 'checkuser-temporary-account-change-access-level-enable'
			// - 'checkuser-temporary-account-change-access-level-disable'
			$params[3] = $this->msg( 'checkuser-temporary-account-change-access-level-' . $params[3], $params[1] );
		}

		return $params;
	}
}
