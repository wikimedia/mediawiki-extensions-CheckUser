<?php

namespace MediaWiki\CheckUser;

use IContextSource;
use MediaWiki\CheckUser\Hook\CheckUserFormatRowHook;
use MediaWiki\Linker\LinkRenderer;
use Psr\Log\LoggerInterface;

class TimelinePagerFactory implements PagerFactory {
	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var CheckUserFormatRowHook */
	private $formatRowHookRunner;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var DurationManager */
	private $durationManager;

	/** @var TimelineService */
	private $service;

	/** @var TimelineRowFormatterFactory */
	private $rowFormatterFactory;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		LinkRenderer $linkRenderer,
		CheckUserFormatRowHook $formatRowHookRunner,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		TimelineService $service,
		TimelineRowFormatterFactory $rowFormatterFactory,
		LoggerInterface $logger
	) {
		$this->linkRenderer = $linkRenderer;
		$this->formatRowHookRunner = $formatRowHookRunner;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->durationManager = $durationManager;
		$this->service = $service;
		$this->rowFormatterFactory = $rowFormatterFactory;
		$this->logger = $logger;
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
			$this->formatRowHookRunner,
			$this->tokenQueryManager,
			$this->durationManager,
			$this->service,
			$rowFormatter,
			$this->logger
		 );
	}
}
