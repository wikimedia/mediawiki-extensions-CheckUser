<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;

/**
 * For use by classes that need an example ClientHints object.
 */
trait CheckUserClientHintsCommonTraitTest {
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
		return ClientHintsData::newFromJsApi(
			[
				"architecture" => $architecture,
				"bitness" => $bitness,
				"brands" => $brands,
				"fullVersionList" => $fullVersionList,
				"mobile" => $mobile,
				"model" => $model,
				"platform" => $platform,
				"platformVersion" => $platformVersion
			]
		);
	}
}
