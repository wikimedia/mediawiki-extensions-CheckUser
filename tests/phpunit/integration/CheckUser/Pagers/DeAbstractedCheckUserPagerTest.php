<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;

/**
 * A helper class for AbstractCheckUserPagerTest.php.
 * Because AbstractCheckUserPager is an abstract class
 * it has to be extended first if an object is wanted.
 *
 * This implements the abstracted methods by returning
 * an empty response (as testing these methods should
 * be done for classes that extend AbstractCheckUserPager).
 *
 * @coversNothing
 */
class DeAbstractedCheckUserPagerTest extends AbstractCheckUserPager {

	public function formatRow( $row ) {
		return '';
	}

	public function getQueryInfo() {
		return [];
	}

	public function getIndexField() {
		return [];
	}
}
