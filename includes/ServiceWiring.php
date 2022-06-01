<?php

use MediaWiki\CheckUser\ComparePagerFactory;
use MediaWiki\CheckUser\CompareService;
use MediaWiki\CheckUser\DurationManager;
use MediaWiki\CheckUser\EventLogger;
use MediaWiki\CheckUser\GuidedTour\TourLauncher;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\PreliminaryCheckPagerFactory;
use MediaWiki\CheckUser\PreliminaryCheckService;
use MediaWiki\CheckUser\TimelinePagerFactory;
use MediaWiki\CheckUser\TimelineRowFormatterFactory;
use MediaWiki\CheckUser\TimelineService;
use MediaWiki\CheckUser\TokenManager;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'CheckUserPreliminaryCheckService' => static function (
		MediaWikiServices $services
	): PreliminaryCheckService {
		return new PreliminaryCheckService(
			$services->getDBLoadBalancerFactory(),
			ExtensionRegistry::getInstance(),
			$services->getUserGroupManagerFactory(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	'CheckUserCompareService' => static function ( MediaWikiServices $services ): CompareService {
		return new CompareService(
			new ServiceOptions(
				CompareService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancer(),
			$services->getUserIdentityLookup()
		);
	},
	'CheckUserTimelineService' => static function ( MediaWikiServices $services ): TimelineService {
		return new TimelineService(
			$services->getDBLoadBalancer()->getConnection( DB_REPLICA ),
			$services->getDBLoadBalancer()->getConnection( DB_REPLICA ),
			$services->getUserIdentityLookup()
		);
	},
	'CheckUserTokenManager' => static function ( MediaWikiServices $services ): TokenManager {
		return new TokenManager(
			$services->getMainConfig()->get( 'SecretKey' )
		);
	},
	'CheckUserTokenQueryManager' => static function ( MediaWikiServices $services ): TokenQueryManager {
		return new TokenQueryManager(
			$services->get( 'CheckUserTokenManager' )
		);
	},
	'CheckUserDurationManager' => static function ( MediaWikiServices $services ): DurationManager {
		return new DurationManager();
	},
	'CheckUserGuidedTourLauncher' => static function ( MediaWikiServices $services ): TourLauncher {
		return new TourLauncher(
			ExtensionRegistry::getInstance(),
			$services->getLinkRenderer()
		);
	},
	'CheckUserPreliminaryCheckPagerFactory' => static function (
		MediaWikiServices $services
	): PreliminaryCheckPagerFactory {
		return new PreliminaryCheckPagerFactory(
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			\ExtensionRegistry::getInstance(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserPreliminaryCheckService' )
		);
	},
	'CheckUserComparePagerFactory' => static function ( MediaWikiServices $services ): ComparePagerFactory {
		return new ComparePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserDurationManager' ),
			$services->get( 'CheckUserCompareService' )
		);
	},
	'CheckUserTimelineRowFormatterFactory' => static function (
		MediaWikiServices $services
	): TimelineRowFormatterFactory {
		return new TimelineRowFormatterFactory(
			$services->getLinkRenderer(),
			$services->getDBLoadBalancer(),
			$services->getRevisionStore(),
			$services->getTitleFormatter(),
			$services->getSpecialPageFactory(),
			$services->getCommentFormatter(),
			$services->getUserFactory()
		);
	},
	'CheckUserTimelinePagerFactory' => static function (
		MediaWikiServices $services
	): TimelinePagerFactory {
		return new TimelinePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserHookRunner' ),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserDurationManager' ),
			$services->get( 'CheckUserTimelineService' ),
			$services->get( 'CheckUserTimelineRowFormatterFactory' ),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'CheckUserEventLogger' => static function (
		 MediaWikiServices $services
	): EventLogger {
		return new EventLogger(
			\ExtensionRegistry::getInstance()
		);
	},
	'CheckUserHookRunner' => static function (
		MediaWikiServices $services
	): HookRunner {
		return new HookRunner(
			$services->getHookContainer()
		);
	}
];
