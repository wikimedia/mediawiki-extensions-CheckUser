<?php

namespace MediaWiki\CheckUser;

use CommentStore;
use DeferredUpdates;
use MediaWiki\CommentFormatter\CommentFormatter;
use Sanitizer;
use Title;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class CheckUserLogService {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var CommentStore */
	private $commentStore;

	/** @var CommentFormatter */
	private $commentFormatter;

	/** @var int */
	private $culReasonMigrationStage;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param CommentStore $commentStore
	 * @param CommentFormatter $commentFormatter
	 * @param int $culReasonMigrationStage
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		CommentStore $commentStore,
		CommentFormatter $commentFormatter,
		int $culReasonMigrationStage
	) {
		$this->loadBalancer = $loadBalancer;
		$this->commentStore = $commentStore;
		$this->commentFormatter = $commentFormatter;
		$this->culReasonMigrationStage = $culReasonMigrationStage;
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
		$data = [
			'cul_actor' => $user->getActorId(),
			'cul_type' => $logType,
			'cul_target_id' => $targetID,
			'cul_target_text' => trim( $target ),
			'cul_target_hex' => $targetHex,
			'cul_range_start' => $rangeStart,
			'cul_range_end' => $rangeEnd
		];

		if ( $this->culReasonMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$plaintextReason = $this->getPlaintextReason( $reason );
		} else {
			$plaintextReason = '';
		}

		if ( $this->culReasonMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$data['cul_reason'] = $reason;
		}

		$fname = __METHOD__;
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$commentStore = $this->commentStore;
		$writeNew = $this->culReasonMigrationStage & SCHEMA_COMPAT_WRITE_NEW;

		DeferredUpdates::addCallableUpdate(
			static function () use (
				$data, $timestamp, $reason, $plaintextReason, $fname, $dbw, $commentStore, $writeNew
			) {
				if ( $writeNew ) {
					$data += $commentStore->insert( $dbw, 'cul_reason', $reason );
					$data += $commentStore->insert( $dbw, 'cul_reason_plaintext', $plaintextReason );
				}
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

	/**
	 * Get the plaintext reason
	 *
	 * @param string $reason
	 * @return string
	 */
	public function getPlaintextReason( $reason ) {
		return Sanitizer::stripAllTags(
			$this->commentFormatter->formatBlock(
				$reason, Title::newFromText( 'Special:CheckUserLog' ),
				false, false, false
			)
		);
	}
}
