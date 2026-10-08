<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;

/**
 * Gets the URL to Special:MultiLock, as configured by $wgCheckUserCAMultiLock.
 */
class CheckUserMultiLockUrlLookup {

	/**
	 * @internal For use by ServiceWiring
	 */
	public const array CONSTRUCTOR_OPTIONS = [ 'CheckUserCAMultiLock' ];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Returns the URL to Special:MultiLock if the user is in one of the global groups
	 * configured in $wgCheckUserCAMultiLock, otherwise null.
	 *
	 * The URL is on the configured central wiki. If no URL can be generated for that wiki,
	 * the URL of the local Special:MultiLock is used instead.
	 */
	public function getMultiLockUrl( UserIdentity $user ): ?string {
		$checkUserCAMultiLock = $this->options->get( 'CheckUserCAMultiLock' );
		if ( $checkUserCAMultiLock === false ) {
			return null;
		}

		if ( !$this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			throw new ConfigException( '$wgCheckUserCAMultiLock requires CentralAuth extension.' );
		}

		$caUserGroups = CentralAuthUser::getInstance( $user )->getGlobalGroups();
		if ( !array_intersect( $checkUserCAMultiLock['groups'], $caUserGroups ) ) {
			return null;
		}

		$centralMLUrl = WikiMap::getForeignURL(
			$checkUserCAMultiLock['centralDB'],
			// Use canonical name instead of local name so that it works
			// even if the local language is different from central wiki
			'Special:MultiLock'
		);
		if ( $centralMLUrl === false ) {
			return SpecialPage::getTitleFor( 'MultiLock' )->getLinkURL();
		}
		return $centralMLUrl;
	}
}
