<?php

namespace MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;

/**
 * Wrapper class for emitting server-side interaction events to the Suggested Investigations
 * Metrics Platform instrument.
 */
class SuggestedInvestigationsInstrumentationClient implements ISuggestedInvestigationsInstrumentationClient {

	public function __construct( private readonly MetricsClientFactory $metricsClientFactory ) {
	}

	/** @inheritDoc */
	public function submitInteraction(
		IContextSource $context,
		string $action,
		array $interactionData
	): void {
		$client = $this->metricsClientFactory->newMetricsClient( $context );

		$client->submitInteraction(
			'mediawiki.product_metrics.suggested_investigations_interaction',
			'/analytics/product_metrics/web/base/1.4.3',
			$action,
			$interactionData
		);
	}
}
