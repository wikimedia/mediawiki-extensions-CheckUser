<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Api\Rest;

use MediaWikiUnitTestCase;
use ReflectionClass;

/**
 * Unit tests to validate that all CheckUser REST API module definitions registered in
 * extension.json have a correct structure and have handler configurations consistent with the code.
 *
 * @coversNothing
 * @group CheckUser
 */
class CheckUserRestModuleTest extends MediaWikiUnitTestCase {

	/**
	 * Data provider for REST module definition files registered in extension.json.
	 *
	 * @return array Array of [ string $relativeFilePath, array $moduleData ]
	 */
	public static function provideRestModules(): array {
		$extensionJsonPath = dirname( __DIR__, 5 ) . '/extension.json';
		if ( !file_exists( $extensionJsonPath ) ) {
			return [];
		}

		$extensionData = json_decode( file_get_contents( $extensionJsonPath ), true );
		if ( !is_array( $extensionData ) || empty( $extensionData['RestModuleFiles'] ) ) {
			return [];
		}

		$cases = [];
		foreach ( $extensionData['RestModuleFiles'] as $file ) {
			$filePath = dirname( __DIR__, 5 ) . '/' . $file;
			if ( !file_exists( $filePath ) ) {
				$cases[$file] = [ $file, null ];
				continue;
			}
			$content = file_get_contents( $filePath );
			$data = json_decode( $content, true );
			$cases[$file] = [ $file, is_array( $data ) ? $data : [] ];
		}

		return $cases;
	}

	/**
	 * Test essential metadata in the module definition file.
	 *
	 * @dataProvider provideRestModules
	 * @param string $file
	 * @param array|null $data
	 */
	public function testModuleMetadata( string $file, ?array $data ) {
		$this->assertNotNull( $data, "$file must exist and be valid JSON" );

		$basename = basename( $file, '.json' );
		$expectedModuleId = str_replace( '.', '/', $basename );
		$this->assertSame(
			$expectedModuleId,
			$data['moduleId'] ?? null,
			"moduleId in $file must match $expectedModuleId"
		);

		// Validate mwapi version format and ensure its schema file exists in core docs/rest/
		$mwapi = $data['mwapi'] ?? null;
		$this->assertIsString( $mwapi, "mwapi in $file must be a string" );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $mwapi, "mwapi in $file must be in X.Y.Z format" );

		$parts = explode( '.', $mwapi );
		$majorMinor = $parts[0] . '.' . $parts[1];
		$corePath = getenv( 'MW_INSTALL_PATH' );
		$schemaPath = "$corePath/docs/rest/mwapi-$majorMinor.json";
		$this->assertFileExists(
			$schemaPath,
			"The schema file docs/rest/mwapi-$majorMinor.json corresponding " .
			"to mwapi version '$mwapi' in $file must exist"
		);

		// Validate external documentation settings are present
		$this->assertArrayHasKey( 'externalDocs', $data, "externalDocs in $file must be defined" );
		$this->assertNotEmpty( $data['externalDocs']['url'] ?? '', "externalDocs url in $file must not be empty" );
		$this->assertNotEmpty(
			$data['externalDocs']['description'] ?? '',
			"externalDocs description in $file must not be empty"
		);

		// Validate that the info object contains required fields
		$this->assertArrayHasKey( 'info', $data, "info object in $file must be defined" );
		$this->assertNotEmpty( $data['info']['version'] ?? '', "info version in $file must not be empty" );

		$expectedTitleKey = "rest-module-$basename-title";
		$expectedDescKey = "rest-module-$basename-desc";
		$this->assertSame(
			$expectedTitleKey,
			$data['info']['x-i18n-title'] ?? null,
			"x-i18n-title in $file must match $expectedTitleKey"
		);
		$this->assertSame(
			$expectedDescKey,
			$data['info']['x-i18n-description'] ?? null,
			"x-i18n-description in $file must match $expectedDescKey"
		);
	}

	/**
	 * Test that the localization keys defined in the info block exist in the api/en.json file.
	 *
	 * @dataProvider provideRestModules
	 * @param string $file
	 * @param array|null $data
	 */
	public function testLocalizationKeysExist( string $file, ?array $data ) {
		$this->assertNotNull( $data, "$file must exist and be valid JSON" );
		$enApiJsonPath = dirname( __DIR__, 5 ) . '/i18n/api/en.json';
		$this->assertFileExists( $enApiJsonPath, 'i18n/api/en.json file must exist' );

		$enApiData = json_decode( file_get_contents( $enApiJsonPath ), true );
		$this->assertIsArray( $enApiData, 'i18n/api/en.json must be valid JSON' );

		$titleKey = $data['info']['x-i18n-title'] ?? '';
		$descKey = $data['info']['x-i18n-description'] ?? '';

		$this->assertNotEmpty( $titleKey, "x-i18n-title in $file must not be empty" );
		$this->assertNotEmpty( $descKey, "x-i18n-description in $file must not be empty" );

		$this->assertArrayHasKey( $titleKey, $enApiData, "Localization key '$titleKey' must exist in api/en.json" );
		$this->assertArrayHasKey( $descKey, $enApiData, "Localization key '$descKey' must exist in api/en.json" );

		// Check path keys
		if ( isset( $data['paths'] ) ) {
			foreach ( $data['paths'] as $path => $methods ) {
				foreach ( $methods as $method => $op ) {
					if ( isset( $op['x-i18n-summary'] ) ) {
						$this->assertArrayHasKey(
							$op['x-i18n-summary'],
							$enApiData,
							"Localization key '{$op['x-i18n-summary']}' must exist in api/en.json"
						);
					}
					if ( isset( $op['x-i18n-description'] ) ) {
						$this->assertArrayHasKey(
							$op['x-i18n-description'],
							$enApiData,
							"Localization key '{$op['x-i18n-description']}' must exist in api/en.json"
						);
					}
				}
			}
		}
	}

	/**
	 * Test that all endpoint paths have summaries, descriptions, and valid handler class configs.
	 *
	 * @dataProvider provideRestModules
	 * @param string $file
	 * @param array|null $data
	 */
	public function testPathsConfiguration( string $file, ?array $data ) {
		$this->assertNotNull( $data, "$file must exist and be valid JSON" );
		$this->assertArrayHasKey( 'paths', $data, "paths in $file must be defined" );

		foreach ( $data['paths'] as $path => $methods ) {
			$this->assertNotEmpty( $path, "path template in $file must not be empty" );
			$this->assertIsArray( $methods, "methods for path $path in $file must be an array" );

			foreach ( $methods as $method => $op ) {
				$this->assertContains(
					strtolower( $method ),
					[ 'get', 'post', 'put', 'delete', 'patch' ],
					"HTTP method $method on path $path in $file is unsupported"
				);

				// Validate summaries and descriptions
				$this->assertArrayHasKey(
					'x-i18n-summary',
					$op,
					"Operation $method on path $path in $file must have x-i18n-summary"
				);
				$this->assertNotEmpty(
					$op['x-i18n-summary'],
					"x-i18n-summary for $method on path $path in $file must not be empty"
				);

				$this->assertArrayHasKey(
					'x-i18n-description',
					$op,
					"Operation $method on path $path in $file must have x-i18n-description"
				);
				$this->assertNotEmpty(
					$op['x-i18n-description'],
					"x-i18n-description for $method on path $path in $file must not be empty"
				);

				// Validate handler configuration
				$this->assertArrayHasKey(
					'handler',
					$op,
					"Operation $method on path $path in $file must specify a handler"
				);
				$handlerClass = $op['handler']['class'] ?? null;
				$this->assertNotNull( $handlerClass, "Path $path $method in $file specifies handler class" );
				$this->assertTrue( class_exists( $handlerClass ), "Handler class $handlerClass in $file exists" );
			}
		}
	}

	/**
	 * Test that the constructor parameter count of each handler class matches the number of services
	 * declared under 'services' in the module JSON file.
	 *
	 * @dataProvider provideRestModules
	 * @param string $file
	 * @param array|null $data
	 */
	public function testHandlerConstructorsMatchServices( string $file, ?array $data ) {
		$this->assertNotNull( $data, "$file must exist and be valid JSON" );
		$this->assertArrayHasKey( 'paths', $data, "paths in $file must be defined" );

		foreach ( $data['paths'] as $path => $methods ) {
			foreach ( $methods as $method => $op ) {
				$handlerClass = $op['handler']['class'];
				$declaredServices = $op['handler']['services'] ?? [];

				$reflection = new ReflectionClass( $handlerClass );
				$constructor = $reflection->getConstructor();

				if ( count( $declaredServices ) === 0 ) {
					// If no services are declared, check if the constructor expects 0 parameters (or is null)
					$paramCount = $constructor ? $constructor->getNumberOfParameters() : 0;
					$this->assertSame(
						0,
						$paramCount,
						"Handler class $handlerClass in $file has constructor parameters " .
						"but no services are declared in JSON"
					);
				} else {
					$this->assertNotNull(
						$constructor,
						"Handler class $handlerClass in $file has declared services in JSON " .
						"but does not define a constructor"
					);
					$paramCount = $constructor->getNumberOfParameters();
					$this->assertSame(
						count( $declaredServices ),
						$paramCount,
						"The number of declared services in $file for $handlerClass (" .
						count( $declaredServices ) . ") " .
						"does not match the constructor parameter count ($paramCount)"
					);
				}
			}
		}
	}
}
