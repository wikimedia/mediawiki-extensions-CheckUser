<?php

namespace MediaWiki\CheckUser;

use Config;
use ResourceLoaderContext;

class ToolLinksMessages {

	public static function getParsedMessage(
		ResourceLoaderContext $context,
		Config $config,
		string $messageKey
	) {
		return [ $messageKey => $context->msg( $messageKey )->parse() ];
	}
}
