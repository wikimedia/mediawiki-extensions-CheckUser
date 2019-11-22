<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\CheckUser\PreliminaryCheckService;

return [
	'PreliminaryCheckService' => function ( MediaWikiServices $services ): PreliminaryCheckService {
		return new PreliminaryCheckService(
			$services->getDBLoadBalancerFactory(),
			ExtensionRegistry::getInstance(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	}
];
