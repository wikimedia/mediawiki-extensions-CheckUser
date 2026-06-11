<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardInstrumentation;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * REST endpoint for /checkuser/v0/userinfo to return data for the
 * UserInfoCard feature (T384725)
 */
class UserInfoHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	private const USERNAME_PARAM_NAME = 'username';

	public function __construct(
		private readonly CheckUserUserInfoCardService $userInfoCardService,
		private readonly UserFactory $userFactory,
		private readonly UserInfoCardInstrumentation $instrumentation,
	) {
	}

	/**
	 * @throws LocalizedHttpException
	 * @throws HttpException
	 */
	public function run(): Response {
		$this->assertHasAccess();

		$body = $this->getValidatedBody() ?? [];
		$username = $body[ self::USERNAME_PARAM_NAME ];
		$user = $this->userFactory->newFromName( $username );

		if ( $user instanceof UserIdentity ) {
			$user->load();
		}

		// If the user exists, is hidden, and the authority doesn't have the `hideuser`
		// right, then pretend that the user doesn't exist
		if ( $user && $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
			$user = null;
		}

		if ( $user === null || !$user->getId() ) {
			$this->instrumentation->onUserNotFound();
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-userinfo-user-not-found' ),
				404
			);
		}

		$userInfo = $this->userInfoCardService->getUserInfo(
			$this->getAuthority(),
			$user
		);

		$this->instrumentation->onApiSuccess();
		return $this->getResponseFactory()->createJson( $userInfo );
	}

	/** @inheritDoc */
	public function getRequestBodyDescription(): MessageValue {
		return new MessageValue( 'checkuser-rest-request-desc-userinfo' );
	}

	/** @inheritDoc */
	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'x-i18n-description' => 'checkuser-rest-response-desc-userinfo',
			'properties' => [
				'name' => [
					'type' => 'string',
					'x-i18n-description' => 'checkuser-rest-property-desc-username',
				],
				'gender' => [
					'type' => 'string',
					'x-i18n-description' => 'checkuser-rest-property-desc-gender',
				],
				'localRegistration' => [
					'type' => 'string',
					'nullable' => true,
					'x-i18n-description' => 'checkuser-rest-property-desc-local-registration',
				],
				'firstRegistration' => [
					'type' => 'string',
					'nullable' => true,
					'x-i18n-description' => 'checkuser-rest-property-desc-first-registration',
				],
				'userPageIsKnown' => [
					'type' => 'boolean',
					'x-i18n-description' => 'checkuser-rest-property-desc-user-page-exists',
				],
				'hasLocalBlockGlobalBlockOrLock' => [
					'type' => 'boolean',
					'x-i18n-description' => 'checkuser-rest-property-desc-blocked-or-locked',
				],
				'groups' => [
					'type' => 'string',
					'x-i18n-description' => 'checkuser-rest-property-desc-groups',
				],
				'totalEditCount' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-total-edit-count',
				],
				'globalEditCount' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-global-edit-count',
				],
				'globalGroups' => [
					'type' => 'string',
					'x-i18n-description' => 'checkuser-rest-property-desc-global-groups',
				],
				'globalRestrictions' => [
					'type' => 'string',
					'nullable' => true,
					'x-i18n-description' => 'checkuser-rest-property-desc-global-restrictions',
				],
				'globalRestrictionsTimestamp' => [
					'type' => 'string',
					'nullable' => true,
					'x-i18n-description' => 'checkuser-rest-property-desc-global-restrictions-timestamp',
				],
				'activeWikis' => [
					'type' => 'object',
					'x-i18n-description' => 'checkuser-rest-property-desc-active-wikis',
				],
				'checkUserChecks' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-checkuser-checks',
				],
				'checkUserLastCheck' => [
					'type' => 'string',
					'x-i18n-description' => 'checkuser-rest-property-desc-checkuser-last-check',
				],
				'suggestedInvestigationsCaseCount' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-suggested-investigations-cases',
				],
				'canAccessTemporaryAccountIpAddresses' => [
					'type' => 'boolean',
					'x-i18n-description' => 'checkuser-rest-property-desc-can-access-temp-account-ip',
				],
				'activeBlocksOnLocalWiki' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-active-blocks-local',
				],
				'activeLocalBlocksAllWikis' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-active-local-blocks-all',
				],
				'pastBlocksOnLocalWiki' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-past-blocks-local',
				],
				'tempAccountsOnIPCount' => [
					'type' => 'array',
					'x-i18n-description' => 'checkuser-rest-property-desc-temp-accounts-ip-count',
					'items' => [
						'type' => 'integer',
					],
				],
				'specialCentralAuthUrl' => [
					'type' => 'string',
					'nullable' => true,
					'x-i18n-description' => 'checkuser-rest-property-desc-special-central-auth-url',
				],
			],
			'required' => [
				'name',
				'gender',
				'localRegistration',
				'firstRegistration',
				'userPageIsKnown',
				'hasLocalBlockGlobalBlockOrLock',
				'groups',
				'totalEditCount',
				'activeWikis',
			],
			'example' => [
				'name' => 'ExampleUser',
				'gender' => 'unknown',
				'localRegistration' => '20200101000000',
				'firstRegistration' => '20200101000000',
				'userPageIsKnown' => true,
				'hasLocalBlockGlobalBlockOrLock' => false,
				'x-i18n-groups' => 'checkuser-rest-example-groups',
				'totalEditCount' => 12500,
				'globalEditCount' => 45000,
				'x-i18n-globalGroups' => 'checkuser-rest-example-global-groups',
				'globalRestrictions' => null,
				'globalRestrictionsTimestamp' => null,
				'activeWikis' => [
					'enwiki' => 'https://en.wikipedia.org/wiki/Special:Contributions/ExampleUser',
				],
				'checkUserChecks' => 5,
				'checkUserLastCheck' => '20260610000000',
				'suggestedInvestigationsCaseCount' => 2,
				'canAccessTemporaryAccountIpAddresses' => false,
				'activeBlocksOnLocalWiki' => 0,
				'activeLocalBlocksAllWikis' => 0,
				'pastBlocksOnLocalWiki' => 2,
				'tempAccountsOnIPCount' => [ 0, 0 ],
				'specialCentralAuthUrl' => 'https://meta.wikimedia.org/wiki/Special:CentralAuth/ExampleUser',
			],
		];
	}

	public function getBodyParamSettings(): array {
		$settings = $this->getTokenParamDefinition();
		$settings['token'][self::PARAM_DESCRIPTION] = new MessageValue( 'checkuser-rest-request-property-desc-token' );
		$settings['token'][self::PARAM_EXAMPLE] = '+\\';

		return $settings + [
			self::USERNAME_PARAM_NAME => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-username' ),
				self::PARAM_EXAMPLE => 'ExampleUser',
			],
		];
	}

	/**
	 * @throws HttpException
	 * @throws LocalizedHttpException
	 */
	private function assertHasAccess(): void {
		$this->validateToken();

		$authority = $this->getAuthority();
		if ( !$authority->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				401
			);
		}

		$performingUser = $this->userFactory->newFromUserIdentity(
			$authority->getUser()
		);

		if ( $performingUser->pingLimiter( 'checkuser-userinfo' ) ) {
			$this->instrumentation->onRateLimited();
			throw new HttpException( 'Too many requests to user info data', 429 );
		}
	}
}
