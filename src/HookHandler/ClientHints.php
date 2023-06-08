<?php

namespace MediaWiki\CheckUser\HookHandler;

use Config;
use MediaWiki\Hook\BeforePageDisplayHook;
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
		$request = $special->getRequest();
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			return;
		}

		if ( in_array( $special->getName(), $this->config->get( 'CheckUserClientHintsSpecialPages' ) ) ) {
			$request->response()->header( $this->getClientHintsHeaderString() );
		} elseif ( $this->config->get( 'CheckUserClientHintsUnsetHeaderWhenPossible' ) ) {
			$request->response()->header( $this->getEmptyClientHintsHeaderString() );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		$request = $out->getRequest();
		if ( $out->getTitle()->isSpecialPage() || !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			// We handle special pages in BeforeSpecialPageBeforeExecute.
			return;
		}

		if ( in_array(
			$request->getRawVal( 'action' ),
			$this->config->get( 'CheckUserClientHintsActionQueryParameter' )
		) ) {
			$request->response()->header( $this->getClientHintsHeaderString() );
		} elseif ( $this->config->get( 'CheckUserClientHintsUnsetHeaderWhenPossible' ) ) {
			$request->response()->header( $this->getEmptyClientHintsHeaderString() );
		}
	}

	/**
	 * Get the list of headers to use with Accept-CH.
	 *
	 * @return string
	 */
	private function getClientHintsHeaderString(): string {
		$headers = implode(
			', ',
			$this->config->get( 'CheckUserClientHintsHeaders' )
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
