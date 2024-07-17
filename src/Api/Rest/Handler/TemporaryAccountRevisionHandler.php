<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use JobQueueGroup;
use MediaWiki\Block\BlockManager;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

class TemporaryAccountRevisionHandler extends AbstractTemporaryAccountNameHandler {

	private RevisionStore $revisionStore;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		RevisionStore $revisionStore
	) {
		parent::__construct(
			$config, $jobQueueGroup, $permissionManager, $userOptionsLookup, $userNameUtils, $dbProvider, $actorStore,
			$blockManager
		);
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		$ids = $this->getValidatedParams()['ids'];
		if ( !count( $ids ) ) {
			throw new LocalizedHttpException(
				DataMessageValue::new( 'paramvalidator-missingparam', [], 'missingparam' )
					->plaintextParams( "ids" ),
				400,
				[
					'error' => 'parameter-validation-failed',
					'name' => 'ids',
					'value' => '',
					'failureCode' => "missingparam",
					'failureData' => null,
				]
			);
		}

		$ids = $this->filterOutHiddenRevisions( $ids );

		if ( !count( $ids ) ) {
			// If all revisions were filtered out, return a results list with no IPs
			// which is what happens when there is no CU data for the revisions.
			return [ 'ips' => [] ];
		}

		$conds = [
			'cuc_actor' => $actorId,
			'cuc_this_oldid' => $ids,
		];

		$rows = $dbr->newSelectQueryBuilder()
			// T327906: 'cuc_actor' and 'cuc_timestamp' are selected
			// only to satisfy Postgres requirement where all ORDER BY
			// fields must be present in SELECT list.
			->select( [ 'cuc_this_oldid', 'cuc_ip', 'cuc_actor', 'cuc_timestamp' ] )
			->from( 'cu_changes' )
			->where( $conds )
			->orderBy( [ 'cuc_actor', 'cuc_ip', 'cuc_timestamp' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ips = [];
		foreach ( $rows as $row ) {
			// In the unlikely case that there are rows with the same
			// revision ID, the final array will contain the most recent
			$ips[$row->cuc_this_oldid] = $row->cuc_ip;
		}

		return [ 'ips' => $ips ];
	}

	/**
	 * Filter out revision IDs where the authority does not have permissions to view
	 * the performer of the revision.
	 *
	 * @param int[] $ids
	 * @return int[]
	 */
	protected function filterOutHiddenRevisions( array $ids ): array {
		// ::joinComment is needed because ::newRevisionsFromBatch needs the comment fields.
		$revisionRows = $this->revisionStore->newSelectQueryBuilder( $this->dbProvider->getReplicaDatabase() )
			->joinComment()
			->where( [ 'rev_id' => $ids ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		// Create RevisionRecord objects for each row and then filter out the revisions that have the performer hidden.
		$status = $this->revisionStore->newRevisionsFromBatch( $revisionRows );
		$filteredIds = $this->filterOutHiddenRevisionsInternal( $status->isOK() ? $status->getValue() : [] );
		// If the authority has the 'deletedhistory' right, also check the archive table for any IDs which
		// were not found in the revision table.
		if ( $this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'deletedhistory' ) ) {
			// RevisionStore::newRevisionsFromBatch doesn't rewind the results after iterating over them.
			$revisionRows->rewind();
			// Find the IDs which were not found in the revision table so that we can check the archive table.
			$missingIds = array_diff(
				$ids,
				array_map( static function ( $row ) {
					return $row->rev_id;
				}, iterator_to_array( $revisionRows ) )
			);
			if ( count( $missingIds ) ) {
				// If IDs are missing, then they are probably in the archive table. If not they are not,
				// they will be ignored as invalid to avoid leaking data.
				$archiveRevisionRows = $this->revisionStore
					->newArchiveSelectQueryBuilder( $this->dbProvider->getReplicaDatabase() )
					->joinComment()
					->where( [ 'ar_rev_id' => $missingIds ] )
					->caller( __METHOD__ )
					->fetchResultSet();
				$status = $this->revisionStore->newRevisionsFromBatch( $archiveRevisionRows, [ 'archive' => true ] );
				$filteredIds = array_merge(
					$filteredIds,
					$this->filterOutHiddenRevisionsInternal( $status->isOK() ? $status->getValue() : [] )
				);
			}
		}
		return $filteredIds;
	}

	/**
	 * Actually perform the filtering of revisions where the performer is hidden from the authority.
	 *
	 * @param RevisionRecord[] $revisions
	 * @return int[] The revision IDs the authority is allowed to see.
	 */
	private function filterOutHiddenRevisionsInternal( array $revisions ): array {
		$filteredIds = [];
		foreach ( $revisions as $revisionRecord ) {
			if ( $revisionRecord->userCan( RevisionRecord::DELETED_USER, $this->getAuthority() ) ) {
				$filteredIds[] = $revisionRecord->getId();
			}
		}
		return $filteredIds;
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		$settings = parent::getParamSettings();
		$settings['ids'] = [
			self::PARAM_SOURCE => 'path',
			ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => true,
			ParamValidator::PARAM_ISMULTI => true,
		];
		return $settings;
	}
}
