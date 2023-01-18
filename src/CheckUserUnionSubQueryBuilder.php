<?php

namespace MediaWiki\CheckUser;

use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @internal For use by CheckUserSelectQueryBuilder only
 */
class CheckUserUnionSubQueryBuilder extends SelectQueryBuilder {
	/**
	 * Allows CheckUserSelectQueryBuilder to reset the last
	 * alias to the value before it made an operation to
	 * add a join.
	 *
	 * Ensures that users of CheckUserSelectQueryBuilder can
	 * expect the USE INDEX hint to apply to either the table
	 * the results come from or the last table they added using ::tables.
	 *
	 * @param string $newLastAlias The value to now use for lastAlias.
	 * @return void
	 * @internal For use by CheckUserSelectQueryBuilder only
	 */
	public function updateLastAlias( string $newLastAlias ) {
		$this->lastAlias = $newLastAlias;
	}

	/**
	 * Allows CheckUserSelectQueryBuilder to have access to
	 * the lastAlias value so that it can reset lastAlias
	 * back to this value after performing an operation.
	 *
	 * @internal For use by CheckUserSelectQueryBuilder only
	 * @return mixed
	 */
	public function getLastAlias() {
		return $this->lastAlias;
	}
}
