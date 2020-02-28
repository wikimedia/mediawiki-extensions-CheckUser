<?php

namespace MediaWiki\CheckUser;

interface PagerFactory {
	/**
	 * Factory to create the pager
	 *
	 * @param \IContextSource $context
	 */
	public function createPager( \IContextSource $context );
}
