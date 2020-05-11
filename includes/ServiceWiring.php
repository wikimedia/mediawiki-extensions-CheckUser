<?php

use MediaWiki\CheckUser\ComparePagerFactory;
use MediaWiki\CheckUser\CompareService;
use MediaWiki\CheckUser\InvestigateLogPagerFactory;
use MediaWiki\CheckUser\PreliminaryCheckPagerFactory;
use MediaWiki\CheckUser\PreliminaryCheckService;
use MediaWiki\CheckUser\TimelinePagerFactory;
use MediaWiki\CheckUser\TimelineRowFormatterFactory;
use MediaWiki\CheckUser\TimelineService;
use MediaWiki\CheckUser\TokenManager;
use MediaWiki\CheckUser\TokenQueryManager;
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
	'CheckUserTimelineService' => function ( MediaWikiServices $services ) : TimelineService {
		return new TimelineService( $services->getDBLoadBalancer() );
	},
	'CheckUserTokenManager' => function ( MediaWikiServices $services ) : TokenManager {
		return new TokenManager(
			$services->getMainConfig()->get( 'SecretKey' )
		);
	},
	'CheckUserTokenQueryManager' => function ( MediaWikiServices $services ) : TokenQueryManager {
		return new TokenQueryManager(
			$services->get( 'CheckUserTokenManager' )
		);
	},
	'CheckUserInvestigateLogPagerFactory' => function (
		MediaWikiServices $services
	) : InvestigateLogPagerFactory {
		return new InvestigateLogPagerFactory(
			$services->getLinkRenderer()
		);
	},
	'CheckUserPreliminaryCheckPagerFactory' => function (
		MediaWikiServices $services
	) : PreliminaryCheckPagerFactory {
		return new PreliminaryCheckPagerFactory(
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			\ExtensionRegistry::getInstance(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserPreliminaryCheckService' )
		);
	},
	'CheckUserComparePagerFactory' => function ( MediaWikiServices $services ) : ComparePagerFactory {
		return new ComparePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserCompareService' )
		);
	},
	'CheckUserTimelineRowFormatterFactory' => function (
		MediaWikiServices $services
	) : TimelineRowFormatterFactory {
		return new TimelineRowFormatterFactory(
			$services->getLinkRenderer(),
			$services->getDBLoadBalancer(),
			$services->getRevisionLookup(),
			$services->getRevisionStore(),
			$services->getRevisionFactory()
		);
	},
	'CheckUserTimelinePagerFactory' => function (
		MediaWikiServices $services
	) : TimelinePagerFactory {
		return new TimelinePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserTimelineService' ),
			$services->get( 'CheckUserTimelineRowFormatterFactory' )
		);
	},
];
