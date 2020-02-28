<?php

namespace MediaWiki\CheckUser;

use MediaWiki\Linker\LinkRenderer;

class ComparePagerFactory implements PagerFactory {
	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var TokenManager */
	private $tokenManager;

	/** @var CompareService */
	private $compare;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param TokenManager $tokenManager
	 * @param CompareService $compare
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		TokenManager $tokenManager,
		CompareService $compare
	) {
		$this->linkRenderer = $linkRenderer;
		$this->tokenManager = $tokenManager;
		$this->compare = $compare;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( \IContextSource $context ) : ComparePager {
		return new ComparePager(
			$context,
			$this->linkRenderer,
			$this->tokenManager,
			$this->compare
		);
	}
}
