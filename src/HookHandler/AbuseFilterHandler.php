<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterBuilderHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCanViewProtectedVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCustomProtectedVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterGenerateAccountCreationVarsHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterGenerateUserVarsHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterInterceptVariableHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterProtectedVariablesDeleteLogEntriesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterProtectedVarsAccessLoggerHook;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Extension\CheckUser\Services\CheckUserLogService;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Permissions\Authority;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use TypeError;
use Wikimedia\IPUtils;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
// For onAbuseFilter_builder, onAbuseFilter_generateUserVars and onAbuseFilter_interceptVariable

class AbuseFilterHandler implements
	AbuseFilterCustomProtectedVariablesHook,
	AbuseFilterProtectedVarsAccessLoggerHook,
	AbuseFilterCanViewProtectedVariablesHook,
	AbuseFilterBuilderHook,
	AbuseFilterGenerateUserVarsHook,
	AbuseFilterGenerateAccountCreationVarsHook,
	AbuseFilterInterceptVariableHook,
	AbuseFilterProtectedVariablesDeleteLogEntriesHook
	{

	/**
	 * Maps the Client Hints CheckUser variables to the associated {@link ClientHintsData} property
	 */
	private const array CLIENT_HINTS_VARIABLES = [
		'checkuser_client_hints_architecture' => 'architecture',
		'checkuser_client_hints_bitness' => 'bitness',
		'checkuser_client_hints_brands' => 'brands',
		'checkuser_client_hints_form_factors' => 'formFactor',
		'checkuser_client_hints_full_version_list' => 'fullVersionList',
		'checkuser_client_hints_mobile' => 'mobile',
		'checkuser_client_hints_model' => 'model',
		'checkuser_client_hints_platform' => 'platform',
		'checkuser_client_hints_platform_version' => 'platformVersion',
		'checkuser_client_hints_wow64' => 'woW64',
	];

	/**
	 * Maps the CheckUser variables for headers set by the WMF CDN to the associated
	 * {@link ClientHintsData} property
	 */
	private const array EDGE_HEADER_VARIABLES = [
		'checkuser_is_browser' => 'isBrowser',
		'checkuser_ja3n' => 'ja3n',
		'checkuser_ja4h' => 'ja4h',
	];

	/**
	 * The number of seconds after which a duplicate Special:CheckUserLog entry can be
	 * created for a debounced log. Matches the AbuseFilter default.
	 */
	private const int LOG_DEBOUNCE_DELAY = 24 * 60 * 60;

	public function __construct(
		private readonly TemporaryAccountLoggerFactory $loggerFactory,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly TempUserConfig $tempUserConfig,
		private readonly Config $config,
		private readonly CheckUserLogService $checkUserLogService,
		private readonly UserIdentityLookup $userIdentityLookup,
	) {
	}

	/**
	 * Because CheckUser wants to define additional restrictions on accessing the
	 * user_unnamed_ip variable and the CheckUser protected variables, we should ensure
	 * that these variables are always protected to allow these restrictions to take effect.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterCustomProtectedVariables( array &$variables ) {
		$variables[] = 'user_unnamed_ip';
		$variables = array_merge( $variables, $this->getVariables() );
	}

	/**
	 * A filter match for a CheckUser protected variable can reveal private data about the user, so
	 * the abuse_filter_log rows for these matches should be deleted when the variable values are purged.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterProtectedVariablesDeleteLogEntries( array &$variables ): void {
		$variables = array_merge( $variables, $this->getVariables() );
	}

	/**
	 * Diverts access logs for protected variables which CheckUser handles away from the AbuseFilter log:
	 * * user_unnamed_ip goes to the temporary account log, to centralise IP view logs
	 * * The CheckUser protected variables go to Special:CheckUserLog, to centralise logs of access
	 *   to CheckUser data
	 *
	 * The handled variables are removed from the parameters, so that AbuseFilter only logs the others.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterLogProtectedVariableValueAccess(
		UserIdentity $performer,
		string $target,
		string $action,
		bool $shouldDebounce,
		int $timestamp,
		array &$params
	) {
		if ( !isset( $params['variables'] ) ) {
			return true;
		}

		// Use the AbuseFilter specific log action with an "af-" prefix, so that it is
		// clear that the log entry is sourced from AbuseFilter.
		// Possible values:
		//  - af-view-protected-var-value
		$externalAction = 'af-' . $action;

		$temporaryAccountVariables = array_values( array_intersect( $params['variables'], [ 'user_unnamed_ip' ] ) );
		if ( $temporaryAccountVariables ) {
			$this->loggerFactory->getLogger()->logFromExternal(
				$performer,
				$target,
				$externalAction,
				[ 'variables' => $temporaryAccountVariables ] + $params,
				$shouldDebounce,
				$timestamp
			);
		}

		$checkUserVariables = array_values( array_intersect( $params['variables'], $this->getVariables() ) );
		if ( $checkUserVariables ) {
			$this->logCheckUserVariablesAccess( $performer, $target, $externalAction, $shouldDebounce, $timestamp );
		}

		$params['variables'] = array_diff(
			$params['variables'],
			$temporaryAccountVariables,
			$checkUserVariables
		);

		// Allow AbuseFilter to continue logging if there are remaining variables
		return true;
	}

	/**
	 * Adds a Special:CheckUserLog entry for a view of CheckUser protected variable values.
	 */
	private function logCheckUserVariablesAccess(
		UserIdentity $performer,
		string $target,
		string $logType,
		bool $shouldDebounce,
		int $timestamp
	): void {
		if (
			$shouldDebounce &&
			$this->checkUserLogService->hasLogEntrySince(
				$performer,
				$logType,
				$target,
				$timestamp - self::LOG_DEBOUNCE_DELAY
			)
		) {
			return;
		}

		if ( IPUtils::isIPAddress( $target ) ) {
			$targetType = 'ip';
			$targetId = 0;
		} else {
			// The target can be an account which does not exist, such as a blocked account creation
			$targetType = 'user';
			$targetId = $this->userIdentityLookup->getUserIdentityByName( $target )?->getId() ?? 0;
		}

		$this->checkUserLogService->addLogEntry( $performer, $logType, $targetType, $target, '', $targetId );
	}

	/**
	 * Restrict access to seeing filters or logs associated with filters which use:
	 * * The user_unnamed_ip variable to those who have the ability to see Temporary Account IP
	 *   addresses (if the wiki has temporary accounts known or enabled)
	 * * Any CheckUser protected variable to those who have the `checkuser` right
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterCanViewProtectedVariables(
		Authority $performer,
		array $variables,
		AbuseFilterPermissionStatus $status
	): void {
		if ( in_array( 'user_unnamed_ip', $variables ) && $this->tempUserConfig->isKnown() ) {
			$this->mergeCheckUserPermissionStatus(
				$status,
				$this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses( $performer )
			);
		}

		if ( array_intersect( $this->getVariables(), $variables ) ) {
			$this->mergeCheckUserPermissionStatus(
				$status,
				$this->checkUserPermissionManager->canAccessCheckUserAbuseFilterVariables( $performer )
			);
		}
	}

	/**
	 * Copies the result of a CheckUser permission check into the given AbuseFilter permission status.
	 */
	private function mergeCheckUserPermissionStatus(
		AbuseFilterPermissionStatus $status,
		CheckUserPermissionStatus $checkUserPermissionStatus
	): void {
		$permission = $checkUserPermissionStatus->getPermission();
		if ( $permission ) {
			$status->setPermission( $permission );
		}

		$block = $checkUserPermissionStatus->getBlock();
		if ( $block ) {
			$status->setBlock( $block );
		}

		$status->merge( $checkUserPermissionStatus );
	}

	/**
	 * Registers the CheckUser protected variables so that they can be selected in the filter editor.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilter_builder( array &$realValues ): void {
		foreach ( $this->getEnabledVariables() as $variable ) {
			// Uses:
			// * abusefilter-edit-builder-vars-checkuser-user-ip
			// * abusefilter-edit-builder-vars-checkuser-user-agent
			// * abusefilter-edit-builder-vars-checkuser-client-hints-*
			// * abusefilter-edit-builder-vars-checkuser-is-browser
			// * abusefilter-edit-builder-vars-checkuser-ja3n
			// * abusefilter-edit-builder-vars-checkuser-ja4h
			$realValues['vars'][$variable] = $this->getMethodForVariable( $variable );
		}
	}

	/**
	 * Defines the CheckUser protected variables for the user performing the action.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilter_generateUserVars( VariableHolder $vars, User $user, ?RecentChange $rc ): void {
		$this->setCheckUserProtectedVariables( $vars, $rc );
	}

	/**
	 * Defines the CheckUser protected variables for account creations, which includes those
	 * made while logged out.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterGenerateAccountCreationVars(
		VariableHolder $vars,
		UserIdentity $creator,
		UserIdentity $createdUser,
		bool $autocreate,
		?RecentChange $rc
	): void {
		$this->setCheckUserProtectedVariables( $vars, $rc );
	}

	/**
	 * Lazy loads the CheckUser protected variables, to avoid computing them when no filter uses them.
	 */
	private function setCheckUserProtectedVariables( VariableHolder $vars, ?RecentChange $rc ): void {
		foreach ( $this->getEnabledVariables() as $variable ) {
			if ( !$vars->varIsSet( $variable ) ) {
				$vars->setLazyLoadVar(
					$variable,
					$this->getMethodForVariable( $variable ),
					[ 'rc' => $rc ]
				);
			}
		}
	}

	/**
	 * Computes the value of the CheckUser protected variables.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilter_interceptVariable(
		string $method,
		VariableHolder $vars,
		array $parameters,
		&$result
	): bool {
		$variable = $this->getVariableForMethod( $method );
		if ( $variable === null ) {
			return true;
		}

		// Don't generate these variables for previous actions, as only the IP would be supported for this
		// and we also don't want to allow the user to use AbuseFilter as another way to perform
		// CU requests on previous actions.
		if ( $parameters['rc'] !== null ) {
			$result = null;
			return false;
		}

		$request = RequestContext::getMain()->getRequest();
		$userAgent = $request->getHeader( 'User-Agent' );
		$result = match ( $variable ) {
			'checkuser_user_ip' => $request->getIP(),
			'checkuser_user_agent' => $userAgent ? mb_scrub( $userAgent ) : null,
			default => $this->getClientHintsValue(
				( self::CLIENT_HINTS_VARIABLES + self::EDGE_HEADER_VARIABLES )[$variable],
				$request
			),
		};

		return false;
	}

	/**
	 * Returns the names of all of the CheckUser protected variables, regardless of whether they are enabled
	 *
	 * @return string[]
	 */
	private function getVariables(): array {
		return array_merge(
			[ 'checkuser_user_ip', 'checkuser_user_agent' ],
			array_keys( self::CLIENT_HINTS_VARIABLES ),
			array_keys( self::EDGE_HEADER_VARIABLES )
		);
	}

	/**
	 * Returns the names of the CheckUser AbuseFilter variables which filters can use, based on config
	 *
	 * @return string[]
	 */
	private function getEnabledVariables(): array {
		if ( !$this->config->get( 'CheckUserAbuseFilterVariablesEnabled' ) ) {
			return [];
		}

		$variables = [ 'checkuser_user_ip', 'checkuser_user_agent' ];

		if ( $this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			$variables = array_merge( $variables, array_keys( self::CLIENT_HINTS_VARIABLES ) );
		}

		if ( $this->config->get( 'CheckUserAbuseFilterEdgeHeaderVariablesEnabled' ) ) {
			$variables = array_merge( $variables, array_keys( self::EDGE_HEADER_VARIABLES ) );
		}

		return $variables;
	}

	/**
	 * Returns the AbuseFilter method name used to lazy load the given variable.
	 */
	private function getMethodForVariable( string $variable ): string {
		return str_replace( '_', '-', $variable );
	}

	/**
	 * Returns the variable for a given AbuseFilter lazy load method name, or null if the
	 * method is not for one of the variables defined by this class.
	 */
	private function getVariableForMethod( string $method ): ?string {
		$variable = str_replace( '-', '_', $method );
		return in_array( $variable, $this->getVariables(), true ) ? $variable : null;
	}

	/**
	 * Gets the value of a Client Hints property. Data which client side code sent with the
	 * request is preferred over the HTTP headers, as it may hold high entropy data.
	 *
	 * @return string|string[]|bool|int|null
	 */
	private function getClientHintsValue( string $property, WebRequest $request ): array|bool|int|string|null {
		$sources = [];
		if ( $this->config->get( 'CheckUserClientHintsInEditRequest' ) && $request->wasPosted() ) {
			$sources[] = ClientHintsData::newFromRequestField( $request );
		}
		try {
			$sources[] = ClientHintsData::newFromRequestHeaders( $request, [ $property ] );
		} catch ( TypeError ) {
			// Ignore invalid header data, as CheckUser does when it stores Client Hints data
		}

		foreach ( array_filter( $sources ) as $clientHintsData ) {
			$value = $clientHintsData->jsonSerialize()[$property];

			// Treat empty values as undefined (so null if no source has it), but allow false as some properties
			// are booleans
			if ( $value === null || $value === '' || $value === [] ) {
				continue;
			}

			if ( is_array( $value ) ) {
				// brands and fullVersionList store their values as multi-dimensional arrays with keys,
				// which AbuseFilter doesn't support. Instead represent as a flat array of values as shown
				// in Special:CheckUser.
				$rows = array_filter(
					$clientHintsData->toDatabaseRows(),
					static fn ( array $row ) => $row['uach_name'] === $property
				);
				return array_map( mb_scrub( ... ), array_values( array_column( $rows, 'uach_value' ) ) );
			}

			return is_string( $value ) ? mb_scrub( $value ) : $value;
		}

		return null;
	}
}
