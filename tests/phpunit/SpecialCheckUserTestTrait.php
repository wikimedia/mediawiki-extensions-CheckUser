<?php

declare( strict_types = 1 );

namespace MediaWiki\CheckUser\Tests;

trait SpecialCheckUserTestTrait {

	/**
	 * Reset the max_execution_time after
	 * {@link \MediaWiki\CheckUser\CheckUser\SpecialCheckUser::execute() SpecialCheckUser::execute()}
	 * potentially set it (via {@link set_time_limit()}).
	 *
	 * @after
	 */
	public function maxExecutionTimeTearDown(): void {
		set_time_limit( 0 );
	}

}
