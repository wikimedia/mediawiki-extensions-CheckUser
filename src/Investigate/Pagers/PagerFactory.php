<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use MediaWiki\Context\IContextSource;

interface PagerFactory {
	/**
	 * Factory to create the pager
	 */
	public function createPager( IContextSource $context );
}
