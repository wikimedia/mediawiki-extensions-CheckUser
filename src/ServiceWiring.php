<?php

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
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in CheckUserServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'CheckUserLogService' => static function (
		MediaWikiServices $services
	): CheckUserLogService {
		return new CheckUserLogService(
			$services->getDBLoadBalancerFactory(),
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
			$services->getDBLoadBalancerFactory(),
			$services->getUserIdentityLookup()
		);
	},
	'CheckUserTimelineService' => static function ( MediaWikiServices $services ): TimelineService {
		return new TimelineService(
			$services->getDBLoadBalancerFactory()->getReplicaDatabase(),
			$services->getDBLoadBalancerFactory()->getReplicaDatabase(),
			$services->getUserIdentityLookup(),
			$services->getCommentStore()
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
			$services->getRevisionStore(),
			$services->getArchivedRevisionLookup(),
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
	},
	'CheckUserTemporaryAccountLoggerFactory' => static function (
		MediaWikiServices $services
	): TemporaryAccountLoggerFactory {
		return new TemporaryAccountLoggerFactory(
			$services->getActorStore(),
			LoggerFactory::getInstance( 'CheckUser' ),
			$services->getDBLoadBalancerFactory()
		);
	},
	'UserAgentClientHintsManager' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsManager {
		return new UserAgentClientHintsManager(
			$services->getDBLoadBalancerFactory(),
			$services->getRevisionStore(),
			new ServiceOptions(
				UserAgentClientHintsManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'UserAgentClientHintsLookup' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsLookup {
		return new UserAgentClientHintsLookup(
			$services->getDBLoadBalancerFactory()->getReplicaDatabase()
		);
	},
	'UserAgentClientHintsFormatter' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsFormatter {
		return new UserAgentClientHintsFormatter(
			new DerivativeContext( RequestContext::getMain() ),
			new ServiceOptions(
				UserAgentClientHintsFormatter::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
];
// @codeCoverageIgnoreEnd
