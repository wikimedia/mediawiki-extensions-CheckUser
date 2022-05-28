<?php

namespace MediaWiki\CheckUser;

use Language;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
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

	/** @var CommentFormatter */
	private $commentFormatter;

	/** @var UserFactory */
	private $userFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer,
		RevisionLookup $revisionLookup,
		RevisionStore $revisionStore,
		RevisionFactory $revisionFactory,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
		$this->revisionLookup = $revisionLookup;
		$this->revisionStore = $revisionStore;
		$this->revisionFactory = $revisionFactory;
		$this->titleFormatter = $titleFormatter;
		$this->specialPageFactory = $specialPageFactory;
		$this->commentFormatter = $commentFormatter;
		$this->userFactory = $userFactory;
	}

	/**
	 * Creates a row formatter
	 *
	 * @param User $user
	 * @param Language $language
	 * @return TimelineRowFormatter
	 */
	public function createRowFormatter( User $user, Language $language ): TimelineRowFormatter {
		return new TimelineRowFormatter(
			$this->linkRenderer,
			$this->loadBalancer,
			$this->revisionLookup,
			$this->revisionStore,
			$this->revisionFactory,
			$this->titleFormatter,
			$this->specialPageFactory,
			$this->commentFormatter,
			$this->userFactory,
			$user,
			$language
		);
	}
}
