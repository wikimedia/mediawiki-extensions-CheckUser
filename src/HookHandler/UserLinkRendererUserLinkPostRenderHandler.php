<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Hook\UserLinkRendererUserLinkPostRenderHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;

class UserLinkRendererUserLinkPostRenderHandler implements UserLinkRendererUserLinkPostRenderHook {

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly UserInfoCardBlockStatusCache $blockStatusCache,
		private readonly UserInfoCardButtonRenderer $buttonRenderer,
	) {
	}

	public function onUserLinkRendererUserLinkPostRender(
		UserIdentity $targetUser,
		IContextSource $context,
		string &$html,
		string &$prefix,
		string &$postfix
	) {
		if ( !$targetUser->isRegistered() ) {
			return;
		}
		if ( $this->userOptionsLookup->getBoolOption( $context->getUser(), Preferences::ENABLE_USER_INFO_CARD ) ) {
			$output = $context->getOutput();
			$output->addModuleStyles( 'ext.checkUser.styles' );
			$output->addModules( 'ext.checkUser.userInfoCard' );

			$isBlocked = $this->blockStatusCache->isIndefinitelyBlockedOrLocked( $targetUser->getName() );

			$buttonHtml = $this->buttonRenderer->render(
				$targetUser->getName(),
				$isBlocked,
				$context
			);

			// This will prevent the button + userlink from wrapping next to the screen edges
			$html = Html::rawElement(
				'span',
				[ 'class' => 'ext-checkuser-userinfocard-button-wrapper' ],
				$buttonHtml . $html
			);
		}
	}
}
