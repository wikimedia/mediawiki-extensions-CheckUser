<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserNameUtils;

class UserInfoCardButtonRenderer {

	public function __construct(
		private readonly UserNameUtils $userNameUtils,
	) {
	}

	/**
	 * Renders a button to trigger the UserInfoCard.
	 *
	 * This function doesn't load any modules that can actually support UIC JS code or styles,
	 * it's only responsible for rendering the button.
	 * @param string $targetName Name of the user for whom to display the card
	 * @param bool $isBlocked Whether the button should use the blocked icon
	 * @param MessageLocalizer $messageLocalizer To use when creating an accessible label for the button
	 * @param bool $hiddenByDefault If true, the button will have the 'hidden' attribute and be hidden
	 *      unless some CSS shows it.
	 * @return string
	 */
	public function render(
		string $targetName,
		bool $isBlocked,
		MessageLocalizer $messageLocalizer,
		bool $hiddenByDefault = false,
	): string {
		$iconName = $this->getIconName( $targetName, $isBlocked );
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
	 * @param bool $isBlocked Whether the target user is blocked
	 * @return string One of 'userBlocked', 'userTemporary' or 'userAvatar'
	 */
	public function getIconName( string $targetName, bool $isBlocked ): string {
		if ( $isBlocked ) {
			return 'userBlocked';
		}
		if ( $this->userNameUtils->isTemp( $targetName ) ) {
			return 'userTemporary';
		}
		return 'userAvatar';
	}
}
