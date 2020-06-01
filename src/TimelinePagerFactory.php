<?php

namespace MediaWiki\CheckUser;

use IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;

class TimelinePagerFactory implements PagerFactory {
	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var HookContainer */
	private $hookContainer;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var TimelineService */
	private $service;

	/** @var TimelineRowFormatterFactory */
	private $rowFormatterFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		HookContainer $hookContainer,
		TokenQueryManager $tokenQueryManager,
		TimelineService $service,
		TimelineRowFormatterFactory $rowFormatterFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->hookContainer = $hookContainer;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->service = $service;
		$this->rowFormatterFactory = $rowFormatterFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ) : TimelinePager {
		$rowFormatter = $this->rowFormatterFactory->createRowFormatter(
			$context->getUser(), $context->getLanguage()
		);

		return new TimelinePager(
			$context,
			$this->linkRenderer,
			$this->hookContainer,
			$this->tokenQueryManager,
			$this->service,
			$rowFormatter
		 );
	}
}
