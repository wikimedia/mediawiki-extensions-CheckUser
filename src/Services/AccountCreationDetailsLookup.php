<?php

namespace MediaWiki\CheckUser\Services;

use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class AccountCreationDetailsLookup {

	private LoggerInterface $logger;

	public function __construct(
		LoggerInterface $logger
	) {
		$this->logger = $logger;
	}

	/**
	 *
	 * @param string $username the name of the user as stored in a local or central database
	 * @param IReadableDatabase $dbr
	 *
	 * @return IResultWrapper
	 */
	public function getIPAndUserAgentFromDB( string $username, IReadableDatabase $dbr ) {
		// events will be logged in the private event table unless $wgNewUserLog is true,
		// and config can be changed at any time, so we must check both there and the public
		// log event table.
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'cupe_ip_hex', 'cupe_agent' ] )
			->from( 'cu_private_event' )
			->join( 'actor', null, [ 'cupe_actor = actor_id' ] )
			->where( $dbr->expr( 'cupe_log_action', '=', [ 'create-account', 'autocreate-account' ] ) )
			->andWhere( $dbr->expr( 'actor_name', '=', $username ) )
			->limit( 2 )
			->caller( __METHOD__ )
			->fetchResultSet();
		if ( $result->numRows() ) {
			return $result;
		}
		return $dbr->newSelectQueryBuilder()
			->select( [ 'cule_ip_hex', 'cule_agent' ] )
			->from( 'cu_log_event' )
			->join( 'actor', null, [ 'cule_actor = actor_id' ] )
			->join( 'logging', null, [ 'cule_log_id = log_id' ] )
			->where( $dbr->expr( 'log_action', '=', 'create' ) )
			->andWhere( $dbr->expr( 'actor_name', '=', $username ) )
			->limit( 2 )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * Returns the ip and user agent associated with the account creation for the given user,
	 * or null if none can be found
	 *
	 * @param string $username the name of the user as stored in a local or central database
	 * @param IReadableDatabase $dbr
	 * @return array{ip: string, agent: string}|null
	 */
	public function getAccountCreationIPAndUserAgent( string $username, IReadableDatabase $dbr ) {
		$result = $this->getIPAndUserAgentFromDB( $username, $dbr );

		if ( $result->numRows() == 0 ) {
			# probably older than the checkuser keep timeframe
			return null;
		} elseif ( $result->numRows() > 1 ) {
			# not sure what this could mean, dunno if worth logging
			$this->logger->warning( "More than one account creation entry for user $username on a specific wiki" );
		}
		foreach ( $result as $row ) {
			if ( isset( $row->cupe_ip_hex ) ) {
				return [ 'ip' => IPUtils::formatHex( $row->cupe_ip_hex ), 'agent' => $row->cupe_agent ];
			} else {
				return [ 'ip' => IPUtils::formatHex( $row->cule_ip_hex ), 'agent' => $row->cule_agent ];
			}
		}
	}

}
