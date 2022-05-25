<?php

namespace MediaWiki\CheckUser;

use Config;
use MediaWiki\ResourceLoader\Context;

class ToolLinksMessages {

	public static function getParsedMessage(
		Context $context,
		Config $config,
		string $messageKey
	) {
		return [ $messageKey => $context->msg( $messageKey )->parse() ];
	}
}
