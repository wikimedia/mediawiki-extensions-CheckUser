<?php

namespace MediaWiki\CheckUser;

use MediaWiki\Linker\LinkRenderer;

class ComparePagerFactory implements PagerFactory {
	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var CompareService */
	private $compare;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param TokenQueryManager $tokenQueryManager
	 * @param CompareService $compare
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		TokenQueryManager $tokenQueryManager,
		CompareService $compare
	) {
		$this->linkRenderer = $linkRenderer;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->compare = $compare;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( \IContextSource $context ) : ComparePager {
		return new ComparePager(
			$context,
			$this->linkRenderer,
			$this->tokenQueryManager,
			$this->compare
		);
	}
}
