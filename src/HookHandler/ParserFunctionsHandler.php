<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\User\UserNameUtils;
use Wikimedia\IPUtils;

/**
 * Registers the parser functions provided by CheckUser.
 */
class ParserFunctionsHandler implements ParserFirstCallInitHook {

	/**
	 * Tracking category for pages that invoke {{#uic:}} with something that cannot be a
	 * registered user name and is not an IP address or range.
	 */
	private const INVALID_USERNAME_TRACKING_CATEGORY = 'checkuser-uic-invalid-username-category';

	/**
	 * Tracking category for pages that invoke {{#uic:}} with an IP address or range as a target.
	 */
	private const IP_TARGET_TRACKING_CATEGORY = 'checkuser-uic-ip-target-category';

	/**
	 * Key under which every target a button was emitted for is recorded in the parser output's extension data.
	 */
	public const TARGETS_EXTENSION_DATA_KEY = 'checkuser-userinfocard-targets';

	/**
	 * Array of all the targets for whom {{#uic:}} was invoked. Consumed by PageDisplay hook handler.
	 *
	 * Normally we store such a list in parser's extension data, but if the parse is done for an interface message,
	 * this data will be thrown away (T66969). In order to preserve it, we keep this additional in-memory cache.
	 * It can't replace the parser cache though - parsed pages can be cached for a longer duration than a single
	 * request, in which case this array will be empty. This is not a problem for interface messages.
	 *
	 * @var array<string,bool>
	 */
	private static array $recordedTargets = [];

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
			if ( IPUtils::isIPAddress( $username ) ) {
				$parser->addTrackingCategory( self::IP_TARGET_TRACKING_CATEGORY );
			} else {
				$parser->addTrackingCategory( self::INVALID_USERNAME_TRACKING_CATEGORY );
			}
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
		self::$recordedTargets[$canonicalUsername] = true;

		return [ $html, 'isHTML' => true ];
	}

	/**
	 * @return list<string> Names of the users for whom {{#uic:}} was invoked
	 */
	public static function getRecordedUserInfoCardTargets(): array {
		return array_keys( self::$recordedTargets );
	}

	/**
	 * Resets the list of users for whom {{#uic:}} was invoked.
	 *
	 * It's supposed to be called once the information has been applied to the page being currently displayed,
	 * through the PageDisplay hook handler.
	 */
	public static function clearRecordedUserInfoCardTargets(): void {
		self::$recordedTargets = [];
	}
}
