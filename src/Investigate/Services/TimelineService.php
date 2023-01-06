<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use MediaWiki\CheckUser\CheckUserActorMigration;

class TimelineService extends ChangeService {
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

		return [
			'tables' => [ 'cu_changes' ] + $actorQuery['tables'],
			'fields' => [
				'cuc_namespace', 'cuc_title', 'cuc_comment', 'cuc_actiontext', 'cuc_timestamp',
				'cuc_minor', 'cuc_page_id', 'cuc_type', 'cuc_this_oldid', 'cuc_last_oldid',
				'cuc_ip', 'cuc_xff', 'cuc_agent', 'cuc_id',
			] + $actorQuery['fields'],
			'conds' => array_merge(
				$this->buildTargetCondsMultiple( $targets ),
				$this->buildExcludeTargetsConds( $excludeTargets ),
				$this->buildStartConds( $start )
			),
			'join_conds' => $actorQuery['joins']
		];
	}
}
