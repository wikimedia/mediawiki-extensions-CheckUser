<?php

use MediaWiki\CheckUser\CheckUserLogService;
use MediaWiki\CheckUser\CheckUserUtilityService;
use MediaWiki\CheckUser\GuidedTour\TourLauncher;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\Investigate\Pagers\ComparePagerFactory;
use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPagerFactory;
use MediaWiki\CheckUser\Investigate\Pagers\TimelinePagerFactory;
use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatterFactory;
use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\CheckUser\TokenManager;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in CheckUserServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'CheckUserLogService' => static function (
		MediaWikiServices $services
	): CheckUserLogService {
		return new CheckUserLogService(
			$services->getDBLoadBalancer(),
			$services->getCommentStore(),
			$services->getCommentFormatter(),
			LoggerFactory::getInstance( 'CheckUser' ),
			$services->getActorStore()
		);
	},
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
			$services->getDBLoadBalancerFactory(),
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
			$services->get( 'CheckUserPreliminaryCheckService' ),
			$services->getUserFactory()
		);
	},
	'CheckUserComparePagerFactory' => static function ( MediaWikiServices $services ): ComparePagerFactory {
		return new ComparePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserDurationManager' ),
			$services->get( 'CheckUserCompareService' ),
			$services->getUserFactory()
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
	},
	'CheckUserUtilityService' => static function (
		MediaWikiServices $services
	): CheckUserUtilityService {
		return new CheckUserUtilityService(
			$services->getProxyLookup(),
			$services->getMainConfig()->get( 'UsePrivateIPs' )
		);
	}
];
// @codeCoverageIgnoreEnd
