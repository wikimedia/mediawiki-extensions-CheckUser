<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;

/**
 * A helper trait used by classes that need to get an example ClientHintsData object or
 * an example JS API response.
 */
trait CheckUserClientHintsCommonTraitTest {
	/**
	 * Generates example Client Hints data in a format
	 * that would be sent as the request body to the
	 * Client Hints REST API.
	 *
	 * @param string|null $architecture
	 * @param string|null $bitness
	 * @param array|null $brands Provide null to use the default. Provide an empty array for no data.
	 * @param array|null $fullVersionList Provide null to use the default. Provide an empty array for no data.
	 * @param bool|null $mobile
	 * @param string|null $model
	 * @param string|null $platform
	 * @param string|null $platformVersion
	 * @return array Data that can be passed to ClientHintsData::newFromJsApi
	 */
	public static function getExampleClientHintsJsApiResponse(
		?string $architecture = "x86",
		?string $bitness = "64",
		?array $brands = null,
		?array $fullVersionList = null,
		?bool $mobile = false,
		?string $model = "",
		?string $platform = "Windows",
		?string $platformVersion = "15.0.0"
	): array {
		if ( $brands === null ) {
			$brands = [
				[
					"brand" => "Not.A/Brand",
					"version" => "8"
				],
				[
					"brand" => "Chromium",
					"version" => "114"
				],
				[
					"brand" => "Google Chrome",
					"version" => "114"
				]
			];
		}
		if ( $fullVersionList === null ) {
			$fullVersionList = [
				[
					"brand" => "Not.A/Brand",
					"version" => "8.0.0.0"
				],
				[
					"brand" => "Chromium",
					"version" => "114.0.5735.199"
				],
				[
					"brand" => "Google Chrome",
					"version" => "114.0.5735.199"
				]
			];
		}
		return [
			"architecture" => $architecture,
			"bitness" => $bitness,
			"brands" => $brands,
			"fullVersionList" => $fullVersionList,
			"mobile" => $mobile,
			"model" => $model,
			"platform" => $platform,
			"platformVersion" => $platformVersion
		];
	}

	/**
	 * Gets an example ClientHintsData object with example data that is
	 * passed through the ClientHintsData::newFromJsApi method.
	 *
	 * @param string|null $architecture
	 * @param string|null $bitness
	 * @param array|null $brands Provide null to use the default. Provide an empty array for no data.
	 * @param array|null $fullVersionList Provide null to use the default. Provide an empty array for no data.
	 * @param bool|null $mobile
	 * @param string|null $model
	 * @param string|null $platform
	 * @param string|null $platformVersion
	 * @return ClientHintsData
	 */
	public static function getExampleClientHintsDataObjectFromJsApi(
		?string $architecture = "x86",
		?string $bitness = "64",
		?array $brands = null,
		?array $fullVersionList = null,
		?bool $mobile = false,
		?string $model = "",
		?string $platform = "Windows",
		?string $platformVersion = "15.0.0"
	): ClientHintsData {
		return ClientHintsData::newFromJsApi(
			self::getExampleClientHintsJsApiResponse(
				$architecture,
				$bitness,
				$brands,
				$fullVersionList,
				$mobile,
				$model,
				$platform,
				$platformVersion
			)
		);
	}
}
