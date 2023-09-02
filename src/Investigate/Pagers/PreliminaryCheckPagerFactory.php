<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use ExtensionRegistry;
use IContextSource;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use NamespaceInfo;

class PreliminaryCheckPagerFactory implements PagerFactory {
	private LinkRenderer $linkRenderer;
	private NamespaceInfo $namespaceInfo;
	private ExtensionRegistry $extensionRegistry;
	private TokenQueryManager $tokenQueryManager;
	private PreliminaryCheckService $preliminaryCheck;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param NamespaceInfo $namespaceInfo
	 * @param ExtensionRegistry $extensionRegistry
	 * @param TokenQueryManager $tokenQueryManager
	 * @param PreliminaryCheckService $preliminaryCheck
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		NamespaceInfo $namespaceInfo,
		ExtensionRegistry $extensionRegistry,
		TokenQueryManager $tokenQueryManager,
		PreliminaryCheckService $preliminaryCheck
	) {
		$this->linkRenderer = $linkRenderer;
		$this->namespaceInfo = $namespaceInfo;
		$this->extensionRegistry = $extensionRegistry;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->preliminaryCheck = $preliminaryCheck;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ): PreliminaryCheckPager {
		return new PreliminaryCheckPager(
			$context,
			$this->linkRenderer,
			$this->namespaceInfo,
			$this->tokenQueryManager,
			$this->extensionRegistry,
			$this->preliminaryCheck
		);
	}
}
