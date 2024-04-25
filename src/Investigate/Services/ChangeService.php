<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use MediaWiki\User\UserIdentityLookup;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\Database\DbQuoter;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

abstract class ChangeService {
	protected DbQuoter $dbQuoter;
	protected ISQLPlatform $sqlPlatform;
	private UserIdentityLookup $userIdentityLookup;

	/**
	 * @param DbQuoter $dbQuoter
	 * @param ISQLPlatform $sqlPlatform
	 * @param UserIdentityLookup $userIdentityLookup
	 */
	public function __construct(
		DbQuoter $dbQuoter,
		ISQLPlatform $sqlPlatform,
		UserIdentityLookup $userIdentityLookup
	) {
		$this->dbQuoter = $dbQuoter;
		$this->sqlPlatform = $sqlPlatform;
		$this->userIdentityLookup = $userIdentityLookup;
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string[] $targets
	 * @return ?string[]
	 */
	protected function buildTargetCondsMultiple( array $targets ): ?array {
		$condSet = array_map( function ( $target ) {
			return $this->buildTargetConds( $target );
		}, $targets );

		if ( !$condSet ) {
			return null;
		}

		$conds = array_merge_recursive( ...$condSet );

		if ( !$conds ) {
			return null;
		}

		return [
			$this->sqlPlatform->makeList( $conds, IDatabase::LIST_OR ),
		];
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string $target
	 * @return string[]
	 */
	protected function buildTargetConds( $target ): array {
		$conds = [];

		if ( IPUtils::isIpAddress( $target ) ) {
			if ( IPUtils::isValid( $target ) ) {
				$conds['cuc_ip_hex'] = IPUtils::toHex( $target );
			} elseif ( IPUtils::isValidRange( $target ) ) {
				$range = IPUtils::parseRange( $target );
				$conds[] = $this->sqlPlatform->makeList( [
					'cuc_ip_hex >= ' . $this->dbQuoter->addQuotes( $range[0] ),
					'cuc_ip_hex <= ' . $this->dbQuoter->addQuotes( $range[1] )
				], IDatabase::LIST_AND );
			}
		} else {
			// TODO: This may filter out invalid values, changing the number of
			// targets. The per-target limit should change too (T246393).
			$user = $this->userIdentityLookup->getUserIdentityByName( $target );
			if ( $user ) {
				$userId = $user->getId();
				if ( $userId !== 0 ) {
					$conds['actor_user'] = $userId;
				}
			}
		}

		return $conds;
	}

	/**
	 * @param string[] $targets
	 * @return array
	 */
	protected function buildExcludeTargetsConds( array $targets ): array {
		$conds = [];
		$ipTargets = [];
		$userTargets = [];

		foreach ( $targets as $target ) {
			if ( IPUtils::isIpAddress( $target ) ) {
				$ipTargets[] = IPUtils::toHex( $target );
			} else {
				$user = $this->userIdentityLookup->getUserIdentityByName( $target );
				if ( $user ) {
					$userId = $user->getId();
					if ( $userId !== 0 ) {
						$userTargets[] = $userId;
					}
				}
			}
		}

		if ( count( $ipTargets ) > 0 ) {
			$conds[] = 'cuc_ip_hex NOT IN (' . $this->sqlPlatform->makeList( $ipTargets ) . ')';
		}
		if ( count( $userTargets ) > 0 ) {
			$conds[] = $this->sqlPlatform->makeList( [
				'actor_user NOT IN (' . $this->sqlPlatform->makeList( $userTargets ) . ')',
				'actor_user is null'
			], IDatabase::LIST_OR );
		}

		return $conds;
	}

	/**
	 * Build conditions for the start timestamp.
	 *
	 * @param string $start timestamp
	 * @return array conditions
	 */
	protected function buildStartConds( string $start ): array {
		if ( $start === '' ) {
			return [];
		}

		return [
			'cuc_timestamp >= ' . $this->dbQuoter->addQuotes( $start ),
		];
	}
}
