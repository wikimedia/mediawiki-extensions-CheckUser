<?php

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
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
use MediaWiki\CheckUser\IPContributions\IPContributionsPagerFactory;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexLookup;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\CheckUser\Services\CheckUserExpiredIdsLookupService;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Services\CheckUserIPRevealManager;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\GlobalIndefiniteBlockCheck;
use MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\LocalIndefiniteBlockCheck;
use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\ISuggestedInvestigationsInstrumentationClient;
use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\NoOpSuggestedInvestigationsInstrumentationClient;
use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsPagerFactory;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in CheckUserServiceWiringTest.php
// @codeCoverageIgnoreStart

/** @phpcs-require-sorted-array */
return [
	'AccountCreationDetailsLookup' => static function (
		MediaWikiServices $services
	): AccountCreationDetailsLookup {
		return new AccountCreationDetailsLookup(
			$services->get( 'CheckUserLogger' ),
			new ServiceOptions(
				AccountCreationDetailsLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	'ApiQueryCheckUserResponseFactory' => static function (
		MediaWikiServices $services
	): ApiQueryCheckUserResponseFactory {
		return new ApiQueryCheckUserResponseFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getMainConfig(),
			RequestContext::getMain(),
			$services->get( 'CheckUserLogService' ),
			$services->getUserNameUtils(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getUserIdentityLookup(),
			$services->getCommentStore(),
			$services->getRevisionStore(),
			$services->getArchivedRevisionLookup(),
			$services->getUserFactory(),
			$services->getLogFormatterFactory()
		);
	},
	'CheckUserApiRequestAggregator' => static function (
		MediaWikiServices $services
	): CheckUserApiRequestAggregator {
		return new CheckUserApiRequestAggregator(
			$services->getHttpRequestFactory(),
			$services->getCentralIdLookup(),
			$services->getExtensionRegistry(),
			$services->getSiteLookup(),
			$services->get( 'CheckUserLogger' )
		);
	},
	'CheckUserCentralIndexLookup' => static function (
		MediaWikiServices $services
	): CheckUserCentralIndexLookup {
		return new CheckUserCentralIndexLookup(
			$services->getConnectionProvider()
		);
	},
	'CheckUserCentralIndexManager' => static function (
		MediaWikiServices $services
	): CheckUserCentralIndexManager {
		return new CheckUserCentralIndexManager(
			new ServiceOptions(
				CheckUserCentralIndexManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getCentralIdLookup(),
			$services->getUserGroupManager(),
			$services->getJobQueueGroup(),
			$services->getTempUserConfig(),
			$services->getUserFactory(),
			$services->get( 'CheckUserLogger' )
		);
	},
	'CheckUserComparePagerFactory' => static function ( MediaWikiServices $services ): ComparePagerFactory {
		return new ComparePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserDurationManager' ),
			$services->get( 'CheckUserCompareService' ),
			$services->getUserFactory(),
			$services->getLinkBatchFactory()
		);
	},
	'CheckUserCompareService' => static function ( MediaWikiServices $services ): CompareService {
		return new CompareService(
			new ServiceOptions(
				CompareService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getTempUserConfig()
		);
	},
	'CheckUserCompositeIndefiniteBlockChecker' => static function (
		MediaWikiServices $services
	): CompositeIndefiniteBlockChecker {
		$blockChecks = [
			new LocalIndefiniteBlockCheck( $services->getDatabaseBlockStore() ),
		];

		if ( $services->getExtensionRegistry()->isLoaded( 'GlobalBlocking' ) ) {
			$globalBlockingServices = GlobalBlockingServices::wrap( $services );

			$blockChecks[] = new GlobalIndefiniteBlockCheck(
				$globalBlockingServices->getGlobalBlockLookup(),
				$services->getCentralIdLookup(),
				$services->getUserIdentityLookup(),
				$services->getMainConfig()->get( 'ApplyGlobalBlocks' )
			);
		}

		return new CompositeIndefiniteBlockChecker( $blockChecks );
	},
	'CheckUserDataPurger' => static function () {
		return new CheckUserDataPurger();
	},
	'CheckUserDurationManager' => static function (): DurationManager {
		return new DurationManager();
	},
	'CheckUserEventLogger' => static function (
		MediaWikiServices $services
	): EventLogger {
		return new EventLogger(
			$services->getExtensionRegistry()
		);
	},
	'CheckUserExpiredIdsLookupService' => static function (
		MediaWikiServices $services
	): CheckUserExpiredIdsLookupService {
		return new CheckUserExpiredIdsLookupService(
			new ServiceOptions(
				CheckUserExpiredIdsLookupService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider(),
			$services->getExtensionRegistry()
		);
	},
	'CheckUserGlobalContributionsLookup' => static function (
		MediaWikiServices $services
	): CheckUserGlobalContributionsLookup {
		return new CheckUserGlobalContributionsLookup(
			$services->getDBLoadBalancerFactory(),
			$services->getExtensionRegistry(),
			$services->getCentralIdLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getMainConfig(),
			$services->getRevisionStore(),
			$services->get( 'CheckUserApiRequestAggregator' ),
			$services->getMainWANObjectCache(),
			$services->getStatsFactory()
		);
	},
	'CheckUserGlobalContributionsPagerFactory' => static function (
		MediaWikiServices $services
	): GlobalContributionsPagerFactory {
		$preferencesFactory = $services->getPreferencesFactory();
		if ( !( $preferencesFactory instanceof GlobalPreferencesFactory ) ) {
			throw new LogicException(
				'Cannot instantiate GlobalContributionsPagerFactory without GlobalPreferences'
			);
		}
		return new GlobalContributionsPagerFactory(
			$services->getLinkRenderer(),
			$services->getLinkBatchFactory(),
			$services->getHookContainer(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getTempUserConfig(),
			$services->getMainConfig(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getCentralIdLookup(),
			$services->get( 'CheckUserGlobalContributionsLookup' ),
			$services->getPermissionManager(),
			$preferencesFactory,
			$services->getDBLoadBalancerFactory(),
			$services->getJobQueueGroup(),
			$services->getUserLinkRenderer(),
			$services->getRevisionStoreFactory(),
			$services->getChangeTagsStoreFactory(),
		);
	},
	'CheckUserHookRunner' => static function (
		MediaWikiServices $services
	): HookRunner {
		return new HookRunner(
			$services->getHookContainer()
		);
	},
	'CheckUserInsert' => static function (
		MediaWikiServices $services
	): CheckUserInsert {
		return new CheckUserInsert(
			new ServiceOptions(
				CheckUserInsert::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getActorStore(),
			$services->get( 'CheckUserUtilityService' ),
			$services->getCommentStore(),
			$services->getHookContainer(),
			$services->getDBLoadBalancerFactory(),
			$services->getContentLanguage(),
			$services->getTempUserConfig(),
			$services->get( 'CheckUserCentralIndexManager' ),
			$services->get( 'UserAgentClientHintsManager' ),
			$services->getJobQueueGroup(),
			$services->getRecentChangeLookup(),
			$services->get( 'SuggestedInvestigationsSignalMatchService' ),
			$services->get( 'CheckUserLogger' )
		);
	},
	'CheckUserIPContributionsPagerFactory' => static function (
		MediaWikiServices $services
	): IPContributionsPagerFactory {
		return new IPContributionsPagerFactory(
			$services->getLinkRenderer(),
			$services->getLinkBatchFactory(),
			$services->getHookContainer(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getTempUserConfig(),
			$services->getMainConfig(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getJobQueueGroup()
		);
	},
	'CheckUserIPRevealManager' => static function (
		MediaWikiServices $services
	): CheckUserIPRevealManager {
		return new CheckUserIPRevealManager(
			new ServiceOptions(
				CheckUserIPRevealManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getTempUserConfig(),
			$services->get( 'CheckUserPermissionManager' )
		);
	},
	'CheckUserLogger' => static function () {
		return LoggerFactory::getInstance( 'CheckUser' );
	},
	'CheckUserLogService' => static function (
		MediaWikiServices $services
	): CheckUserLogService {
		return new CheckUserLogService(
			$services->getDBLoadBalancerFactory(),
			$services->getCommentStore(),
			$services->getCommentFormatter(),
			$services->get( 'CheckUserLogger' ),
			$services->getActorStore(),
			$services->getUserIdentityLookup(),
			new ServiceOptions(
				CheckUserLogService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	'CheckUserLookupUtils' => static function (
		MediaWikiServices $services
	): CheckUserLookupUtils {
		return new CheckUserLookupUtils(
			new ServiceOptions(
				CheckUserLookupUtils::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getRevisionStore(),
			$services->getArchivedRevisionLookup(),
			$services->get( 'CheckUserLogger' )
		);
	},
	'CheckUserPermissionManager' => static function ( MediaWikiServices $services ): CheckUserPermissionManager {
		return new CheckUserPermissionManager(
			$services->getUserOptionsLookup(),
			$services->getSpecialPageFactory(),
			$services->getCentralIdLookup(),
			$services->getUserFactory()
		);
	},
	'CheckUserPreliminaryCheckPagerFactory' => static function (
		MediaWikiServices $services
	): PreliminaryCheckPagerFactory {
		return new PreliminaryCheckPagerFactory(
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$services->getExtensionRegistry(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserPreliminaryCheckService' ),
			$services->getUserFactory()
		);
	},
	'CheckUserPreliminaryCheckService' => static function (
		MediaWikiServices $services
	): PreliminaryCheckService {
		return new PreliminaryCheckService(
			$services->getDBLoadBalancerFactory(),
			$services->getExtensionRegistry(),
			$services->getUserGroupManagerFactory(),
			$services->getDatabaseBlockStoreFactory(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	'CheckUserSuggestedInvestigationsCaseLookup' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsCaseLookupService {
		return new SuggestedInvestigationsCaseLookupService(
			new ServiceOptions(
				SuggestedInvestigationsCaseLookupService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider(),
			$services->get( 'CheckUserLogger' ),
		);
	},
	'CheckUserSuggestedInvestigationsCaseManager' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsCaseManagerService {
		return new SuggestedInvestigationsCaseManagerService(
			new ServiceOptions(
				SuggestedInvestigationsCaseManagerService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' )
		);
	},
	'CheckUserSuggestedInvestigationsInstrumentationClient' => static function (
		MediaWikiServices $services
	): ISuggestedInvestigationsInstrumentationClient {
		// If the EventLogging extension is not installed, then return the
		// no-op instrumentation client to allow callers to call it safely
		if ( !$services->has( 'EventLogging.MetricsClientFactory' ) ) {
			return new NoOpSuggestedInvestigationsInstrumentationClient();
		}

		return new SuggestedInvestigationsInstrumentationClient(
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->getUserFactory(),
			$services->getUserRegistrationLookup(),
			$services->getUserEditTracker(),
			$services->getUserGroupManager(),
			$services->getCentralIdLookup(),
			$services->getExtensionRegistry(),
			$services->get( 'EventLogging.MetricsClientFactory' )
		);
	},
	'CheckUserSuggestedInvestigationsPagerFactory' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsPagerFactory {
		$centralAuthEditCounter = null;
		if ( $services->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$centralAuthEditCounter = CentralAuthServices::getEditCounter( $services );
		}
		return new SuggestedInvestigationsPagerFactory(
			$services->getLinkRenderer(),
			$services->getLinkBatchFactory(),
			$services->getHookContainer(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getConnectionProvider(),
			$services->getUserEditTracker(),
			$services->getSpecialPageFactory(),
			$services->getUserIdentityLookup(),
			$centralAuthEditCounter
		);
	},
	'CheckUserTemporaryAccountAutoRevealLookup' => static function (
		MediaWikiServices $services
	): CheckUserTemporaryAccountAutoRevealLookup {
		return new CheckUserTemporaryAccountAutoRevealLookup(
			new ServiceOptions(
				CheckUserTemporaryAccountAutoRevealLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getPreferencesFactory(),
			$services->get( 'CheckUserPermissionManager' )
		);
	},
	'CheckUserTemporaryAccountLoggerFactory' => static function (
		MediaWikiServices $services
	): TemporaryAccountLoggerFactory {
		return new TemporaryAccountLoggerFactory(
			$services->getActorStore(),
			$services->get( 'CheckUserLogger' ),
			$services->getDBLoadBalancerFactory(),
			$services->getTitleFactory()
		);
	},
	'CheckUserTemporaryAccountsByIPLookup' => static function (
		MediaWikiServices $services
	): CheckUserTemporaryAccountsByIPLookup {
		return new CheckUserTemporaryAccountsByIPLookup(
			new ServiceOptions(
				CheckUserTemporaryAccountsByIPLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getJobQueueGroup(),
			$services->getTempUserConfig(),
			$services->getUserFactory(),
			$services->getPermissionManager(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserOptionsLookup(),
			$services->get( 'CheckUserLookupUtils' )
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
			$services->getLinkBatchFactory(),
			$services->get( 'CheckUserLogger' )
		);
	},
	'CheckUserTimelineRowFormatterFactory' => static function (
		MediaWikiServices $services
	): TimelineRowFormatterFactory {
		return new TimelineRowFormatterFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getTitleFormatter(),
			$services->getSpecialPageFactory(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getCommentStore(),
			$services->getLogFormatterFactory()
		);
	},
	'CheckUserTimelineService' => static function ( MediaWikiServices $services ): TimelineService {
		return new TimelineService(
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getTempUserConfig(),
			new ServiceOptions(
				TimelineService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
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
	'CheckUserUserInfoCardService' => static function (
		MediaWikiServices $services
	): CheckUserUserInfoCardService {
		$extensionRegistry = $services->getExtensionRegistry();
		$userImpactLookup = null;
		if ( $extensionRegistry->isLoaded( 'GrowthExperiments' ) ) {
			$userImpactLookup = $services->getService( 'GrowthExperimentsUserImpactLookup' );
		}
		$globalContributionsLookup = null;
		if ( $extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$globalContributionsLookup = $services->get( 'CheckUserGlobalContributionsLookup' );
		}
		return new CheckUserUserInfoCardService(
			$userImpactLookup,
			$extensionRegistry,
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager(),
			$globalContributionsLookup,
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getUserEditTracker(),
			$services->get( 'CheckUserTemporaryAccountsByIPLookup' ),
			new DerivativeContext( RequestContext::getMain() ),
			$services->getTitleFactory(),
			$services->getGenderCache(),
			$services->getTempUserConfig(),
			new ServiceOptions(
				CheckUserUserInfoCardService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getCentralIdLookup()
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
	'SuggestedInvestigationsSignalMatchService' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsSignalMatchService {
		return new SuggestedInvestigationsSignalMatchService(
			new ServiceOptions(
				SuggestedInvestigationsSignalMatchService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( 'CheckUserHookRunner' ),
			$services->get( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$services->get( 'CheckUserSuggestedInvestigationsCaseManager' ),
			$services->get( 'CheckUserLogger' ),
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
	'UserAgentClientHintsLookup' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsLookup {
		return new UserAgentClientHintsLookup(
			$services->getConnectionProvider()->getReplicaDatabase()
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
			$services->get( 'CheckUserLogger' )
		);
	},
];
// @codeCoverageIgnoreEnd
