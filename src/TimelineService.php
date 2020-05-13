<?php

namespace MediaWiki\CheckUser;

use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class TimelineService {
	/** @var ILoadBalancer */
	private $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Get timeline query info
	 *
	 * @param string[] $targets
	 * @return array
	 */
	public function getQueryInfo( array $targets ): array {
		// TODO: Add timestamp conditions (T246261)
		$conds = [ $this->buildUserConds( $targets ) ];
		return [
			'tables' => 'cu_changes',
			'fields' => [
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment',
				'cuc_actiontext', 'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type',
				'cuc_this_oldid', 'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent', 'cuc_id',
			],
			'conds' => $conds,
		];
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string[] $targets
	 * @return string
	 */
	private function buildUserConds( array $targets ) : string {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );

		$conds = [];
		foreach ( $targets as $target ) {
			if ( IPUtils::isIpAddress( $target ) ) {
				if ( IPUtils::isValid( $target ) ) {
					$conds['cuc_ip_hex'][] = IPUtils::toHex( $target );
				} elseif ( IPUtils::isValidRange( $target ) ) {
					$range = IPUtils::parseRange( $target );
					$conds[] = $db->makeList( [
						'cuc_ip_hex >= ' . $db->addQuotes( $range[0] ),
						'cuc_ip_hex <= ' . $db->addQuotes( $range[1] )
					], IDatabase::LIST_AND );
				}
			} else {
				$userId = $this->getUserId( $target );
				if ( $userId ) {
					$conds['cuc_user'][] = $userId;
				}
			}
		}

		return $conds ? $db->makeList( $conds, IDatabase::LIST_OR ) : $db->addQuotes( false );
	}

	/**
	 * Get user ID from a user name; for mocking in tests.
	 *
	 * @param string $username
	 * @return int|null Id, or null if the username is invalid or non-existent
	 */
	protected function getUserId( $username ) : ?int {
		return User::idFromName( $username );
	}
}
