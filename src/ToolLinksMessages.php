<?php

namespace MediaWiki\CheckUser;

use Config;
use MediaWiki\ResourceLoader\Context;

class ToolLinksMessages {

	/**
	 * @param Context $context
	 * @param Config $config
	 * @param string $messageKey
	 *
	 * @return array
	 */
	public static function getParsedMessage(
		Context $context,
		Config $config,
		string $messageKey
	) {
		return [ $messageKey => $context->msg( $messageKey )->parse() ];
	}
}
