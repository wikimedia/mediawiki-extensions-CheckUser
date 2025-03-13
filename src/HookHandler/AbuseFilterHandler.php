<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCustomProtectedVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterProtectedVarsAccessLoggerHook;
use MediaWiki\User\UserIdentity;

class AbuseFilterHandler implements
	AbuseFilterCustomProtectedVariablesHook,
	AbuseFilterProtectedVarsAccessLoggerHook
	{

	private TemporaryAccountLoggerFactory $loggerFactory;

	public function __construct(
		TemporaryAccountLoggerFactory $loggerFactory
	) {
		$this->loggerFactory = $loggerFactory;
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
	 * Whenever AbuseFilter logs access to protected variables, this should instead
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
		// Use the AbuseFilter specific log action as a message key along
		// with an indicator that it's being sourced from AbuseFilter so that
		// it's clearer that this is an external log
		$action = 'af-' . $action;

		// Possible values:
		//  - af-change-access-enable
		//  - af-change-access-disable
		//  - af-view-protected-var-value
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
}
