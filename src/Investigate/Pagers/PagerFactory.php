<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use IContextSource;

interface PagerFactory {
	/**
	 * Factory to create the pager
	 *
	 * @param IContextSource $context
	 */
	public function createPager( IContextSource $context );
}
