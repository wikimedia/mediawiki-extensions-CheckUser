<?php

namespace MediaWiki\CheckUser;

use IContextSource;
use MediaWiki\Linker\LinkRenderer;

class TimelinePagerFactory implements PagerFactory {
	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var TimelineService */
	private $service;

	public function __construct(
		LinkRenderer $linkRenderer,
		TokenQueryManager $tokenQueryManager,
		TimelineService $service
	) {
		$this->linkRenderer = $linkRenderer;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->service = $service;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ) : TimelinePager {
		$rowFormatter = new TimelineRowFormatter(
			$this->linkRenderer, $context->getUser(), $context->getLanguage()
		);

		return new TimelinePager(
			$context,
			$this->linkRenderer,
			$this->tokenQueryManager,
			$this->service,
			$rowFormatter
		 );
	}
}
