<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Hook\ContributionsToolLinksHook;
use MediaWiki\Hook\UserToolLinksEditHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use RequestContext;

class ToolLinksHandler implements ContributionsToolLinksHook, UserToolLinksEditHook {

	private PermissionManager $permissionManager;
	private SpecialPageFactory $specialPageFactory;
	private LinkRenderer $linkRenderer;
	private UserIdentityLookup $userIdentityLookup;
	private UserIdentityUtils $userIdentityUtils;

	/**
	 * @param PermissionManager $permissionManager
	 * @param SpecialPageFactory $specialPageFactory
	 * @param LinkRenderer $linkRenderer
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserIdentityUtils $userIdentityUtils
	 */
	public function __construct(
		PermissionManager $permissionManager,
		SpecialPageFactory $specialPageFactory,
		LinkRenderer $linkRenderer,
		UserIdentityLookup $userIdentityLookup,
		UserIdentityUtils $userIdentityUtils
	) {
		$this->permissionManager = $permissionManager;
		$this->specialPageFactory = $specialPageFactory;
		$this->linkRenderer = $linkRenderer;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/**
	 * Add a link to Special:CheckUser and Special:CheckUserLog
	 * on Special:Contributions/<username> for
	 * privileged users.
	 *
	 * @param int $id User ID
	 * @param Title $nt User page title
	 * @param string[] &$links Tool links
	 * @param SpecialPage $sp Special page
	 */
	public function onContributionsToolLinks(
		$id, Title $nt, array &$links, SpecialPage $sp
	) {
		$user = $sp->getUser();
		$linkRenderer = $sp->getLinkRenderer();

		if ( $this->permissionManager->userHasRight( $user, 'checkuser' ) ) {
			$links['checkuser'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUser' ),
				$sp->msg( 'checkuser-contribs' )->text(),
				[ 'class' => 'mw-contributions-link-check-user' ],
				[ 'user' => $nt->getText() ]
			);
		}
		if ( $this->permissionManager->userHasRight( $user, 'checkuser-log' ) ) {
			$links['checkuser-log'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				$sp->msg( 'checkuser-contribs-log' )->text(),
				[ 'class' => 'mw-contributions-link-check-user-log' ],
				[ 'cuSearch' => $nt->getText() ]
			);
			$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $id );
			if ( $id && $userIdentity && $this->userIdentityUtils->isNamed( $userIdentity ) ) {
				$links['checkuser-log-initiator'] = $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUserLog' ),
					$sp->msg( 'checkuser-contribs-log-initiator' )->text(),
					[ 'class' => 'mw-contributions-link-check-user-initiator' ],
					[ 'cuInitiator' => $nt->getText() ]
				);
			}
		}
	}

	/** @inheritDoc */
	public function onUserToolLinksEdit( $userId, $userText, &$items ) {
		$requestTitle = RequestContext::getMain()->getTitle();
		if (
			$requestTitle !== null &&
			$requestTitle->isSpecialPage()
		) {
			$specialPageName = $this->specialPageFactory->resolveAlias( $requestTitle->getText() )[0];
			if ( $specialPageName === 'CheckUserLog' ) {
				$items[] = $this->linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'CheckUserLog', $userText ),
					wfMessage( 'checkuser-log-checks-on' )->text()
				);
			} elseif ( $specialPageName === 'CheckUser' ) {
				$items[] = $this->linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'CheckUser', $userText ),
					wfMessage( 'checkuser-toollink-check' )->text(),
					[],
					[ 'reason' => RequestContext::getMain()->getRequest()->getVal( 'reason', '' ) ]
				);
			}
		}
	}
}
