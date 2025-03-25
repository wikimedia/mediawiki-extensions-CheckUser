<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCanViewProtectedVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCanViewProtectedVariableValuesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCustomProtectedVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterProtectedVarsAccessLoggerHook;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;

class AbuseFilterHandler implements
	AbuseFilterCustomProtectedVariablesHook,
	AbuseFilterProtectedVarsAccessLoggerHook,
	AbuseFilterCanViewProtectedVariablesHook,
	AbuseFilterCanViewProtectedVariableValuesHook
	{

	private TemporaryAccountLoggerFactory $loggerFactory;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private TempUserConfig $tempUserConfig;

	public function __construct(
		TemporaryAccountLoggerFactory $loggerFactory,
		CheckUserPermissionManager $checkUserPermissionManager,
		TempUserConfig $tempUserConfig
	) {
		$this->loggerFactory = $loggerFactory;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->tempUserConfig = $tempUserConfig;
	}

	/**
	 * Because CheckUser wants to define additional restrictions on accessing the
	 * user_unnamed_ip variable, we should ensure that the variable is always
	 * protected to allow these restrictions to take effect.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterCustomProtectedVariables( array &$variables ) {
		$variables[] = 'user_unnamed_ip';
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
		array $params
	) {
		// Only divert logs for protected variable value access when the variables included the user_unnamed_ip
		// variable.
		if ( isset( $params['variables'] ) && !in_array( 'user_unnamed_ip', $params['variables'] ) ) {
			return true;
		}

		// Use the AbuseFilter specific log action as a message key along
		// with an indicator that it's being sourced from AbuseFilter so that
		// it's clearer that this is an external log.
		// Possible values:
		//  - af-view-protected-var-value
		$action = 'af-' . $action;

		$logger = $this->loggerFactory->getLogger();
		$logger->logFromExternal(
			$performer,
			$target,
			$action,
			$params,
			$shouldDebounce,
			$timestamp
		);

		// Abort further AF logging of this action
		return false;
	}

	/**
	 * Restrict access to seeing the value of the AbuseFilter user_unnamed_ip variable to those who
	 * have the ability to see Temporary Account IP addresses if the wiki has temporary accounts
	 * known or enabled.
	 *
	 * This is done to prevent users without IP reveal access bypassing the restriction through
	 * AbuseFilter and then seeing the IP addresses of temporary accounts.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterCanViewProtectedVariableValues(
		Authority $performer, array $variables, AbuseFilterPermissionStatus $status
	): void {
		$this->handleProtectedVariablesAccessCall( $performer, $variables, $status );
	}

	/**
	 * Restrict access to seeing filters which use the AbuseFilter user_unnamed_ip variable to those who
	 * have the ability to see Temporary Account IP addresses if the wiki has temporary accounts
	 * known or enabled.
	 *
	 * This is done to prevent users who do not have Temporary account IP address access from seeing
	 * IP addresses or ranges in filters where they are attached to a specific set of temporary
	 * accounts (such as an "LTA" filter or a filter which tracks edits from specific IP
	 * addresses associated with an organisation).
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterCanViewProtectedVariables(
		Authority $performer, array $variables, AbuseFilterPermissionStatus $status
	): void {
		$this->handleProtectedVariablesAccessCall( $performer, $variables, $status );
	}

	/**
	 * Handles both the AbuseFilterCanViewProtectedVariables and AbuseFilterCanViewProtectedVariableValues
	 * hooks to enforce that users with IP reveal access can only see the user_unnamed_ip AbuseFilter
	 * protected variable.
	 *
	 * @param Authority $performer
	 * @param array $variables
	 * @param AbuseFilterPermissionStatus $status
	 */
	private function handleProtectedVariablesAccessCall(
		Authority $performer,
		array $variables,
		AbuseFilterPermissionStatus $status
	): void {
		if ( !in_array( 'user_unnamed_ip', $variables ) || !$this->tempUserConfig->isKnown() ) {
			return;
		}

		$checkUserPermissionStatus = $this->checkUserPermissionManager
			->canAccessTemporaryAccountIPAddresses( $performer );

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
}
