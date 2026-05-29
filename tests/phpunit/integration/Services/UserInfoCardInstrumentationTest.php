<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardInstrumentation;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWikiIntegrationTestCase;
use Wikimedia\MetricsPlatform\MetricsClient;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\UserInfoCardInstrumentation
 */
class UserInfoCardInstrumentationTest extends MediaWikiIntegrationTestCase {

	private function newInstrumentation(
		?MetricsClientFactory $metricsClientFactory,
		bool $instrumentationEnabled = true,
		?StatsFactory $statsFactory = null
	): UserInfoCardInstrumentation {
		return new UserInfoCardInstrumentation(
			RequestContext::getMain(),
			$statsFactory ?? StatsFactory::newNull(),
			new ServiceOptions(
				UserInfoCardInstrumentation::CONSTRUCTOR_OPTIONS,
				[
					'CheckUserEnableUserInfoCardInstrumentation' => $instrumentationEnabled,
				]
			),
			$metricsClientFactory
		);
	}

	/** @dataProvider provideFunctionIncrementsCounter */
	public function testFunctionIncrementsCounter(
		string $functionName,
		string $counterName
	) {
		$statsHelper = StatsFactory::newUnitTestingHelper();

		$instrumentation = $this->newInstrumentation( null, true, $statsHelper->getStatsFactory() );
		$instrumentation->$functionName();

		$this->assertSame(
			1,
			$statsHelper->withComponent( 'CheckUser' )
				->count( $counterName )
		);
	}

	public static function provideFunctionIncrementsCounter(): iterable {
		yield 'onApiSuccess' => [
			'functionName' => 'onApiSuccess',
			'counterName' => 'userinfocard_api_success',
		];
		yield 'onUserNotFound' => [
			'functionName' => 'onUserNotFound',
			'counterName' => 'userinfocard_api_user_not_found',
		];
		yield 'onRateLimited' => [
			'functionName' => 'onRateLimited',
			'counterName' => 'userinfocard_api_rate_limit',
		];
	}

	/** @dataProvider provideFunctionEmitsEvent */
	public function testFunctionEmitsEvent(
		string $functionName,
		string $eventName
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );

		$mockClient = $this->createMock( MetricsClient::class );
		$mockClient->expects( $this->once() )
			->method( 'submitInteraction' )
			->with(
				'mediawiki.product_metrics.user_info_card_interaction',
				'/analytics/product_metrics/web/base/2.0.0',
				$eventName,
				[]
			);

		$mockFactory = $this->createMock( MetricsClientFactory::class );
		$mockFactory->method( 'newMetricsClient' )->willReturn( $mockClient );

		$this->newInstrumentation( $mockFactory )
			->$functionName();
	}

	/** @dataProvider provideFunctionEmitsEvent */
	public function testFunctionDoesntEmitEventWhenConfigDisabled(
		string $functionName,
		string $eventName
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );

		$mockFactory = $this->createMock( MetricsClientFactory::class );
		$mockFactory->expects( $this->never() )->method( 'newMetricsClient' );

		$instrumentation = $this->newInstrumentation( $mockFactory, false );
		$instrumentation->$functionName();
	}

	public static function provideFunctionEmitsEvent(): iterable {
		yield 'onApiSuccess' => [
			'functionName' => 'onApiSuccess',
			'eventName' => 'api_request',
		];
		yield 'onUserNotFound' => [
			'functionName' => 'onUserNotFound',
			'eventName' => 'user_not_found',
		];
		yield 'onRateLimited' => [
			'functionName' => 'onRateLimited',
			'eventName' => 'rate_limit_exceeded',
		];
	}
}
