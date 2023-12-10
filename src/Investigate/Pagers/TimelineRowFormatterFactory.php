<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use Language;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class TimelineRowFormatterFactory {
	private LinkRenderer $linkRenderer;
	private RevisionStore $revisionStore;
	private ArchivedRevisionLookup $archivedRevisionLookup;
	private TitleFormatter $titleFormatter;
	private SpecialPageFactory $specialPageFactory;
	private CommentFormatter $commentFormatter;
	private UserFactory $userFactory;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param RevisionStore $revisionStore
	 * @param ArchivedRevisionLookup $archivedRevisionLookup
	 * @param TitleFormatter $titleFormatter
	 * @param SpecialPageFactory $specialPageFactory
	 * @param CommentFormatter $commentFormatter
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		RevisionStore $revisionStore,
		ArchivedRevisionLookup $archivedRevisionLookup,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->revisionStore = $revisionStore;
		$this->archivedRevisionLookup = $archivedRevisionLookup;
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
			$this->revisionStore,
			$this->archivedRevisionLookup,
			$this->titleFormatter,
			$this->specialPageFactory,
			$this->commentFormatter,
			$this->userFactory,
			$user,
			$language
		);
	}
}
