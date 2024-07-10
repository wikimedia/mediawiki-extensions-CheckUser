<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Config\Config;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\User\UserIdentityLookup;

/**
 * Hooks into several hook handlers to create private checkuser events when certain actions occur.
 */
class CheckUserPrivateEventsHandler implements UserLogoutCompleteHook {

	private CheckUserInsert $checkUserInsert;
	private Config $config;
	private UserIdentityLookup $userIdentityLookup;

	public function __construct(
		CheckUserInsert $checkUserInsert,
		Config $config,
		UserIdentityLookup $userIdentityLookup
	) {
		$this->checkUserInsert = $checkUserInsert;
		$this->config = $config;
		$this->userIdentityLookup = $userIdentityLookup;
	}

	/**
	 * Creates a private checkuser event when a user logs out.
	 *
	 * @inheritDoc
	 */
	public function onUserLogoutComplete( $user, &$inject_html, $oldName ) {
		if ( !$this->config->get( 'CheckUserLogLogins' ) ) {
			// Treat the log logins config as also applying to logging logouts.
			return;
		}

		$performer = $this->userIdentityLookup->getUserIdentityByName( $oldName );
		if ( $performer === null ) {
			return;
		}

		$this->checkUserInsert->insertIntoCuPrivateEventTable(
			[
				'cupe_namespace'  => NS_USER,
				'cupe_title'      => $oldName,
				// The following messages are generated here:
				// * logentry-checkuser-private-event-user-logout
				'cupe_log_action' => 'user-logout',
			],
			__METHOD__,
			$performer
		);
	}
}
