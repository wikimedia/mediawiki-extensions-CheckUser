<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use Skin;

/**
 * Adds a link to users' GlobalContributions pages on user pages.
 */
class SidebarLinksHandler implements SidebarBeforeOutputHook {
	/**
	 * Key used to identify the new link added to $sidebar['TOOLBOX'].
	 */
	private const TOOLBOX_KEY = 'global-contributions';

	private CheckUserPermissionManager $permissionManager;

	public function __construct(
		CheckUserPermissionManager $checkUserPermissionManager
	) {
		$this->permissionManager = $checkUserPermissionManager;
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
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
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
				[ self::TOOLBOX_KEY => $globalContributionsLink ],
				array_slice( $sidebar['TOOLBOX'], $index )
			);
		} else {
			$sidebar['TOOLBOX'][ self::TOOLBOX_KEY ] = $globalContributionsLink;
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
}
