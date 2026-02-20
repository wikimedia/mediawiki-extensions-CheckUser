<?php

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Investigate\Services\CompareService;
use MediaWiki\Extension\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\User\UserFactory;

class ComparePagerFactory implements PagerFactory {
	private LinkRenderer $linkRenderer;
	private TokenQueryManager $tokenQueryManager;
	private DurationManager $durationManager;
	private CompareService $compare;
	private UserFactory $userFactory;
	private LinkBatchFactory $linkBatchFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		CompareService $compare,
		UserFactory $userFactory,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->durationManager = $durationManager;
		$this->compare = $compare;
		$this->userFactory = $userFactory;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ): ComparePager {
		return new ComparePager(
			$context,
			$this->linkRenderer,
			$this->tokenQueryManager,
			$this->durationManager,
			$this->compare,
			$this->userFactory,
			$this->linkBatchFactory
		);
	}
}
