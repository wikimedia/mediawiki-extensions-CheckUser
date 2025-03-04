<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCustomProtectedVariablesHook;

class AbuseFilterHandler implements AbuseFilterCustomProtectedVariablesHook {
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
}
