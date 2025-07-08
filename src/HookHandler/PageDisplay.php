<?php

namespace MediaWiki\CheckUser\HookHandler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\CheckUser\Services\CheckUserIPRevealManager;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\IPInfo\HookHandler\AbstractPreferencesHandler;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Skin;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;

class PageDisplay implements BeforePageDisplayHook {
	private Config $config;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private CheckUserIPRevealManager $checkUserIPRevealManager;
	private UserOptionsLookup $userOptionsLookup;
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;
	private UserIdentityUtils $userIdentityUtils;
	private PreferencesFactory $preferencesFactory;

	public function __construct(
		Config $config,
		CheckUserPermissionManager $checkUserPermissionManager,
		CheckUserIPRevealManager $checkUserIPRevealManager,
		TempUserConfig $tempUserConfig,
		UserOptionsLookup $userOptionsLookup,
		ExtensionRegistry $extensionRegistry,
		UserIdentityUtils $userIdentityUtils,
		PreferencesFactory $preferencesFactory
	) {
		$this->config = $config;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->checkUserIPRevealManager = $checkUserIPRevealManager;
		$this->tempUserConfig = $tempUserConfig;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->preferencesFactory = $preferencesFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->loadIPInfoGlobalContributionsLink( $out, $skin );
		$this->addUserInfoCardConfigVars( $out );
		$this->addTemporaryAccountsOnboardingDialog( $out );
		$this->addIPRevealButtons( $out );
	}

	/**
	 * Add IP reveal buttons to certain pages if the user has the necessary permissions.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	private function addIPRevealButtons( OutputPage $out ) {
		if ( !$this->checkUserIPRevealManager->shouldAddIPRevealButtons( $out ) ) {
			return;
		}

		// Config needed for a js-added message on Special:Block
		$title = $out->getTitle();
		if ( $title->isSpecial( 'Block' ) ) {
			$out->addJSConfigVars( [
				'wgCUDMaxAge' => $this->config->get( 'CUDMaxAge' )
			] );
		}

		$out->addModules( 'ext.checkUser.tempAccounts' );
		$out->addModuleStyles( 'ext.checkUser.styles' );

		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$out->getAuthority()
		);
		$out->addJSConfigVars( [
			'wgCheckUserIsPerformerBlocked' => $permStatus->getBlock() !== null,
			'wgCheckUserTemporaryAccountMaxAge' => $this->config->get( 'CheckUserTemporaryAccountMaxAge' ),
			'wgCheckUserSpecialPagesWithoutIPRevealButtons' =>
				$this->config->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' ),
		] );
	}

	/**
	 * Export JS config variables for UserInfoCard to determine permissions.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	private function addUserInfoCardConfigVars( OutputPage $out ) {
		$performer = $out->getUser();
		if ( !$this->userOptionsLookup->getBoolOption( $performer, Preferences::ENABLE_USER_INFO_CARD ) ) {
			return;
		}

		if ( $performer->isNamed() ) {
			$out->addJsConfigVars(
				'wgCheckUserCanViewCheckUserLog',
				$out->getAuthority()->isAllowed( 'checkuser-log' )
			);
			$out->addJsConfigVars(
				'wgCheckUserCanBlock',
				$out->getAuthority()->isAllowed( 'block' )
			);
			$out->addJsConfigVars(
				'wgCheckUserCanPerformCheckUser',
				$out->getAuthority()->isAllowed( 'checkuser' )
			);
		}
	}

	/**
	 * Returns whether the 'ipinfo-use-agreement' preference is enabled for the
	 * given user. If the GlobalPreferences extension is installed, then the global value
	 * of the preference is returned ignoring any local override.
	 *
	 * Assumes that IPInfo is loaded, so caller should check this before calling.
	 *
	 * @param UserIdentity $user The user to get the preference value for
	 * @return bool Whether the preference is enabled
	 */
	private function getIPInfoAgreementPreferenceValue( UserIdentity $user ): bool {
		if (
			$this->extensionRegistry->isLoaded( 'GlobalPreferences' ) &&
			$this->preferencesFactory instanceof GlobalPreferencesFactory
		) {
			// If GlobalPreferences is installed, then we want to use the value from
			// there over the local preference. This is because we will set the value globally
			// in the dialog if that is possible, so want to have it unchecked if locally
			// enabled but globally disabled.
			$globalPreferences = $this->preferencesFactory->getGlobalPreferencesValues( $user );
			if ( $globalPreferences !== false ) {
				return isset( $globalPreferences[AbstractPreferencesHandler::IPINFO_USE_AGREEMENT] ) &&
					$globalPreferences[AbstractPreferencesHandler::IPINFO_USE_AGREEMENT];
			}
		}
		return $this->userOptionsLookup->getBoolOption( $user, AbstractPreferencesHandler::IPINFO_USE_AGREEMENT );
	}

	/**
	 * Show the temporary accounts onboarding dialog if the user has never seen the dialog before,
	 * has permissions to reveal IPs (ignoring the preference check) and the user is
	 * viewing any of the history page, Special:Watchlist, or Special:RecentChanges.
	 * We show the dialog even if the acting user is blocked
	 *
	 * @return void
	 */
	private function addTemporaryAccountsOnboardingDialog( OutputPage $out ) {
		if ( !$this->tempUserConfig->isKnown() ) {
			return;
		}

		$action = $out->getRequest()->getVal( 'action' );
		$title = $out->getTitle();
		if (
			$action !== 'history' &&
			!$title->isSpecial( 'Watchlist' ) &&
			!$title->isSpecial( 'Recentchanges' )
		) {
			return;
		}

		if ( !$out->getAuthority()->isAllowedAny(
			'checkuser-temporary-account-no-preference', 'checkuser-temporary-account'
		) ) {
			return;
		}

		$userHasSeenDialog = $this->userOptionsLookup->getBoolOption(
			$out->getUser(), Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN
		);
		if ( !$userHasSeenDialog ) {
			$out->addHtml( '<div id="ext-checkuser-tempaccountsonboarding-app"></div>' );
			$out->addModules( 'ext.checkUser.tempAccountOnboarding' );
			$out->addModuleStyles( 'ext.checkUser.styles' );
			$out->addModuleStyles( 'ext.checkUser.images' );

			// Allow the dialog to hide the IPInfo step and/or IPInfo preference depending on the
			// rights of the user and if IPInfo is installed.
			$ipInfoLoaded = $this->extensionRegistry->isLoaded( 'IPInfo' );
			$out->addJsConfigVars( [
				'wgCheckUserIPInfoExtensionLoaded' => $ipInfoLoaded,
				'wgCheckUserUserHasIPInfoRight' => $ipInfoLoaded &&
					$out->getAuthority()->isAllowed( 'ipinfo' ),
				'wgCheckUserIPInfoPreferenceChecked' => $ipInfoLoaded &&
					$this->getIPInfoAgreementPreferenceValue( $out->getUser() ),
				'wgCheckUserGlobalPreferencesExtensionLoaded' =>
					$this->extensionRegistry->isLoaded( 'GlobalPreferences' ),
			] );
		}
	}

	/**
	 * If IPInfo is enabled and the user has access to Special:GlobalContributions,
	 * enable the link to Special:GC on IPInfo's infobox widget on pages where it's loaded
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void
	 */
	private function loadIPInfoGlobalContributionsLink( $out, $skin ): void {
		if ( !$this->extensionRegistry->isLoaded( 'IPInfo' ) ) {
			return;
		}

		// If no relevant user exists or is a registered account, return early since IPInfo only
		// supports IPs and temporary accounts
		$relevantUser = $skin->getRelevantUser();
		if ( !$relevantUser || $this->userIdentityUtils->isNamed( $relevantUser ) ) {
			return;
		}

		// If page isn't one of IPInfo's supported pages, return early
		$title = $out->getTitle();
		if (
			!$title->isSpecial( 'Contributions' ) &&
			!$title->isSpecial( 'DeletedContributions' ) &&
			!$title->isSpecial( 'IPContributions' )
		) {
			return;
		}

		// If user cannot access Special:GlobalContributions, return early
		if (
			!$this->checkUserPermissionManager->canAccessUserGlobalContributions(
				$out->getAuthority(),
				$relevantUser->getName()
			)->isGood()
		) {
			return;
		}

		// Relevant user is an IP or temporary account, page is one where IPInfo's infobox is
		// expected to load, and accessing user has permission to view Special:GlobalContributions.
		// Add module to load Special:GC link to IPInfo infobox.
		$out->addModules( 'ext.checkUser.ipInfo.hooks' );
	}
}
