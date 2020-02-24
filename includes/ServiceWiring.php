<?php

use MediaWiki\CheckUser\ComparePagerFactory;
use MediaWiki\CheckUser\CompareService;
use MediaWiki\CheckUser\PreliminaryCheckPagerFactory;
use MediaWiki\CheckUser\PreliminaryCheckService;
use MediaWiki\CheckUser\TokenManager;
use MediaWiki\MediaWikiServices;

return [
	'CheckUserPreliminaryCheckService' => function (
		MediaWikiServices $services
	) : PreliminaryCheckService {
		return new PreliminaryCheckService(
			$services->getDBLoadBalancerFactory(),
			ExtensionRegistry::getInstance(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	'CheckUserCompareService' => function ( MediaWikiServices $services ) : CompareService {
		return new CompareService( $services->getDBLoadBalancer() );
	},
	'CheckUserTokenManager' => function ( MediaWikiServices $services ) : TokenManager {
		return new TokenManager(
			$services->getMainConfig()->get( 'SecretKey' )
		);
	},
	'CheckUserPreliminaryCheckPagerFactory' => function (
		MediaWikiServices $services
	) : PreliminaryCheckPagerFactory {
		return new PreliminaryCheckPagerFactory(
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			\ExtensionRegistry::getInstance(),
			$services->get( 'CheckUserTokenManager' ),
			$services->get( 'CheckUserPreliminaryCheckService' )
		);
	},
	'CheckUserComparePagerFactory' => function ( MediaWikiServices $services ) : ComparePagerFactory {
		return new ComparePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserTokenManager' ),
			$services->get( 'CheckUserCompareService' )
		);
	},
];
