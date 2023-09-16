<?php

namespace MediaWiki\CheckUser;

use DeferredUpdates;
use MediaWiki\User\ActorStore;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class CheckUserLogService {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var ActorStore */
	private $actorStore;

	/** @var int */
	private $culActorMigrationStage;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param ActorStore $actorStore
	 * @param int $culActorMigrationStage
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		ActorStore $actorStore,
		int $culActorMigrationStage
	) {
		$this->loadBalancer = $loadBalancer;
		$this->actorStore = $actorStore;
		$this->culActorMigrationStage = $culActorMigrationStage;
	}

	/**
	 * Adds a log entry to the CheckUserLog.
	 *
	 * @param User $user
	 * @param string $logType
	 * @param string $targetType
	 * @param string $target
	 * @param string $reason
	 * @param int $targetID
	 * @return void
	 */
	public function addLogEntry(
		User $user, string $logType, string $targetType, string $target, string $reason, int $targetID = 0
	) {
		if ( $targetType == 'ip' ) {
			list( $rangeStart, $rangeEnd ) = IPUtils::parseRange( $target );
			$targetHex = $rangeStart;
			if ( $rangeStart == $rangeEnd ) {
				$rangeStart = '';
				$rangeEnd = '';
			}
		} else {
			$targetHex = '';
			$rangeStart = '';
			$rangeEnd = '';
		}

		$timestamp = ConvertibleTimestamp::now();
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );

		$data = [
			'cul_user' => $user->getId(),
			'cul_user_text' => $user->getName(),
			'cul_reason' => $reason,
			'cul_type' => $logType,
			'cul_target_id' => $targetID,
			'cul_target_text' => trim( $target ),
			'cul_target_hex' => $targetHex,
			'cul_range_start' => $rangeStart,
			'cul_range_end' => $rangeEnd
		];

		if ( $this->culActorMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$data['cul_actor'] = $this->actorStore->acquireActorId( $user, $dbw );
		}

		$fname = __METHOD__;

		DeferredUpdates::addCallableUpdate(
			static function () use ( $data, $timestamp, $fname, $dbw ) {
				$dbw->insert(
					'cu_log',
					[
						'cul_timestamp' => $dbw->timestamp( $timestamp )
					] + $data,
					$fname
				);
			},
			// fail on error and show no output
			DeferredUpdates::PRESEND
		);
	}
}
