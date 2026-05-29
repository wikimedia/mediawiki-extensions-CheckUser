<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWiki\WikiMap\WikiMap;
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

	public function __construct(
		private readonly IContextSource $context,
		private readonly StatsFactory $statsFactory,
		private readonly ServiceOptions $config,
		private readonly ?MetricsClientFactory $metricsClientFactory,
	) {
		$this->config->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Record a successful API call to /checkuser/v0/userinfo (HTTP 200).
	 */
	public function onApiSuccess(): void {
		$this->statsFactory->withComponent( 'CheckUser' )
			->getCounter( 'userinfocard_api_success' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->increment();
		$this->emitInteractionEvent( 'api_request' );
	}

	/**
	 * Record that the requested user was not found.
	 */
	public function onUserNotFound(): void {
		$this->statsFactory->withComponent( 'CheckUser' )
			->getCounter( 'userinfocard_api_user_not_found' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->increment();
		$this->emitInteractionEvent( 'user_not_found' );
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

	private function emitInteractionEvent( string $action ): void {
		if (
			$this->metricsClientFactory === null ||
			!$this->config->get( 'CheckUserEnableUserInfoCardInstrumentation' )
		) {
			return;
		}
		$client = $this->metricsClientFactory->newMetricsClient( $this->context );
		$client->submitInteraction(
			'mediawiki.product_metrics.user_info_card_interaction',
			'/analytics/product_metrics/web/base/2.0.0',
			$action
		);
	}
}
