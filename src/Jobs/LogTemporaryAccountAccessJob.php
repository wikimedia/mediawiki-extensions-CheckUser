<?php

namespace MediaWiki\CheckUser\Jobs;

use Job;
use MediaWiki\MediaWikiServices;

/**
 * Log when a user views the IP addresses of a temporary account.
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
		$tempUser = $this->params['tempUser'];
		$timestamp = $this->params['timestamp'];

		if ( !$performer ) {
			$this->setLastError( 'Invalid performer' );
			return false;
		}

		$logger = $services
			->get( 'CheckUserTemporaryAccountLoggerFactory' )
			->getLogger();
		$logger->logViewIPs( $performer, $tempUser, $timestamp );

		return true;
	}
}
