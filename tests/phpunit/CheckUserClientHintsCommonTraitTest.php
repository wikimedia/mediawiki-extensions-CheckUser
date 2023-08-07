<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;

/**
 * A helper trait used by classes that need to get an example ClientHintsData object or
 * an example JS API response.
 */
trait CheckUserClientHintsCommonTraitTest {
	/**
	 * To not specify anything for $brands or $fullVersionList, use an empty
	 * array. The value of null will use the default value.
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
	 * To not specify anything for $brands or $fullVersionList, use an empty
	 * array. The value of null will use the default value.
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
