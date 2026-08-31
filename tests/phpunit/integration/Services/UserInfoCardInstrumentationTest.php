<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardInstrumentation;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
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
		?StatsFactory $statsFactory = null,
		?DerivativeContext $context = null,
		?TitleFactory $titleFactory = null
	): UserInfoCardInstrumentation {
		return new UserInfoCardInstrumentation(
			$context ?? new DerivativeContext( RequestContext::getMain() ),
			$statsFactory ?? StatsFactory::newNull(),
			new ServiceOptions(
				UserInfoCardInstrumentation::CONSTRUCTOR_OPTIONS,
				[
					'CheckUserEnableUserInfoCardInstrumentation' => $instrumentationEnabled,
				]
			),
			$metricsClientFactory,
			$titleFactory ?? $this->getServiceContainer()->getTitleFactory()
		);
	}

	/** @dataProvider provideFunctionIncrementsCounter */
	public function testFunctionIncrementsCounter(
		string $functionName,
		string $counterName,
		array $functionArgs
	) {
		$statsHelper = StatsFactory::newUnitTestingHelper();

		$instrumentation = $this->newInstrumentation( null, true, $statsHelper->getStatsFactory() );
		$instrumentation->$functionName( ...$functionArgs );

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
			'functionArgs' => [ 'username' ],
		];
		yield 'onUserNotFound' => [
			'functionName' => 'onUserNotFound',
			'counterName' => 'userinfocard_api_user_not_found',
			'functionArgs' => [ 'username' ],
		];
		yield 'onRateLimited' => [
			'functionName' => 'onRateLimited',
			'counterName' => 'userinfocard_api_rate_limit',
			'functionArgs' => [],
		];
	}

	/** @dataProvider provideFunctionEmitsEvent */
	public function testFunctionEmitsEvent(
		string $functionName,
		string $eventName,
		array $functionArgs,
		array $expectedContext
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );

		$mockClient = $this->createMock( MetricsClient::class );
		$mockClient->expects( $this->once() )
			->method( 'submitInteraction' )
			->with(
				'mediawiki.product_metrics.user_info_card_interaction',
				'/analytics/product_metrics/web/base/2.0.0',
				$eventName,
				[ 'action_context' => json_encode( $expectedContext ) ]
			);

		$mockFactory = $this->createMock( MetricsClientFactory::class );
		$mockFactory->method( 'newMetricsClient' )->willReturn( $mockClient );

		$this->newInstrumentation( $mockFactory )
			->$functionName( ...$functionArgs );
	}

	/** @dataProvider provideFunctionEmitsEvent */
	public function testFunctionDoesntEmitEventWhenConfigDisabled(
		string $functionName,
		string $eventName,
		array $functionArgs,
		array $expectedContext
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );

		$mockFactory = $this->createMock( MetricsClientFactory::class );
		$mockFactory->expects( $this->never() )->method( 'newMetricsClient' );

		$instrumentation = $this->newInstrumentation( $mockFactory, false );
		$instrumentation->$functionName( ...$functionArgs );
	}

	/** @dataProvider provideSetSourcePage */
	public function testSetSourcePage( ?string $prefixedTitle, ?string $expectedTitle ) {
		$parentContext = new RequestContext();
		$parentContext->setTitle( Title::makeTitle( NS_MAIN, 'Page without a source page' ) );
		$context = new DerivativeContext( $parentContext );

		$this->newInstrumentation( null, true, null, $context )
			->setSourcePage( $prefixedTitle );

		$this->assertSame(
			$expectedTitle ?? 'Page without a source page',
			$context->getTitle()->getPrefixedText()
		);
	}

	public static function provideSetSourcePage(): iterable {
		yield 'article page' => [ 'Main Page', 'Main Page' ];
		yield 'title with underscores' => [ 'Project:Sandbox_page', 'Project:Sandbox page' ];
		yield 'special page' => [ 'Special:RecentChanges', 'Special:RecentChanges' ];
		yield 'no source page' => [ null, null ];
		yield 'title which cannot be parsed' => [ '<invalid>', null ];
	}

	public function testSetSourcePageForTitleOnAnotherWiki() {
		$parentContext = new RequestContext();
		$parentContext->setTitle( Title::makeTitle( NS_MAIN, 'Page without a source page' ) );
		$context = new DerivativeContext( $parentContext );

		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromText' )
			->willReturn( Title::makeTitle( NS_MAIN, 'Main Page', '', 'testwiki' ) );

		$this->newInstrumentation( null, true, null, $context, $titleFactory )
			->setSourcePage( 'testwiki:Main Page' );

		$this->assertSame( 'Page without a source page', $context->getTitle()->getPrefixedText() );
	}

	public static function provideFunctionEmitsEvent(): iterable {
		yield 'onApiSuccess' => [
			'functionName' => 'onApiSuccess',
			'eventName' => 'api_request',
			'functionArgs' => [ 'User123' ],
			'expectedContext' => [ 'username' => 'User123' ],
		];
		yield 'onUserNotFound' => [
			'functionName' => 'onUserNotFound',
			'eventName' => 'user_not_found',
			'functionArgs' => [ 'User123' ],
			'expectedContext' => [ 'username' => 'User123' ],
		];
		yield 'onRateLimited' => [
			'functionName' => 'onRateLimited',
			'eventName' => 'rate_limit_exceeded',
			'functionArgs' => [],
			'expectedContext' => [],
		];
	}
}
