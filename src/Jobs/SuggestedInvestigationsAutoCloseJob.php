<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Jobs;

use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Context\RequestContext;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class SuggestedInvestigationsAutoCloseJob extends Job {

	public const TYPE = 'checkuserSuggestedInvestigationsAutoClose';

	private const DELAY_SECONDS = 3600;

	public function __construct(
		array $params,
		private readonly SuggestedInvestigationsCaseManagerService $caseManager,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookup,
		private readonly CompositeIndefiniteBlockChecker $blockChecker,
		private readonly LoggerInterface $logger,
		private readonly MessageLocalizer $messageLocalizer
	) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * @param array $params ['caseId', 'jobReleaseTimeStamp'] int The ID of the case to potentially auto-close
	 * @return self
	 */
	public static function newFromGlobalState( array $params ): self {
		$services = MediaWikiServices::getInstance();

		return new self(
			$params,
			$services->getService( 'CheckUserSuggestedInvestigationsCaseManager' ),
			$services->getService( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$services->getService( 'CheckUserCompositeIndefiniteBlockChecker' ),
			$services->getService( 'CheckUserLogger' ),
			RequestContext::getMain()
		);
	}

	/**
	 * @param int $caseId
	 * @param bool $delayedJobsEnabled Whether delayed jobs are enabled in the job queue to avoid exceptions
	 */
	public static function newSpec( int $caseId, bool $delayedJobsEnabled ): IJobSpecification {
		$params = [ 'caseId' => $caseId ];
		if ( $delayedJobsEnabled ) {
			$params['jobReleaseTimestamp'] = ConvertibleTimestamp::time() + self::DELAY_SECONDS;
		}
		return new JobSpecification( self::TYPE, $params );
	}

	/** @inheritDoc */
	public function run(): bool {
		$caseId = (int)$this->params['caseId'];
		if ( $this->caseLookup->getCaseStatus( $caseId ) !== CaseStatus::Open ) {
			return true;
		}

		$userIds = $this->caseLookup->getUserIdsInCase( $caseId );
		if ( $userIds === [] ) {
			return true;
		}

		$unblockedUserIds = $this->blockChecker->getUnblockedUserIds( $userIds );
		if ( $unblockedUserIds !== [] ) {
			$this->logger->info(
				'Users {userIds} are not indefinitely blocked, skipping auto-close for case {caseId}',
				[ 'userIds' => implode( ', ', $unblockedUserIds ), 'caseId' => $caseId ]
			);

			return true;
		}

		$reason = $this->messageLocalizer
			->msg( 'checkuser-suggestedinvestigations-autoclose-reason' )
			->text();

		$this->caseManager->setCaseStatus( $caseId, CaseStatus::Resolved, $reason );
		$this->logger->info(
			'Auto resolved case {caseId} as all associated users are indefinitely blocked',
			[ 'caseId' => $caseId ]
		);

		return true;
	}
}
