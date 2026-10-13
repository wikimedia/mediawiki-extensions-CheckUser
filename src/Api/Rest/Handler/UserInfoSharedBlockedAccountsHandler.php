<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Services\CheckUserSharedBrowserVersionAccountsLookup;
use MediaWiki\Extension\CheckUser\Services\CheckUserSharedIPAccountsLookup;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardInstrumentation;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeBlockChecker;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Message\MessageValue;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handler for POST requests to /checkuser/v0/userinfo/sharedblocked/{name}.
 *
 * Returns the number of blocked accounts that share IP addresses or browser versions with
 * the target user (T438686). Intended to be called by the ext.checkUser.userInfoCard module.
 */
class UserInfoSharedBlockedAccountsHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	private const int CACHE_TTL = WANObjectCache::TTL_MINUTE * 15;

	public function __construct(
		private readonly Config $config,
		private readonly UserFactory $userFactory,
		private readonly UserEditTracker $userEditTracker,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly CheckUserSharedIPAccountsLookup $sharedIPAccountsLookup,
		private readonly CheckUserSharedBrowserVersionAccountsLookup $sharedBrowserVersionAccountsLookup,
		private readonly CompositeBlockChecker $blockChecker,
		private readonly WANObjectCache $wanCache,
		private readonly UserInfoCardInstrumentation $instrumentation,
	) {
	}

	/**
	 * @throws LocalizedHttpException
	 * @throws HttpException
	 */
	public function run( string $name ): array {
		$this->assertHasAccess();

		$user = $this->userFactory->newFromName( $name );
		if (
			$user === null ||
			!$user->getId() ||
			( $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' ) )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-userinfo-user-not-found' ),
				404
			);
		}

		$maxTargetEditCount = $this->config->get( 'CheckUserUserInfoCardSharedBlockedMaxEdits' );
		if ( $this->userEditTracker->getUserEditCount( $user ) > $maxTargetEditCount ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-rest-userinfo-sharedblocked-too-many-edits',
					[ $maxTargetEditCount ]
				),
				403
			);
		}

		// The count can include hidden users only for viewers with the hideuser right.
		$canSeeHiddenUsers = $this->getAuthority()->isAllowed( 'hideuser' );
		$this->instrumentation->onSharedBlockedAccountsSuccess();
		return $this->wanCache->getWithSetCallback(
			$this->wanCache->makeKey(
				'checkuser-userinfo-sharedblocked',
				$user->getId(),
				$canSeeHiddenUsers ? 'with-hidden' : 'without-hidden'
			),
			self::CACHE_TTL,
			fn () => [
				'ip' => $this->getBlockedCount(
					$this->sharedIPAccountsLookup->getUsersOnSharedIPs( $user ),
					$canSeeHiddenUsers
				),
				'browserVersion' => $this->getBlockedCount(
					$this->sharedBrowserVersionAccountsLookup->getUsersWithSharedBrowserVersion( $user ),
					$canSeeHiddenUsers
				),
			]
		);
	}

	/**
	 * @param array{users: UserIdentity[], complete: bool} $sharedAccounts
	 * @param bool $canSeeHiddenUsers
	 * @return array{count: int, complete: bool}
	 */
	private function getBlockedCount( array $sharedAccounts, bool $canSeeHiddenUsers ): array {
		$userIds = array_map(
			static fn ( UserIdentity $user ) => $user->getId(),
			$sharedAccounts['users']
		);
		if ( $userIds && !$canSeeHiddenUsers ) {
			$visibleUsers = $this->userIdentityLookup->newSelectQueryBuilder()
				->whereUserIds( $userIds )
				->hidden( false )
				->caller( __METHOD__ )
				->fetchUserIdentities();
			$userIds = [];
			foreach ( $visibleUsers as $user ) {
				$userIds[] = $user->getId();
			}
		}

		$count = $userIds ? count( $this->blockChecker->getUserIdsBlocked( $userIds ) ) : 0;
		return [ 'count' => $count, 'complete' => $sharedAccounts['complete'] ];
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

		if ( !$authority->isAllowed( 'checkuser' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				403
			);
		}

		$performingUser = $this->userFactory->newFromUserIdentity( $authority->getUser() );
		if ( $performingUser->pingLimiter( 'checkuser-userinfo' ) ) {
			$this->instrumentation->onRateLimited();
			throw new HttpException( 'Too many requests to user info data', 429 );
		}
	}

	/** @inheritDoc */
	public function getRequestBodyDescription(): MessageValue {
		return new MessageValue( 'checkuser-rest-request-desc-userinfo-sharedblocked' );
	}

	/** @inheritDoc */
	protected function getResponseBodySchema( string $method ): ?array {
		$countSchema = [
			'type' => 'object',
			'properties' => [
				'count' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-sharedblocked-count',
				],
				'complete' => [
					'type' => 'boolean',
					'x-i18n-description' => 'checkuser-rest-property-desc-sharedblocked-complete',
				],
			],
			'required' => [ 'count', 'complete' ],
		];

		return [
			'type' => 'object',
			'x-i18n-description' => 'checkuser-rest-response-desc-userinfo-sharedblocked',
			'properties' => [
				'ip' => $countSchema + [
					'x-i18n-description' => 'checkuser-rest-property-desc-sharedblocked-ip',
				],
				'browserVersion' => $countSchema + [
					'x-i18n-description' => 'checkuser-rest-property-desc-sharedblocked-browser-version',
				],
			],
			'required' => [ 'ip', 'browserVersion' ],
			'example' => [
				'ip' => [ 'count' => 3, 'complete' => true ],
				'browserVersion' => [ 'count' => 0, 'complete' => false ],
			],
		];
	}

	/** @inheritDoc */
	public function getParamSettings(): array {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-sharedblocked-name' ),
				self::PARAM_EXAMPLE => 'ExampleUser',
			],
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		$settings = $this->getTokenParamDefinition();
		$settings['token'][self::PARAM_DESCRIPTION] = new MessageValue( 'checkuser-rest-request-property-desc-token' );
		$settings['token'][self::PARAM_EXAMPLE] = '+\\';
		return $settings;
	}
}
