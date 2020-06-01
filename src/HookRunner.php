<?php

namespace MediaWiki\CheckUser;

use IContextSource;
use MediaWiki\HookContainer\HookContainer;

class HookRunner implements CheckUserFormatRowHook {
	/** @var HookContainer */
	private $container;

	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

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
}
