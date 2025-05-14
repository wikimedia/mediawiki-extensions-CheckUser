<?php

namespace MediaWiki\CheckUser\HookHandler;

use IContextSource;
use Mediawiki\Linker\Hook\UserLinkRendererUserLinkPostRenderHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\WikiMap\WikiMap;
use OOUI\ButtonWidget;

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
			$output->enableOOUI();
			$wikiId = $targetUser->getWikiId() ?: WikiMap::getCurrentWikiId();
			$prefix .= ( new ButtonWidget( [
				'framed' => false,
				'href' => '#',
				'icon' => $this->userNameUtils->isTemp( $targetUser->getName() ) ? 'userTemporary' : 'userAvatar',
				'flags' => [ 'progressive' ],
				'invisibleLabel' => true,
				'infusable' => true,
				'classes' => [
					'ext-checkuser-userinfocard-button', 'ext-checkuser-userinfocard-id-' .
					$wikiId . ':' . $targetUser->getId()
				],
			] ) )->toString();
		}
	}
}
