<?php

use MediaWiki\CheckUser\PreliminaryCheckService;
use MediaWiki\MediaWikiServices;

return [
	'CheckUserPreliminaryCheckService' =>
	function ( MediaWikiServices $services ): PreliminaryCheckService {
		return new PreliminaryCheckService(
			$services->getDBLoadBalancerFactory(),
			ExtensionRegistry::getInstance(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	}
];
