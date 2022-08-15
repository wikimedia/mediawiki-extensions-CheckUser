<?php

namespace MediaWiki\CheckUser\Hook;

use IContextSource;
use MediaWiki\HookContainer\HookContainer;

class HookRunner implements CheckUserFormatRowHook, CheckUserSubtitleLinksHook {
	/** @var HookContainer */
	private $container;

	/**
	 * @param HookContainer $container
	 */
	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

	/**
	 * @inheritDoc
	 */
	public function onCheckUserFormatRow(
		IContextSource $context,
		\stdClass $row,
		array &$rowItems
	) {
		$this->container->run(
			'CheckUserFormatRow',
			[ $context, $row, &$rowItems ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCheckUserSubtitleLinks(
		IContextSource $context,
		array &$links
	) {
		$this->container->run(
			'CheckUserSubtitleLinks',
			[ $context, &$links ]
		);
	}
}
