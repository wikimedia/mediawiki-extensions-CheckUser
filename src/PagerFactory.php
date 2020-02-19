<?php

namespace MediaWiki\CheckUser;

interface PagerFactory {
	/**
	 * Factory to create the TablePager
	 *
	 * @param \IContextSource $context
	 *
	 * @return \TablePager
	 */
	public function createPager( \IContextSource $context ) : \TablePager;
}
