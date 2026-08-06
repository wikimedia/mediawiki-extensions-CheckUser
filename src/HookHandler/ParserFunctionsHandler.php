<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\User\UserNameUtils;

/**
 * Registers the parser functions provided by CheckUser.
 */
class ParserFunctionsHandler implements ParserFirstCallInitHook {

	/**
	 * Tracking category for pages that invoke {{#uic:}} with something that cannot be a
	 * registered user name.
	 */
	private const INVALID_USERNAME_TRACKING_CATEGORY = 'checkuser-uic-invalid-username-category';

	/**
	 * Key under which every target a button was emitted for is recorded in the parser output's extension data.
	 */
	public const TARGETS_EXTENSION_DATA_KEY = 'checkuser-userinfocard-targets';

	public function __construct(
		private readonly UserNameUtils $userNameUtils,
		private readonly UserInfoCardButtonRenderer $buttonRenderer,
	) {
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'uic', [ $this, 'renderUserInfoCardButton' ] );
	}

	/**
	 * Render a UserInfoCard trigger button for {{#uic:Username}}.
	 *
	 * The button is emitted for everyone, so that the parser cache is not split on whether the
	 * viewer has the UserInfoCard preference enabled. It is emitted hidden, and revealed by
	 * ext.checkUser.styles together with a body class that PageDisplay adds
	 * at request time for viewers who have the preference on.
	 *
	 * No database is touched: the icon variant is derived from the name alone, and the user's
	 * existence is not checked. Whether the account exists, and whether it is blocked, are both
	 * things that can change without the page being reparsed, so they must not be baked into the
	 * parser cache. The card itself reports a non-existent user when it is opened.
	 *
	 * @param Parser $parser
	 * @param string $username Name of the user the card should be shown for
	 * @return array|string
	 */
	public function renderUserInfoCardButton( Parser $parser, string $username = '' ) {
		$username = trim( $username );
		$canonicalUsername = $this->userNameUtils->getCanonical( $username );
		if ( $canonicalUsername === false ) {
			$parser->addTrackingCategory( self::INVALID_USERNAME_TRACKING_CATEGORY );
			return '';
		}

		$html = $this->buttonRenderer->render(
			$canonicalUsername,
			false,
			$parser,
			true
		);

		$output = $parser->getOutput();
		$output->addModuleStyles( [ 'ext.checkUser.styles' ] );
		$output->appendExtensionData( self::TARGETS_EXTENSION_DATA_KEY, $canonicalUsername );

		return [ $html, 'isRawHTML' => true ];
	}
}
