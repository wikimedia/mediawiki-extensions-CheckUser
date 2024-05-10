<?php

namespace MediaWiki\Extension\UserMerge\Hooks;

/**
 * Stub for phan of the UserMerge extension "AccountFieldsHook" interface
 */
interface AccountFieldsHook {
	/**
	 * See the UserMerge extension for details.
	 * @param array &$updateFields
	 */
	public function onUserMergeAccountFields( array &$updateFields ): void;
}
