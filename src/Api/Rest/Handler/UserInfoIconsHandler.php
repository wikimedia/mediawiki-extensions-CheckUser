<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\ParamValidator\TypeDef\ArrayDef;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handler for POST requests to /checkuser/v0/userinfo/icons
 *
 * Tells the client which icon to show on the UserInfoCard trigger buttons for the given users,
 * because some of the icons depend on state that the client cannot know without making requests to the API.
 */
class UserInfoIconsHandler extends SimpleHandler {

	private const USERS_PARAM_NAME = 'users';

	// Keep in sync with MAX_ICON_USERS in modules/ext.checkUser.userInfoCard/rest.js
	private const MAX_USERS = 500;

	public function __construct(
		private readonly UserInfoCardButtonRenderer $buttonRenderer,
		private readonly UserNameUtils $userNameUtils,
	) {
	}

	public function run(): Response {
		// Accept only named users, as UserInfoHandler does.
		$authority = $this->getAuthority();
		if ( !$authority->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				401
			);
		}

		$body = $this->getValidatedBody() ?? [];
		$requestedNames = $body[self::USERS_PARAM_NAME] ?? [];

		// Look the users up under their canonical names, but ensure that we return the names
		// as we received them.
		$canonicalNames = [];
		foreach ( $requestedNames as $requestedName ) {
			$canonicalName = $this->userNameUtils->getCanonical( (string)$requestedName );
			$canonicalNames[$requestedName] = $canonicalName !== false ? $canonicalName : null;
		}

		$namesToLookUp = array_values( array_unique( array_filter(
			$canonicalNames,
			static fn ( $canonicalName ) => $canonicalName !== null
		) ) );
		$icons = $namesToLookUp !== [] ? $this->buttonRenderer->getIconNamesForUsers(
			$namesToLookUp,
			[ 'viewer' => $authority ]
		) : [];

		$result = [];
		foreach ( $canonicalNames as $requestedName => $canonicalName ) {
			// We don't have a special icon for invalid usernames and users that don't exist, so
			// return the default icon for them.
			$result[$requestedName] = $canonicalName !== null ? $icons[$canonicalName] : 'userAvatar';
		}

		// Cast, because purely numeric usernames are valid, and PHP array keys for them
		// would make this a JSON list instead of the documented map.
		return $this->getResponseFactory()->createJson( [ 'icons' => (object)$result ] );
	}

	/** @inheritDoc */
	public function needsWriteAccess(): bool {
		return false;
	}

	/** @inheritDoc */
	public function getRequestBodyDescription(): MessageValue {
		return new MessageValue( 'checkuser-rest-request-desc-userinfo-icon' );
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			self::USERS_PARAM_NAME => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-userinfo-icon-users' ),
				self::PARAM_EXAMPLE => [ 'ExampleUser', 'OtherUser' ],
				ArrayDef::PARAM_SCHEMA => [
					'type' => 'array',
					'maxItems' => self::MAX_USERS,
					'items' => [
						'type' => 'string',
						'x-i18n-description' => 'checkuser-rest-request-property-desc-userinfo-icon-user-item',
					],
				],
			],
		];
	}

	/** @inheritDoc */
	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'x-i18n-description' => 'checkuser-rest-response-desc-userinfo-icon',
			'properties' => [
				'icons' => [
					'type' => 'object',
					'x-i18n-description' => 'checkuser-rest-property-desc-userinfo-icons',
					'additionalProperties' => [
						'type' => 'string',
						'enum' => [ 'userAvatar', 'userTemporary', 'userBlocked', 'suggestedInvestigations' ],
						'x-i18n-description' => 'checkuser-rest-property-desc-userinfo-icon-name',
					],
				],
			],
			'required' => [ 'icons' ],
			'example' => [
				'icons' => [
					'ExampleUser' => 'userAvatar',
					'OtherUser' => 'userBlocked',
				],
			],
		];
	}
}
