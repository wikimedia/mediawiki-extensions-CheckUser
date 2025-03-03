<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use Skin;

/**
 * Adds a link to users' GlobalContributions pages on user pages.
 */
class SidebarLinksHandler implements SidebarBeforeOutputHook {
	/**
	 * Keys used to identify the new links added to $sidebar['TOOLBOX'].
	 */
	private const GLOBAL_CONTRIBUTIONS_KEY = 'global-contributions';
	private const IP_AUTO_REVEAL_KEY = 'checkuser-ip-auto-reveal';

	private Config $config;
	private CheckUserPermissionManager $permissionManager;

	public function __construct(
		Config $config,
		CheckUserPermissionManager $checkUserPermissionManager
	) {
		$this->config = $config;
		$this->permissionManager = $checkUserPermissionManager;
	}

	/** @inheritDoc */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$this->addGlobalContributions( $skin, $sidebar );
		$this->addIPAutoReveal( $skin, $sidebar );
	}

	/**
	 * Adds a link to Special:GlobalContributions to the sidebar of user pages.
	 *
	 * Modifies the sidebar before it is output by skins in order to add a link
	 * pointing to the Special:GlobalContributions page for the user the current
	 * user page belongs to.
	 *
	 * @param Skin $skin Page skin, used to get info about the current page.
	 * @param string[][][] &$sidebar Links being modified if conditions are met.
	 *
	 * @return void
	 */
	private function addGlobalContributions( $skin, &$sidebar ): void {
		if ( !$this->shouldLinkToGlobalContributions( $skin ) ) {
			return;
		}

		$name = $skin->getRelevantUser()->getName();
		$targetTitle = SpecialPage::getTitleFor( 'GlobalContributions', $name );
		$globalContributionsLink = [
			'id' => 't-global-contributions',
			'text' => $skin->msg( 'checkuser-global-contributions-link-sidebar' )->text(),
			'href' => $targetTitle->getLocalURL()
		];

		// Try to insert the Global Contributions link after the 'contributions' key
		$index = array_search( 'contributions', array_keys( $sidebar['TOOLBOX'] ?? [] ) );
		if ( $index !== false ) {
			$index++;
			$sidebar['TOOLBOX'] = array_merge(
				array_slice( $sidebar['TOOLBOX'], 0, $index ),
				[ self::GLOBAL_CONTRIBUTIONS_KEY => $globalContributionsLink ],
				array_slice( $sidebar['TOOLBOX'], $index )
			);
		} else {
			$sidebar['TOOLBOX'][ self::GLOBAL_CONTRIBUTIONS_KEY ] = $globalContributionsLink;
		}
	}

	/**
	 * Checks if the user accessing the page is allowed to access the Global
	 * Contributions page for the user or IP the current page refers to.
	 *
	 * @param Skin $skin Object providing info about the current page & user.
	 * @return bool
	 */
	private function shouldLinkToGlobalContributions( Skin $skin ): bool {
		if ( !$skin->getRelevantUser() instanceof UserIdentity ) {
			// A Relevant User is set when listing (Global / IP) Contributions
			// by username or IP, but it isn't if the request refers to an IP
			// range.
			return false;
		}

		$gcAccess = $this->permissionManager->canAccessUserGlobalContributions(
			$skin->getAuthority(),
			$skin->getRelevantUser()->getName()
		);

		return $gcAccess->isGood();
	}

	/**
	 * Add tool to sidebar for managing IP auto-reveal status.
	 *
	 * @param Skin $skin Page skin, used to get info about the current page.
	 * @param string[][][] &$sidebar Links being modified if conditions are met.
	 *
	 * @return void
	 */
	public function addIPAutoReveal( $skin, &$sidebar ): void {
		$authority = $skin->getAuthority();
		$autoRevealStatus = $this->permissionManager->canAutoRevealIPAddresses( $authority );

		if ( !$autoRevealStatus->isGood() ) {
			return;
		}

		$out = $skin->getOutput();

		$out->addJSConfigVars( [
			'wgCheckUserTemporaryAccountAutoRevealTime' =>
				$this->config->get( 'CheckUserTemporaryAccountAutoRevealTime' )
		] );

		$out->addModules( 'ext.checkUser.tempAccounts' );
		$sidebar['TOOLBOX'][self::IP_AUTO_REVEAL_KEY] = [
			'id' => 't-checkuser-ip-auto-reveal',
			'text' => $skin->msg( 'checkuser-ip-auto-reveal-link-sidebar' )->text(),
			'href' => '#',
			'class' => 'checkuser-ip-auto-reveal',
		];
	}
}
