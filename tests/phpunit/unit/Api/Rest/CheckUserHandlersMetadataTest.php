<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Api\Rest;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\ConnectedTemporaryAccountsHandler;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UpdateCaseHandler;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountHandler;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountIPHandler;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoBlockedHandler;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoHandler;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\ResponseFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Unit tests to validate the OpenAPI metadata code added to all CheckUser REST API handlers.
 *
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\ConnectedTemporaryAccountsHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UpdateCaseHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountIPHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoBlockedHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoHandler
 */
class CheckUserHandlersMetadataTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	/**
	 * Data provider for the handler classes and the HTTP methods they support.
	 *
	 * @return array Array of [ string $handlerClass, string $httpMethod ]
	 */
	public static function provideHandlers(): array {
		return [
			'SuggestedInvestigations\UpdateCaseHandler' => [
				UpdateCaseHandler::class,
				'post',
			],
			'UserInfoHandler' => [
				UserInfoHandler::class,
				'post',
			],
			'UserInfoBlockedHandler' => [
				UserInfoBlockedHandler::class,
				'get',
			],
			'TemporaryAccountHandler' => [
				TemporaryAccountHandler::class,
				'post',
			],
			'TemporaryAccountIPHandler' => [
				TemporaryAccountIPHandler::class,
				'post',
			],
			'BatchTemporaryAccountHandler' => [
				BatchTemporaryAccountHandler::class,
				'post',
			],
			'ConnectedTemporaryAccountsHandler' => [
				ConnectedTemporaryAccountsHandler::class,
				'post',
			],
			'UserAgentClientHintsHandler' => [
				UserAgentClientHintsHandler::class,
				'post',
			],
		];
	}

	/**
	 * Dynamically instantiate a handler class by generating mocks for all of its
	 * constructor arguments.
	 *
	 * @return Handler
	 */
	private function createHandlerInstance( string $handlerClass ): Handler {
		$instance = $this->newServiceInstance( $handlerClass, [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
				'CheckUserExpiredIdsLookupService' => true,
				'CheckUserMaximumRowCount' => 5000,
			] ),
		] );

		// Inject mock responseFactory on base class Handler
		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->method( 'getFormattedMessage' )
			->willReturnCallback( static function ( $messageValue ) {
				return 'localized-' . $messageValue->getKey();
			} );
		TestingAccessWrapper::newFromObject( $instance )->responseFactory = $responseFactory;

		return $instance;
	}

	/**
	 * Test that getRequestSpec returns a valid OpenAPI request spec array with required properties.
	 *
	 * @dataProvider provideHandlers
	 */
	public function testGetRequestSpec( string $handlerClass, string $httpMethod ): void {
		$instance = $this->createHandlerInstance( $handlerClass );

		$spec = TestingAccessWrapper::newFromObject( $instance )->getRequestSpec( $httpMethod );

		// Only POST/PUT methods have a request body spec in these handlers
		if ( strtolower( $httpMethod ) === 'post' ) {
			$this->assertIsArray(
				$spec,
				"Request spec for $handlerClass must be an array"
			);
			$this->assertArrayHasKey(
				'description',
				$spec,
				"Request spec for $handlerClass must have a 'description'"
			);
			$this->assertNotEmpty(
				$spec['description'],
				"Request spec description for $handlerClass must not be empty"
			);

			$this->assertArrayHasKey(
				'content',
				$spec,
				"Request spec for $handlerClass must have a 'content' key"
			);
			$this->assertArrayHasKey(
				'application/json',
				$spec['content'],
				"Request spec for $handlerClass must support 'application/json'"
			);

			$jsonContent = $spec['content']['application/json'];
			$this->assertArrayHasKey(
				'example',
				$jsonContent,
				"Request spec content for $handlerClass must have an 'example'"
			);
			$this->assertNotEmpty(
				$jsonContent['example'],
				"Request spec example for $handlerClass must not be empty"
			);
		} else {
			// GET requests typically do not have a request body specification
			$this->assertNull(
				$spec,
				"GET request for $handlerClass should not have a request spec"
			);
		}
	}

	/**
	 * Test that getResponseBodySchema returns a valid OpenAPI response body schema.
	 *
	 * @dataProvider provideHandlers
	 */
	public function testGetResponseBodySchema( string $handlerClass, string $httpMethod ): void {
		$instance = $this->createHandlerInstance( $handlerClass );

		$schema = TestingAccessWrapper::newFromObject( $instance )->getResponseBodySchema( $httpMethod );

		$this->assertIsArray(
			$schema,
			"Response body schema for $handlerClass must be an array"
		);
		$this->assertArrayHasKey(
			'type',
			$schema,
			"Response schema for $handlerClass must have a 'type'"
		);
		$this->assertContains(
			$schema['type'],
			[ 'object', 'array' ],
			"Response schema type for $handlerClass must be 'object' or 'array'"
		);

		$hasDesc = array_key_exists( 'description', $schema ) ||
			array_key_exists( 'x-i18n-description', $schema );
		$this->assertTrue(
			$hasDesc,
			"Response schema for $handlerClass must have a 'description' or 'x-i18n-description'"
		);
		$descVal = $schema['description'] ?? $schema['x-i18n-description'];
		$this->assertNotEmpty(
			$descVal,
			"Response schema description for $handlerClass must not be empty"
		);

		if ( $schema['type'] === 'object' ) {
			$this->assertArrayHasKey(
				'properties',
				$schema,
				"Response schema for $handlerClass must have 'properties'"
			);
			$this->assertIsArray(
				$schema['properties'],
				"Response schema properties for $handlerClass must be an array"
			);
			$this->assertNotEmpty(
				$schema['properties'],
				"Response schema properties for $handlerClass must not be empty"
			);

			// Check description for each property
			foreach ( $schema['properties'] as $propName => $propInfo ) {
				$this->assertArrayHasKey(
					'type',
					$propInfo,
					"Property '$propName' in response schema of $handlerClass must have a 'type'"
				);
				$hasPropDesc = array_key_exists( 'description', $propInfo ) ||
					array_key_exists( 'x-i18n-description', $propInfo );
				$this->assertTrue(
					$hasPropDesc,
					"Property '$propName' in response schema of $handlerClass " .
						"must have a 'description' or 'x-i18n-description'"
				);
				$propDescVal = $propInfo['description'] ?? $propInfo['x-i18n-description'];
				$this->assertNotEmpty(
					$propDescVal,
					"Property '$propName' description in response schema of $handlerClass must not be empty"
				);
			}
		} else {
			$this->assertArrayHasKey(
				'items',
				$schema,
				"Response schema for $handlerClass must have 'items' because it is of type 'array'"
			);
			$this->assertIsArray(
				$schema['items'],
				"Response schema items for $handlerClass must be an array"
			);
			$this->assertArrayHasKey(
				'type',
				$schema['items'],
				"Items in response schema of $handlerClass must have a 'type'"
			);
			$hasItemsDesc = array_key_exists( 'description', $schema['items'] ) ||
				array_key_exists( 'x-i18n-description', $schema['items'] );
			$this->assertTrue(
				$hasItemsDesc,
				"Items in response schema of $handlerClass must have a 'description' or 'x-i18n-description'"
			);
			$itemsDescVal = $schema['items']['description'] ?? $schema['items']['x-i18n-description'];
			$this->assertNotEmpty(
				$itemsDescVal,
				"Items description in response schema of $handlerClass must not be empty"
			);
		}

		$this->assertArrayHasKey(
			'example',
			$schema,
			"Response schema for $handlerClass must have an 'example'"
		);
		$this->assertNotEmpty(
			$schema['example'],
			"Response schema example for $handlerClass must not be empty"
		);
	}

	/**
	 * Test that parameter definitions contain descriptions and examples.
	 *
	 * @dataProvider provideHandlers
	 */
	public function testParamSettingsContainDescriptionAndExample( string $handlerClass, string $httpMethod ): void {
		$instance = $this->createHandlerInstance( $handlerClass );

		// Check path/query parameters
		$paramSettings = $instance->getParamSettings();
		$this->assertIsArray(
			$paramSettings,
			"getParamSettings of $handlerClass must return an array"
		);
		foreach ( $paramSettings as $paramName => $settings ) {
			if ( $paramName === 'token' ) {
				continue;
			}
			$this->assertArrayHasKey(
				'rest-param-description',
				$settings,
				"Parameter '$paramName' in getParamSettings of $handlerClass is missing description"
			);
			$this->assertNotEmpty(
				$settings['rest-param-description'],
				"Parameter '$paramName' description in getParamSettings of $handlerClass must not be empty"
			);

			$this->assertArrayHasKey(
				'rest-param-example',
				$settings,
				"Parameter '$paramName' in getParamSettings of $handlerClass is missing example"
			);
			$this->assertNotNull(
				$settings['rest-param-example'],
				"Parameter '$paramName' example in getParamSettings of $handlerClass must not be null"
			);
			$this->assertNotSame(
				'',
				$settings['rest-param-example'],
				"Parameter '$paramName' example in getParamSettings of $handlerClass must not be empty"
			);
		}

		// Check body parameters
		$bodyParamSettings = $instance->getBodyParamSettings();
		$this->assertIsArray(
			$bodyParamSettings,
			"getBodyParamSettings of $handlerClass must return an array"
		);
		foreach ( $bodyParamSettings as $paramName => $settings ) {
			if ( $paramName === 'token' ) {
				continue;
			}
			$this->assertArrayHasKey(
				'rest-param-description',
				$settings,
				"Parameter '$paramName' in getBodyParamSettings of $handlerClass is missing description"
			);
			$this->assertNotEmpty(
				$settings['rest-param-description'],
				"Parameter '$paramName' description in getBodyParamSettings of $handlerClass must not be empty"
			);

			$this->assertArrayHasKey(
				'rest-param-example',
				$settings,
				"Parameter '$paramName' in getBodyParamSettings of $handlerClass is missing example"
			);
			$this->assertNotNull(
				$settings['rest-param-example'],
				"Parameter '$paramName' example in getBodyParamSettings of $handlerClass must not be null"
			);
			$this->assertNotSame(
				'',
				$settings['rest-param-example'],
				"Parameter '$paramName' example in getBodyParamSettings of $handlerClass must not be empty"
			);
		}
	}
}
