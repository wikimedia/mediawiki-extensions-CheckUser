<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;

class ComparePagerFactory implements PagerFactory {
	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var DurationManager */
	private $durationManager;

	/** @var CompareService */
	private $compare;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param TokenQueryManager $tokenQueryManager
	 * @param DurationManager $durationManager
	 * @param CompareService $compare
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		CompareService $compare
	) {
		$this->linkRenderer = $linkRenderer;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->durationManager = $durationManager;
		$this->compare = $compare;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( \IContextSource $context ): ComparePager {
		return new ComparePager(
			$context,
			$this->linkRenderer,
			$this->tokenQueryManager,
			$this->durationManager,
			$this->compare
		);
	}
}
