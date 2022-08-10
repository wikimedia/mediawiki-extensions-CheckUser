<?php

namespace MediaWiki\CheckUser\Investigate\Services;

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
		return [
			'tables' => 'cu_changes',
			'fields' => [
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment',
				'cuc_actiontext', 'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type',
				'cuc_this_oldid', 'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent', 'cuc_id',
			],
			'conds' => array_merge(
				$this->buildTargetCondsMultiple( $targets ),
				$this->buildExcludeTargetsConds( $excludeTargets ),
				$this->buildStartConds( $start )
			),
		];
	}
}
