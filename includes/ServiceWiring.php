<?php

use MediaWiki\CheckUser\CompareService;
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
	},
	'CheckUserCompareService' => function ( MediaWikiServices $services ) : CompareService {
		return new CompareService( $services->getDBLoadBalancer() );
	},
];
