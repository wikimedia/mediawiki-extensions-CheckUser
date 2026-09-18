<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Extension\DiscussionTools\Hooks\DiscussionToolsAddSignatureUserInfoMarkupHook;
use MediaWiki\User\UserNameUtils;

/**
 * Adds a UserInfoCard button before each comment signature on talk pages that DiscussionTools handles.
 */
class DiscussionToolsSignatureHandler implements DiscussionToolsAddSignatureUserInfoMarkupHook {

	public function __construct(
		private readonly UserNameUtils $userNameUtils,
		private readonly UserInfoCardButtonRenderer $buttonRenderer,
	) {
	}

	/**
	 * @param string[] &$markupByItemId HTML to add before each signature, keyed by thread item ID
	 * @param array[] $threadItemsData Data of every comment in the content, keyed by thread item ID
	 * @param IContextSource $contextSource
	 */
	public function onDiscussionToolsAddSignatureUserInfoMarkup(
		array &$markupByItemId,
		array $threadItemsData,
		IContextSource $contextSource
	): void {
		if ( !$this->buttonRenderer->isUicEnabledForUser( $contextSource->getUser() ) ) {
			return;
		}

		$targetNames = [];
		foreach ( $threadItemsData as $threadItemData ) {
			$authorName = $threadItemData['author'];
			if ( !isset( $targetNames[$authorName] ) && $this->userNameUtils->isValid( $authorName ) ) {
				$targetNames[$authorName] = true;
			}
		}
		if ( $targetNames === [] ) {
			return;
		}

		$iconNames = $this->buttonRenderer->getIconNamesForUsers(
			array_map( 'strval', array_keys( $targetNames ) ),
			[ 'viewer' => $contextSource->getAuthority() ]
		);

		$buttons = [];
		foreach ( $threadItemsData as $itemId => $threadItemData ) {
			$authorName = $threadItemData['author'];
			$icon = $iconNames[ $authorName ] ?? null;
			if ( $icon === null ) {
				// Username was invalid, don't render the button
				continue;
			}

			$buttons[$authorName] ??= $this->buttonRenderer->render( $authorName, $icon, $contextSource );
			$markupByItemId[$itemId] .= $buttons[$authorName];
		}
		$this->buttonRenderer->addModules( $contextSource->getOutput() );
	}
}
