<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Extension\UserMerge\Hooks\AccountFieldsHook;

class UserMergeHandler implements AccountFieldsHook {
	/** @inheritDoc */
	public function onUserMergeAccountFields( array &$updateFields ): void {
		$updateFields[] = [
			'cu_changes',
			'batch_key' => 'cuc_id',
			'actorId' => 'cuc_actor',
			'actorStage' => SCHEMA_COMPAT_NEW
		];
		$updateFields[] = [
			'cu_log',
			'batch_key' => 'cul_id',
			'actorId' => 'cul_actor',
			'actorStage' => SCHEMA_COMPAT_NEW
		];
		$updateFields[] = [ 'cu_log', 'cul_target_id' ];
	}
}
