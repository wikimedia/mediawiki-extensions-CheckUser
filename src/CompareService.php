<?php

namespace MediaWiki\CheckUser;

use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class CompareService {

	/** @var ILoadBalancer */
	private $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Get edits made from an ip
	 *
	 * @param string $ip
	 * @param string|null $excludeUser
	 * @return array
	 */
	public function getTotalEditsFromIp(
		string $ip,
		string $excludeUser = null
	): array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$conds = [
			'cuc_ip' => $ip,
			'cuc_type' => [ RC_EDIT, RC_NEW ],
		];

		if ( $excludeUser ) {
			$conds[] = 'cuc_user_text != ' . $db->addQuotes( $excludeUser );
		}

		$data = $db->selectRow(
			'cu_changes',
			[
				'total_edits' => 'COUNT(*)',
				'total_users' => 'COUNT(distinct cuc_user_text)'
			],
			$conds,
			__METHOD__
		);

		return $data ? (array)$data : [];
	}

	/**
	 * Get the compare query info
	 *
	 * @param string[]|UserIdentity[] $users
	 * @return array
	 */
	public function getQueryInfo( array $users ): array {
		return [
			'fields' => [
				'cuc_user',
				'cuc_user_text',
				'cuc_ip',
				'cuc_ip_hex',
				'first_edit' => 'MIN(cuc_timestamp)',
				'last_edit' => 'MAX(cuc_timestamp)',
				'total_edits' => 'count(*)',
				'cuc_agent',
			],
			'tables' => 'cu_changes',
			'conds' => [
				'cuc_type' => [ RC_EDIT, RC_NEW ],
				$this->buildUserPredicate( $users ),
			],
			'options' => [
				'GROUP BY' => [
					'cuc_user_text',
					'cuc_ip',
					'cuc_agent',
				],
			],
		];
	}

	/**
	 * Builds a query predicate depending on what type of
	 * users are being passed in
	 *
	 * @param string[]|UserIdentity[] $users
	 * @return string
	 */
	private function buildUserPredicate( array $users ): string {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$usernames = [];
		$ips = [];
		$ranges = [];
		foreach ( $users as $user ) {
			if ( $user instanceof UserIdentity ) {
				$user = $user->getName();
			} else {
				$user = (string)$user;
			}

			if ( IPUtils::isIpAddress( $user ) ) {
				if ( IPUtils::isValid( $user ) ) {
					$ips[] = IPUtils::toHex( $user );
				} elseif ( IPUtils::isValidRange( $user ) ) {
					$ranges[] = IPUtils::parseRange( $user );
				}
			} else {
				$usernames[] = $user;
			}
		}

		$conds = [];
		if ( $ranges ) {
			foreach ( $ranges as $range ) {
				$conds[] = $db->makeList( [
					'cuc_ip_hex >= ' . $db->addQuotes( $range[0] ),
					'cuc_ip_hex <=' . $db->addQuotes( $range[1] )
				], IDatabase::LIST_AND );
			}
		}

		if ( $usernames ) {
			$conds['cuc_user_text'] = $usernames;
		}

		if ( $ips ) {
			$conds['cuc_ip_hex'] = $ips;
		}

		return $db->makeList( $conds, IDatabase::LIST_OR );
	}
}
