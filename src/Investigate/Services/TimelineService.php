<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use MediaWiki\CheckUser\CheckUserActorMigration;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\Database\DbQuoter;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

class TimelineService extends ChangeService {
	/** @var CommentStore */
	private $commentStore;

	/**
	 * @param DbQuoter $dbQuoter
	 * @param ISQLPlatform $sqlPlatform
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param CommentStore $commentStore
	 */
	public function __construct(
		DbQuoter $dbQuoter,
		ISQLPlatform $sqlPlatform,
		UserIdentityLookup $userIdentityLookup,
		CommentStore $commentStore
	) {
		parent::__construct( $dbQuoter, $sqlPlatform, $userIdentityLookup );

		$this->commentStore = $commentStore;
	}

	/**
	 * Get timeline query info
	 *
	 * @param string[] $targets
	 * @param string[] $excludeTargets
	 * @param string $start
	 * @return array
	 */
	public function getQueryInfo( array $targets, array $excludeTargets, string $start ): array {
		$actorQuery = CheckUserActorMigration::newMigration()->getJoin( 'cuc_user' );
		$commentQuery = $this->commentStore->getJoin( 'cuc_comment' );

		return [
			'tables' => [ 'cu_changes' ] + $actorQuery['tables'] + $commentQuery['tables'],
			'fields' => [
				'cuc_namespace', 'cuc_title', 'cuc_actiontext', 'cuc_timestamp',
				'cuc_minor', 'cuc_page_id', 'cuc_type', 'cuc_this_oldid', 'cuc_last_oldid',
				'cuc_ip', 'cuc_xff', 'cuc_agent', 'cuc_id',
			] + $actorQuery['fields'] + $commentQuery['fields'],
			'conds' => array_merge(
				$this->buildTargetCondsMultiple( $targets ),
				$this->buildExcludeTargetsConds( $excludeTargets ),
				$this->buildStartConds( $start )
			),
			'join_conds' => $actorQuery['joins'] + $commentQuery['joins']
		];
	}
}
