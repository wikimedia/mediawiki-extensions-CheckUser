<?php

namespace MediaWiki\CheckUser\Jobs;

use Job;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\MediaWikiServices;

/**
 * Log when a user views the IP addresses of a temporary account or the user views the temporary accounts
 * associated with a given IP or IP range.
 */
class LogTemporaryAccountAccessJob extends Job {
	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'checkuserLogTemporaryAccountAccess', $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();

		$performer = $services
			->getUserIdentityLookup()
			->getUserIdentityByName( $this->params['performer'] );
		$target = $this->params['target'];
		$timestamp = $this->params['timestamp'];
		$type = $this->params['type'];

		if ( !$performer ) {
			$this->setLastError( 'Invalid performer' );
			return false;
		}

		/** @var TemporaryAccountLogger $logger */
		$logger = $services
			->get( 'CheckUserTemporaryAccountLoggerFactory' )
			->getLogger();
		if ( $type === TemporaryAccountLogger::ACTION_VIEW_IPS ) {
			$logger->logViewIPs( $performer, $target, $timestamp );
		} elseif ( $type === TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP ) {
			$logger->logViewTemporaryAccountsOnIP( $performer, $target, $timestamp );
		} else {
			$this->setLastError( "Invalid type '$type'" );
			return false;
		}

		return true;
	}
}
