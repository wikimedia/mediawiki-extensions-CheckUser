<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;

/**
 * HookHandler for entry points related to requesting User-Agent Client Hints data.
 */
class ClientHints implements SpecialPageBeforeExecuteHook, BeforePageDisplayHook {

	private Config $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			return;
		}

		$request = $special->getRequest();

		$specialPagesList = $this->config->get( 'CheckUserClientHintsSpecialPages' );

		$headerSent = false;
		if ( in_array( $special->getName(), $specialPagesList ) && !$request->wasPosted() ) {
			// If the special page name is a value in the config, then this is the old format and we should
			// consider it as collecting the data via the header.
			$request->response()->header( $this->getClientHintsHeaderString() );
			$headerSent = true;
		} elseif ( array_key_exists( $special->getName(), $specialPagesList ) ) {
			// If the special page name is a key in the config, then the value is the method which the data is
			// collected.
			$type = $specialPagesList[$special->getName()];
			if ( $type === 'js' ) {
				$this->addJsClientHintsModule( $special->getOutput() );
			} elseif ( !$request->wasPosted() ) {
				$request->response()->header( $this->getClientHintsHeaderString() );
				$headerSent = true;
			}
		}

		if ( $this->config->get( 'CheckUserClientHintsUnsetHeaderWhenPossible' ) && !$headerSent ) {
			$request->response()->header( $this->getEmptyClientHintsHeaderString() );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		// We handle special pages in BeforeSpecialPageBeforeExecute.
		if ( $out->getTitle()->isSpecialPage() ||
			// ClientHints is globally disabled
			!$this->config->get( 'CheckUserClientHintsEnabled' )
		) {
			return;
		}

		$this->addJsClientHintsModule( $out );

		if ( $this->config->get( 'CheckUserClientHintsUnsetHeaderWhenPossible' ) ) {
			$request = $out->getRequest();
			$request->response()->header( $this->getEmptyClientHintsHeaderString() );
		}
	}

	/**
	 * Add the JS Client Hints module to the given OutputPage instance.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	private function addJsClientHintsModule( OutputPage $out ): void {
		$out->addJsConfigVars( [
			// Roundabout way to ensure we have a list of values like "architecture", "bitness"
			// etc for use with the client-side JS API. Make sure we get 1) just the values
			// from the configuration, 2) filter out any empty entries, 3) convert to a list
			'wgCheckUserClientHintsHeadersJsApi' => array_values( array_filter( array_values(
				$this->config->get( 'CheckUserClientHintsHeaders' )
			) ) ),
		] );
		$out->addModules( 'ext.checkUser.clientHints' );
	}

	/**
	 * Get the list of headers to use with Accept-CH.
	 *
	 * @return string
	 */
	private function getClientHintsHeaderString(): string {
		$headers = implode(
			', ',
			array_filter( array_keys( $this->config->get( 'CheckUserClientHintsHeaders' ) ) )
		);
		return "Accept-CH: $headers";
	}

	/**
	 * Get an Accept-CH header string to tell the client to stop sending client-hint data.
	 *
	 * @return string
	 */
	private function getEmptyClientHintsHeaderString(): string {
		return "Accept-CH: ";
	}

}
