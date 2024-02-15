<?php

namespace MediaWiki\CheckUser\Tests\Unit\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler;
use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountRevisionHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Rest\Validator\UnsupportedContentTypeBodyValidator;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler
 */
class AbstractTemporaryAccountHandlerTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	/** @dataProvider provideBodyValidator */
	public function testBodyValidator( $contentType, $expectedBodyValidatorClassName ) {
		// We cannot construct a AbstractTemporaryAccountHandler directly as the class is abstract,
		// so use TemporaryAccountRevisionHandler which shouldn't override the body validator method.
		/** @var AbstractTemporaryAccountHandler $handler */
		$objectUnderTest = $this->newServiceInstance( TemporaryAccountRevisionHandler::class, [] );
		$bodyValidator = $objectUnderTest->getBodyValidator( $contentType );
		$this->assertInstanceOf(
			$expectedBodyValidatorClassName,
			$bodyValidator,
			"Expected body validator for content type $contentType to be $expectedBodyValidatorClassName"
		);
	}

	public static function provideBodyValidator() {
		return [
			'JSON content type' => [ 'application/json', JsonBodyValidator::class ],
			'Plaintext content type' => [ 'text/plain', UnsupportedContentTypeBodyValidator::class ],
			'Form data content type' => [
				'application/x-www-form-urlencoded',
				UnsupportedContentTypeBodyValidator::class
			],
		];
	}
}
