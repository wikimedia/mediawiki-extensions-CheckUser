<?php

namespace MediaWiki\CheckUser;

use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

abstract class ChangeService {
	/** @var ILoadBalancer */
	protected $loadBalancer;

	/** @var UserManager */
	protected $userManager;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param UserManager $userManager
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		UserManager $userManager
	) {
		$this->loadBalancer = $loadBalancer;
		$this->userManager = $userManager;
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string[] $targets
	 * @return string[]
	 */
	protected function buildTargetCondsMultiple( array $targets ) : array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );

		$condSet = array_map( function ( $target ) {
			return $this->buildTargetConds( $target );
		}, $targets );

		if ( !$condSet ) {
			return [
				$db->addQuotes( false )
			];
		}

		$conds = array_merge_recursive( ...$condSet );

		if ( !$conds ) {
			return [
				$db->addQuotes( false )
			];
		}

		return [
			$db->makeList( $conds, IDatabase::LIST_OR ),
		];
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string $target
	 * @return string[]
	 */
	protected function buildTargetConds( $target ) : array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$conds = [];

		if ( IPUtils::isIpAddress( $target ) ) {
			if ( IPUtils::isValid( $target ) ) {
				$conds['cuc_ip_hex'] = IPUtils::toHex( $target );
			} elseif ( IPUtils::isValidRange( $target ) ) {
				$range = IPUtils::parseRange( $target );
				$conds[] = $db->makeList( [
					'cuc_ip_hex >= ' . $db->addQuotes( $range[0] ),
					'cuc_ip_hex <= ' . $db->addQuotes( $range[1] )
				], IDatabase::LIST_AND );
			}
		} else {
			// TODO: This may filter out invalid values, changing the number of
			// targets. The per-target limit should change too (T246393).
			$userId = $this->userManager->idFromName( $target );
			if ( $userId ) {
				$conds['cuc_user'] = $userId;
			}
		}

		return $conds;
	}

	/**
	 * @param string[] $targets
	 * @return array
	 */
	protected function buildExcludeTargetsConds( array $targets ) : array {
		$conds = [];
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );

		$ipTargets = [];
		$userTargets = [];

		foreach ( $targets as $target ) {
			if ( IPUtils::isIpAddress( $target ) ) {
				$ipTargets[] = IPUtils::toHex( $target );
			} else {
				$userId = $this->userManager->idFromName( $target );
				if ( $userId ) {
					$userTargets[] = $userId;
				}
			}
		}

		if ( count( $ipTargets ) > 0 ) {
			$conds[] = 'cuc_ip_hex NOT IN (' . $db->makeList( $ipTargets ) . ')';
		}
		if ( count( $userTargets ) > 0 ) {
			$conds[] = 'cuc_user NOT IN (' . $db->makeList( $userTargets ) . ')';
		}

		return $conds;
	}

	/**
	 * Build conditions for the start timeestamp.
	 *
	 * @param string $start timestamp
	 * @return array conditions
	 */
	protected function buildStartConds( string $start ) : array {
		if ( $start === '' ) {
			return [];
		}

		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		return [
			'cuc_timestamp >= ' . $db->addQuotes( $start ),
		];
	}
}
