<?php

namespace MediaWiki\CheckUser\ClientHints;

use JsonSerializable;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;

/**
 * Value object for modeling user agent client hints data.
 */
class ClientHintsData implements JsonSerializable {
	public const HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME = [
		"Sec-CH-UA" => "userAgent",
		"Sec-CH-UA-Arch" => "architecture",
		"Sec-CH-UA-Bitness" => "bitness",
		"Sec-CH-UA-Form-Factor" => "formFactor",
		"Sec-CH-UA-Full-Version-List" => "fullVersionList",
		"Sec-CH-UA-Mobile" => "mobile",
		"Sec-CH-UA-Model" => "model",
		"Sec-CH-UA-Platform" => "platform",
		"Sec-CH-UA-Platform-Version" => "platformVersion",
		"Sec-CH-UA-WoW64" => "woW64"
	];

	private ?string $architecture;
	private ?string $bitness;
	private ?array $brands;
	private ?string $formFactor;
	private ?array $fullVersionList;
	private ?bool $mobile;
	private ?string $model;
	private ?string $platform;
	private ?string $platformVersion;
	private ?string $userAgent;
	private ?bool $woW64;

	/**
	 * @param string|null $architecture
	 * @param string|null $bitness
	 * @param string[][]|null $brands
	 * @param string|null $formFactor
	 * @param string[][]|null $fullVersionList
	 * @param bool|null $mobile
	 * @param string|null $model
	 * @param string|null $platform
	 * @param string|null $platformVersion
	 * @param string|null $userAgent
	 * @param bool|null $woW64
	 */
	public function __construct(
		?string $architecture,
		?string $bitness,
		?array $brands,
		?string $formFactor,
		?array $fullVersionList,
		?bool $mobile,
		?string $model,
		?string $platform,
		?string $platformVersion,
		?string $userAgent,
		?bool $woW64
	) {
		$this->architecture = $architecture;
		$this->bitness = $bitness;
		$this->brands = $brands;
		$this->formFactor = $formFactor;
		$this->fullVersionList = $fullVersionList;
		$this->mobile = $mobile;
		$this->model = $model;
		$this->platform = $platform;
		$this->platformVersion = $platformVersion;
		$this->userAgent = $userAgent;
		$this->woW64 = $woW64;
	}

	/**
	 * Given an array of data received from the client-side JavaScript API for obtaining
	 * user agent client hints, construct a new ClientHintsData object.
	 *
	 * @see UserAgentClientHintsManager::getBodyValidator
	 *
	 * @param array $data
	 * @return ClientHintsData
	 */
	public static function newFromJsApi( array $data ): ClientHintsData {
		return new self(
			$data['architecture'] ?? null,
			$data['bitness'] ?? null,
			$data['brands'] ?? null,
			null,
			$data['fullVersionList'] ?? null,
			$data['mobile'] ?? null,
			$data['model'] ?? null,
			$data['platform'] ?? null,
			$data['platformVersion'] ?? null,
			null,
			null
		);
	}

	/**
	 * @return array[]
	 *  An array of arrays containing maps of uach_name => uach_value items
	 *  to insert into the cu_useragent_clienthints table.
	 */
	public function toDatabaseRows(): array {
		$rows = [];
		foreach ( $this->jsonSerialize() as $key => $value ) {
			if ( !is_array( $value ) ) {
				if ( $value === "" || $value === null ) {
					continue;
				}
				if ( is_bool( $value ) ) {
					$value = $value ? "1" : "0";
				}
				$rows[] = [ 'uach_name' => $key, 'uach_value' => $value ];
			} else {
				// Some values are arrays, for example:
				//  [
				//    "brand": "Not.A/Brand",
				//    "version": "8"
				//  ],
				// We transform these by joining brand/version with a space, e.g. "Not.A/Brand 8"
				foreach ( $value as $item ) {
					$rows[] = [
						'uach_name' => $key,
						'uach_value' => implode( ' ', $item )
					];
				}
			}
		}
		return $rows;
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		return [
			'architecture' => $this->architecture,
			'bitness' => $this->bitness,
			'brands' => $this->brands,
			'formFactor' => $this->formFactor,
			'fullVersionList' => $this->fullVersionList,
			'mobile' => $this->mobile,
			'model' => $this->model,
			'platform' => $this->platform,
			'platformVersion' => $this->platformVersion,
			'userAgent' => $this->userAgent,
			'woW64' => $this->woW64,
		];
	}
}
