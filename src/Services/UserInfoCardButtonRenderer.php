<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;

class UserInfoCardButtonRenderer {

	public function __construct(
		private readonly UserNameUtils $userNameUtils,
		private readonly UserInfoCardBlockStatusCache $blockStatusCache,
		private readonly UserIdentityLookup $userIdentityLookup,
	) {
	}

	/**
	 * Renders a button to trigger the UserInfoCard.
	 *
	 * This function doesn't load any modules that can actually support UIC JS code or styles,
	 * it's only responsible for rendering the button.
	 * @param string $targetName Name of the user for whom to display the card
	 * @param string $iconName Name of the icon to display in the button, e.g., 'userAvatar'
	 * @param MessageLocalizer $messageLocalizer To use when creating an accessible label for the button
	 * @param bool $hiddenByDefault If true, the button will have the 'hidden' attribute and be hidden
	 *      unless some CSS shows it.
	 * @return string
	 */
	public function render(
		string $targetName,
		string $iconName,
		MessageLocalizer $messageLocalizer,
		bool $hiddenByDefault = false,
	): string {
		// CSS-only Codex icon button
		$icon = Html::rawElement(
			'span',
			[
				'class' =>
					'cdx-button__icon ext-checkuser-userinfocard-button__icon ' .
					"ext-checkuser-userinfocard-button__icon--$iconName",
			]
		);
		$ariaLabel = $messageLocalizer->msg(
			'checkuser-userinfocard-toggle-button-aria-label',
			$targetName
		)->text();

		// <button>, not <a>: avoids matching gadgets that do
		// $('#mw-diff-ntitle2 a').first() to find the editor (T426830).
		return Html::rawElement(
			'button',
			[
				'type' => 'button',
				'aria-label' => $ariaLabel,
				'aria-haspopover' => 'dialog',
				'class' => 'ext-checkuser-userinfocard-button cdx-button ' .
					'cdx-button--action-default cdx-button--weight-quiet cdx-button--icon-only',
				'data-username' => $targetName,
				'hidden' => $hiddenByDefault,
			],
			$icon
		);
	}

	/**
	 * Returns the name of the Codex icon which represents the status of the target user.
	 *
	 * @param string $targetName Name of the user for whom to display the card
	 * @param array $options See $options for {@see getIconNamesForUsers}
	 * @return string One of 'userBlocked', 'userTemporary' or 'userAvatar'
	 */
	public function getIconName( string $targetName, array $options = [] ): string {
		return $this->getIconNamesForUsers( [ $targetName ], $options )[ $targetName ];
	}

	/**
	 * Maps usernames to names of Codex icons that should be displayed for the user on buttons for them.
	 *
	 * @param array $targetNames An array of the usernames
	 * @param array $options Optional switches to change the logic of applying the icons:
	 * * 'customIcons' (default: true) - if true, icons such as "is blocked" or similar, which denote the current
	 *    account status, will be available. Set to false if you need to cache the result for a long term
	 *    (e.g., in parser cache).
	 * * 'viewer' (default: null) - if an icon should be displayed only to users with certain rights, this is the
	 *     authority for whom to check the rights. If null, it will be assumed that the viewer has no rights.
	 * @return array A map of usernames to their corresponding icons. Every username will be present in the result.
	 */
	public function getIconNamesForUsers( array $targetNames, array $options = [] ): array {
		$options += [
			'customIcons' => true,
			'viewer' => null,
		];

		if ( !( $options['viewer'] instanceof Authority ) ) {
			$options['viewer'] = null;
		}

		if ( $options['viewer']?->isAllowed( 'hideuser' ) ) {
			$visibleTargetNames = $targetNames;
		} else {
			$visibleTargetNames = $this->userIdentityLookup->newSelectQueryBuilder()
				->whereUserNames( $targetNames )
				->hidden( false )
				->caller( __METHOD__ )
				->fetchUserNames();
		}

		$result = [];
		if ( $options['customIcons'] ) {
			$result = $this->applyCustomIcons( $visibleTargetNames );
		}

		// Fill out rest with the default icon, userAvatar/userTemporary
		$targetsWithoutIcons = array_diff( $targetNames, array_keys( $result ) );
		foreach ( $targetsWithoutIcons as $targetName ) {
			$result[$targetName] = $this->userNameUtils->isTemp( $targetName ) ? 'userTemporary' : 'userAvatar';
		}

		return $result;
	}

	private function applyCustomIcons( array $targetNames ): array {
		// The order in which the methods are listed here denotes the icon priority (top to bottom)
		$customIconHandlers = [
			$this->applyBlockedIcon( ... ),
		];

		$result = [];

		foreach ( $customIconHandlers as $customIconHandler ) {
			$userIcons = $customIconHandler( $targetNames );
			$result += $userIcons;

			$targetNames = array_diff( $targetNames, array_keys( $userIcons ) );
			if ( $targetNames === [] ) {
				return $result;
			}
		}
		return $result;
	}

	private function applyBlockedIcon( array $targetNames ): array {
		$blockedUsers = $this->blockStatusCache->getIndefinitelyBlockedOrLockedUsers( $targetNames );
		return array_fill_keys( $blockedUsers, 'userBlocked' );
	}
}
