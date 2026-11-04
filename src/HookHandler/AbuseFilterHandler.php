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
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterProtectedVarsAccessLoggerHook;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Permissions\Authority;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
// For onAbuseFilter_builder, onAbuseFilter_generateUserVars and onAbuseFilter_interceptVariable

class AbuseFilterHandler implements
	AbuseFilterCustomProtectedVariablesHook,
	AbuseFilterProtectedVarsAccessLoggerHook,
	AbuseFilterCanViewProtectedVariablesHook,
	AbuseFilterBuilderHook,
	AbuseFilterGenerateUserVarsHook,
	AbuseFilterGenerateAccountCreationVarsHook,
	AbuseFilterInterceptVariableHook
	{

	public function __construct(
		private readonly TemporaryAccountLoggerFactory $loggerFactory,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly TempUserConfig $tempUserConfig,
		private readonly Config $config,
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
	 * Whenever AbuseFilter logs access to the user_unnamed_ip protected variable, this should instead
	 * be logged to CheckUser in order to centralize IP view logs. Abort the hook
	 * afterwards so that the event is not double-logged.
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

		$handledVariables = [ 'user_unnamed_ip' ];
		$variablesOfInterest = array_intersect( $handledVariables, $params['variables'] );

		if ( count( $variablesOfInterest ) === 0 ) {
			// No variables of interest, continue with the default logging
			return true;
		}

		// Use the AbuseFilter specific log action as a message key along
		// with an indicator that it's being sourced from AbuseFilter so that
		// it's clearer that this is an external log.
		// Possible values:
		//  - af-view-protected-var-value
		$action = 'af-' . $action;

		// Copy the parameters but with only the subset of variables that we're interested in
		$newParams = $params;
		$newParams['variables'] = $variablesOfInterest;

		// Keep only the unhandled variables in the log entry handled by AbuseFilter
		$params['variables'] = array_diff( $params['variables'], $handledVariables );

		$logger = $this->loggerFactory->getLogger();
		$logger->logFromExternal(
			$performer,
			$target,
			$action,
			$newParams,
			$shouldDebounce,
			$timestamp
		);

		// Allow AbuseFilter to continue logging if there are remaining variables
		return true;
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
		if ( !$this->config->get( 'CheckUserAbuseFilterVariablesEnabled' ) ) {
			return;
		}

		foreach ( $this->getVariables() as $variable ) {
			// Uses:
			// * abusefilter-edit-builder-vars-checkuser-user-ip
			// * abusefilter-edit-builder-vars-checkuser-user-agent
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
		if ( !$this->config->get( 'CheckUserAbuseFilterVariablesEnabled' ) ) {
			return;
		}

		foreach ( $this->getVariables() as $variable ) {
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
		};

		return false;
	}

	/**
	 * Returns the names of all of the CheckUser protected variables.
	 *
	 * @return string[]
	 */
	private function getVariables(): array {
		return [ 'checkuser_user_ip', 'checkuser_user_agent' ];
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
}
