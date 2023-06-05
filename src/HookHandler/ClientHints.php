<?php

namespace MediaWiki\CheckUser\HookHandler;

use Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;

/**
 * HookHandler for entry points related to requesting User-Agent Client Hints data.
 */
class ClientHints implements SpecialPageBeforeExecuteHook, BeforePageDisplayHook {

	// User-Agent Client Hints headers to request.
	// See the list of valid values at https://wicg.github.io/ua-client-hints
	public const CLIENT_HINT_HEADERS = [
		'Sec-CH-UA',
		'Sec-CH-UA-Arch',
		'Sec-CH-UA-Bitness',
		'Sec-CH-UA-Form-Factor',
		'Sec-CH-UA-Full-Version-List',
		'Sec-CH-UA-Mobile',
		'Sec-CH-UA-Model',
		'Sec-CH-UA-Platform',
		'Sec-CH-UA-Platform-Version',
		'Sec-CH-UA-WoW64'
	];

	private Config $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if ( $this->config->get( 'CheckUserClientHintsEnabled' ) &&
			in_array( $special->getName(), $this->config->get( 'CheckUserClientHintsSpecialPages' ) ) ) {
			$special->getRequest()->response()->header( $this->getClientHintsHeaderString() );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getTitle()->isSpecialPage() ) {
			// We handle special pages in BeforeSpecialPageBeforeExecute.
			return;
		}

		if ( $this->config->get( 'CheckUserClientHintsEnabled' ) &&
			in_array(
				$out->getRequest()->getRawVal( 'action' ),
				$this->config->get( 'CheckUserClientHintsActionQueryParameter' )
			) ) {
			$out->getRequest()->response()->header( $this->getClientHintsHeaderString() );
		}
	}

	private function getClientHintsHeaderString(): string {
		$headers = implode(
			', ',
			self::CLIENT_HINT_HEADERS
		);
		return "Accept-CH: $headers";
	}

}
