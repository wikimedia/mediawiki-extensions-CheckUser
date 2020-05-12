<?php

namespace MediaWiki\CheckUser;

use Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use TitleFormatter;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class TimelineRowFormatterFactory {

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var RevisionFactory */
	private $revisionFactory;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer,
		RevisionLookup $revisionLookup,
		RevisionStore $revisionStore,
		RevisionFactory $revisionFactory,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
		$this->revisionLookup = $revisionLookup;
		$this->revisionStore = $revisionStore;
		$this->revisionFactory = $revisionFactory;
		$this->titleFormatter = $titleFormatter;
		$this->specialPageFactory = $specialPageFactory;
	}

	/**
	 * Creates a row formatter
	 *
	 * @param User $user
	 * @param Language $language
	 * @return TimelineRowFormatter
	 */
	public function createRowFormatter( User $user, Language $language ) : TimelineRowFormatter {
		return new TimelineRowFormatter(
			$this->linkRenderer,
			$this->loadBalancer,
			$this->revisionLookup,
			$this->revisionStore,
			$this->revisionFactory,
			$this->titleFormatter,
			$this->specialPageFactory,
			$user,
			$language
		);
	}
}
