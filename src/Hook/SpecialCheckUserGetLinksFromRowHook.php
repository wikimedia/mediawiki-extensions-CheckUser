<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;

interface SpecialCheckUserGetLinksFromRowHook {
	/**
	 * A hook that is used to modify the generated links
	 * shown for a entry in the 'Get edits' checktype. Any
	 * added strings to &$links must be properly escaped.
	 * Keys can be used in the array for convenience, but these
	 * are not used in the resulting output.
	 *
	 * @param AbstractCheckUserPager $specialCheckUser The instance of the pager being used to generate the results
	 * @param \stdClass $row The row from the database that is being processed by the pager
	 * @param array &$links The links that the pager has defined for this row that can be modified
	 * @since 1.40
	 */
	public function onSpecialCheckUserGetLinksFromRow(
		AbstractCheckUserPager $specialCheckUser,
		\stdClass $row,
		array &$links
	);
}