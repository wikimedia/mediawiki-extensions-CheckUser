<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\Hook\LogEventsListLineEndingHook;
use MediaWiki\User\UserNameUtils;

class LogEventsListHandler implements LogEventsListLineEndingHook {

	private UserNameUtils $userNameUtils;
	private Config $config;
	private CheckUserPermissionManager $checkUserPermissionManager;

	public function __construct(
		UserNameUtils $userNameUtils,
		Config $config,
		CheckUserPermissionManager $checkUserPermissionManager
	) {
		$this->userNameUtils = $userNameUtils;
		$this->config = $config;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
	}

	/** @inheritDoc */
	public function onLogEventsListLineEnding( $page, &$ret, $entry, &$classes, &$attribs ) {
		// Only add the "Show IP" button next to log entries performed by temporary accounts.
		if ( !$this->userNameUtils->isTemp( $entry->getPerformerIdentity()->getName() ) ) {
			return;
		}

		// If the title is not a special page that is supported, then we can't add the "Show IP" button.
		// We don't currently support non-special pages as the page output could be cached, which won't
		// work as the cache is not per-user.
		if (
			!( $page->getTitle() && $page->getTitle()->isSpecialPage() ) ||
			in_array(
				$page->getTitle()->getDBkey(),
				$this->config->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' )
			)
		) {
			return;
		}

		// No need for the "Show IP" button if the user cannot use the button.
		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$page->getAuthority()
		);
		if ( !$permStatus->isGood() ) {
			return;
		}

		// Indicate that the log line supports IP reveal and should have a "Show IP" button shown next to
		// the performer of the log entry (which should be the first temporary account user link).
		$classes[] = 'ext-checkuser-log-line-supports-ip-reveal';
	}
}
