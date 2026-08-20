<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\User\Options\UserOptionsLookup;

/**
 * Adds a UserInfoCard trigger to the page navigation of user pages and user talk pages.
 */
class UserInfoCardNavigationHandler implements SkinTemplateNavigation__UniversalHook {

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly UserInfoCardBlockStatusCache $blockStatusCache,
		private readonly UserInfoCardButtonRenderer $buttonRenderer,
	) {
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$this->shouldAddUserInfoCard( $sktemplate ) ) {
			return;
		}

		$output = $sktemplate->getOutput();
		$output->addModuleStyles( 'ext.checkUser.styles' );
		$output->addModules( 'ext.checkUser.userInfoCard' );

		$target = $sktemplate->getRelevantUser();
		$isBlocked = $this->blockStatusCache->isIndefinitelyBlockedOrLocked( $target->getName() );
		$iconName = $this->buttonRenderer->getIconName( $target->getName(), $isBlocked );

		// The username is not put into a data attribute, because the skins render this markup
		// themselves; the JavaScript uses wgRelevantUserName instead.
		//
		// The icon name goes into a class as well as into the 'icon' key, because skins which
		// keep the name of the icon out of the HTML, such as Vector 2010, can then still show
		// the correct icon with CSS. The following CSS classes are used here:
		// * ext-checkuser-userinfocard-navigation-item--userAvatar
		// * ext-checkuser-userinfocard-navigation-item--userTemporary
		// * ext-checkuser-userinfocard-navigation-item--userBlocked
		$links['views']['checkuser-userinfocard'] = [
			'text' => $sktemplate->msg( 'checkuser-userinfocard-navigation-label' )->text(),
			'href' => '#',
			'icon' => $iconName,
			'class' => 'ext-checkuser-userinfocard-navigation-item ' .
				"ext-checkuser-userinfocard-navigation-item--$iconName",
			'tooltip-params' => [ $target->getName() ],
		];
	}

	private function shouldAddUserInfoCard( SkinTemplate $skinTemplate ): bool {
		if ( !$skinTemplate->getTitle()?->hasSubjectNamespace( NS_USER ) ) {
			return false;
		}

		$target = $skinTemplate->getRelevantUser();
		if ( !$target || !$target->isRegistered() ) {
			return false;
		}

		$performer = $skinTemplate->getUser();
		if ( !$performer->isNamed() ) {
			return false;
		}

		return $this->userOptionsLookup->getBoolOption(
			$performer,
			Preferences::ENABLE_USER_INFO_CARD
		);
	}
}
