<?php

namespace MediaWiki\CheckUser;

use CommentStore;
use DeferredUpdates;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\User\ActorStore;
use Psr\Log\LoggerInterface;
use Sanitizer;
use Title;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class CheckUserLogService {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var CommentStore */
	private $commentStore;

	/** @var CommentFormatter */
	private $commentFormatter;

	/** @var ActorStore */
	private $actorStore;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param CommentStore $commentStore
	 * @param CommentFormatter $commentFormatter
	 * @param LoggerInterface $logger
	 * @param ActorStore $actorStore
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		CommentStore $commentStore,
		CommentFormatter $commentFormatter,
		LoggerInterface $logger,
		ActorStore $actorStore
	) {
		$this->loadBalancer = $loadBalancer;
		$this->commentStore = $commentStore;
		$this->commentFormatter = $commentFormatter;
		$this->logger = $logger;
		$this->actorStore = $actorStore;
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
			'cul_actor' => $this->actorStore->acquireActorId( $user, $dbw ),
			'cul_type' => $logType,
			'cul_target_id' => $targetID,
			'cul_target_text' => trim( $target ),
			'cul_target_hex' => $targetHex,
			'cul_range_start' => $rangeStart,
			'cul_range_end' => $rangeEnd
		];

		$plaintextReason = $this->getPlaintextReason( $reason );

		$fname = __METHOD__;
		$commentStore = $this->commentStore;
		$logger = $this->logger;

		DeferredUpdates::addCallableUpdate(
			static function () use (
				$data, $timestamp, $reason, $plaintextReason, $fname, $dbw, $commentStore, $logger
			) {
				try {
					$data += $commentStore->insert( $dbw, 'cul_reason', $reason );
					$data += $commentStore->insert( $dbw, 'cul_reason_plaintext', $plaintextReason );
					$dbw->insert(
						'cu_log',
						[
							'cul_timestamp' => $dbw->timestamp( $timestamp )
						] + $data,
						$fname
					);
				} catch ( DBError $e ) {
					$logger->critical(
						'CheckUserLog entry was not recorded. This means checks can occur without being auditable. ' .
						'Immediate fix required.'
					);
					throw $e;
				}
			}
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
