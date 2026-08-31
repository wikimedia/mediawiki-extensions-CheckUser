<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use Wikimedia\Stats\StatsFactory;

/**
 * Server-side instrumentation for the UserInfoCard feature (T405216).
 *
 * Prometheus counters are always emitted. EventLogging events are only emitted
 * when the EventLogging extension is available (non-null MetricsClientFactory)
 * and the CheckUserEnableUserInfoCardInstrumentation config flag is true.
 */
class UserInfoCardInstrumentation {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserEnableUserInfoCardInstrumentation',
	];

	/**
	 * Places from which the card can be opened.
	 *
	 * Keep in sync with getOpenContext() in modules/ext.checkUser.userInfoCard/util.js.
	 */
	private const OPENED_FROM_VALUES = [
		'user-page-toolbar',
		'log',
		'checkuser',
		'suggested-investigations',
		'blocklist',
		'rc',
		'special',
		'history',
		'diff',
		'page',
		'other',
	];

	private ?string $openedFrom = null;

	public function __construct(
		private readonly DerivativeContext $context,
		private readonly StatsFactory $statsFactory,
		private readonly ServiceOptions $config,
		private readonly ?MetricsClientFactory $metricsClientFactory,
		private readonly TitleFactory $titleFactory,
		private readonly LoggerInterface $logger,
	) {
		$this->config->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Set the page which the UserInfoCard was opened from, so that the page fields of the
	 * events point to that page.
	 *
	 * Titles which cannot be parsed, and titles on other wikis, are ignored.
	 *
	 * @param ?string $prefixedTitle Prefixed title of the page, or null if it is not known
	 */
	public function setSourcePage( ?string $prefixedTitle ): void {
		if ( $prefixedTitle === null ) {
			return;
		}
		$title = $this->titleFactory->newFromText( $prefixedTitle );
		if ( $title === null || $title->isExternal() ) {
			return;
		}
		$this->context->setTitle( $title );
	}

	/**
	 * Set the place from which the UserInfoCard was opened, which is reported as the page
	 * field of the action context.
	 *
	 * Values outside of self::OPENED_FROM_VALUES are ignored.
	 *
	 * @param ?string $openedFrom The place, or null if it is not known
	 */
	public function setOpenedFrom( ?string $openedFrom ): void {
		if ( in_array( $openedFrom, self::OPENED_FROM_VALUES, true ) ) {
			$this->openedFrom = $openedFrom;
		} elseif ( $openedFrom !== null ) {
			$this->logger->warning(
				'Invalid string provided for openedFrom in userinfo API: {provided}',
				[
					'provided' => $openedFrom,
				]
			);
		}
	}

	/**
	 * Record a successful API call to /checkuser/v0/userinfo (HTTP 200).
	 * @param string $targetUser Name of the user, whose data was returned
	 */
	public function onApiSuccess( string $targetUser ): void {
		$this->statsFactory->withComponent( 'CheckUser' )
			->getCounter( 'userinfocard_api_success' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->increment();
		$this->emitInteractionEvent( 'api_request', [ 'username' => $targetUser ] );
	}

	/**
	 * Record that the requested user was not found.
	 * @param string $targetUser Username that was requested
	 */
	public function onUserNotFound( string $targetUser ): void {
		$this->statsFactory->withComponent( 'CheckUser' )
			->getCounter( 'userinfocard_api_user_not_found' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->increment();
		$this->emitInteractionEvent( 'user_not_found', [ 'username' => $targetUser ] );
	}

	/**
	 * Record that the performing user hit the rate limit.
	 */
	public function onRateLimited(): void {
		$this->statsFactory->withComponent( 'CheckUser' )
			->getCounter( 'userinfocard_api_rate_limit' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->increment();
		$this->emitInteractionEvent( 'rate_limit_exceeded' );
	}

	private function emitInteractionEvent( string $action, array $actionContext = [] ): void {
		if (
			$this->metricsClientFactory === null ||
			!$this->config->get( 'CheckUserEnableUserInfoCardInstrumentation' )
		) {
			return;
		}
		if ( $this->openedFrom !== null ) {
			$actionContext = [ 'page' => $this->openedFrom ] + $actionContext;
		}
		$client = $this->metricsClientFactory->newMetricsClient( $this->context );
		$client->submitInteraction(
			'mediawiki.product_metrics.user_info_card_interaction',
			'/analytics/product_metrics/web/base/2.0.0',
			$action,
			[
				'action_context' => json_encode( $actionContext ),
			]
		);
	}
}
