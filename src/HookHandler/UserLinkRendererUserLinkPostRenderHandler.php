<?php

namespace MediaWiki\CheckUser\HookHandler;

use IContextSource;
use MediaWiki\Html\Html;
use Mediawiki\Linker\Hook\UserLinkRendererUserLinkPostRenderHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\WikiMap\WikiMap;

class UserLinkRendererUserLinkPostRenderHandler implements UserLinkRendererUserLinkPostRenderHook {

	private UserOptionsLookup $userOptionsLookup;
	private UserNameUtils $userNameUtils;

	public function __construct( UserOptionsLookup $userOptionsLookup, UserNameUtils $userNameUtils ) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userNameUtils = $userNameUtils;
	}

	public function onUserLinkRendererUserLinkPostRender(
		UserIdentity $targetUser,
		IContextSource $context,
		string &$html,
		string &$prefix,
		string &$postfix
	) {
		if ( $this->userOptionsLookup->getBoolOption( $context->getUser(), Preferences::ENABLE_USER_INFO_CARD ) ) {
			$output = $context->getOutput();
			$output->addModuleStyles( 'oojs-ui.styles.icons-user' );
			$output->addModuleStyles( 'ext.checkUser.styles' );
			$output->addModules( 'ext.checkUser.userInfoCard' );

			$wikiId = $targetUser->getWikiId() ?: WikiMap::getCurrentWikiId();
			$iconClass = $this->userNameUtils->isTemp( $targetUser->getName() ) ? 'userTemporary' : 'userAvatar';
			$wikiIdClass = 'ext-checkuser-userinfocard-id-' . $wikiId . ':' . $targetUser->getId();

			// CSS-only Codex icon button
			$icon = Html::rawElement(
				'span',
				[
					'class' =>
						'cdx-button__icon ext-checkuser-userinfocard-button__icon ' .
						"ext-checkuser-userinfocard-button__icon--$iconClass"
				]
			);
			$markup = Html::rawElement(
				'a',
				[
					'href' => '#',
					'role' => 'button',
					'aria-label' => $context->msg( 'checkuser-userinfocard-toggle-button-aria-label' )->text(),
					'class' => "ext-checkuser-userinfocard-button $wikiIdClass cdx-button " .
						'cdx-button--action-default cdx-button--weight-quiet cdx-button--fake-button ' .
						'cdx-button--fake-button--enabled cdx-button--icon-only'
				],
				$icon
			);
			$prefix .= $markup;
		}
	}
}
