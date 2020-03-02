<?php

namespace MediaWiki\CheckUser;

use MediaWiki\Linker\LinkRenderer;

class PreliminaryCheckPagerFactory implements PagerFactory {
	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var \NamespaceInfo */
	private $namespaceInfo;

	/** @var \ExtensionRegistry */
	private $extensionRegistry;

	/** @var TokenManager */
	private $tokenManager;

	/** @var PreliminaryCheckService */
	private $preliminaryCheck;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param \NamespaceInfo $namespaceInfo
	 * @param \ExtensionRegistry $extensionRegistry
	 * @param TokenManager $tokenManager
	 * @param PreliminaryCheckService $preliminaryCheck
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		\NamespaceInfo $namespaceInfo,
		\ExtensionRegistry $extensionRegistry,
		TokenManager $tokenManager,
		PreliminaryCheckService $preliminaryCheck
	) {
		$this->linkRenderer = $linkRenderer;
		$this->namespaceInfo = $namespaceInfo;
		$this->extensionRegistry = $extensionRegistry;
		$this->tokenManager = $tokenManager;
		$this->preliminaryCheck = $preliminaryCheck;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( \IContextSource $context ) : PreliminaryCheckPager {
		return new PreliminaryCheckPager(
			$context,
			$this->linkRenderer,
			$this->namespaceInfo,
			$this->tokenManager,
			$this->extensionRegistry,
			$this->preliminaryCheck
		);
	}
}
