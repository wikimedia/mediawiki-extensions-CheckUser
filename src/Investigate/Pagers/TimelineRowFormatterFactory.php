<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use Language;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use TitleFormatter;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class TimelineRowFormatterFactory {
	private LinkRenderer $linkRenderer;
	private ILoadBalancer $loadBalancer;
	private RevisionStore $revisionStore;
	private TitleFormatter $titleFormatter;
	private SpecialPageFactory $specialPageFactory;
	private CommentFormatter $commentFormatter;
	private UserFactory $userFactory;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param ILoadBalancer $loadBalancer
	 * @param RevisionStore $revisionStore
	 * @param TitleFormatter $titleFormatter
	 * @param SpecialPageFactory $specialPageFactory
	 * @param CommentFormatter $commentFormatter
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer,
		RevisionStore $revisionStore,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
		$this->revisionStore = $revisionStore;
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
			$this->revisionStore,
			$this->titleFormatter,
			$this->specialPageFactory,
			$this->commentFormatter,
			$this->userFactory,
			$user,
			$language
		);
	}
}
