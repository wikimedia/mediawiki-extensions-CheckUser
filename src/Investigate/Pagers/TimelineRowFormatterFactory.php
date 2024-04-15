<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use Language;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class TimelineRowFormatterFactory {
	private LinkRenderer $linkRenderer;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private TitleFormatter $titleFormatter;
	private SpecialPageFactory $specialPageFactory;
	private CommentFormatter $commentFormatter;
	private UserFactory $userFactory;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 * @param TitleFormatter $titleFormatter
	 * @param SpecialPageFactory $specialPageFactory
	 * @param CommentFormatter $commentFormatter
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		CheckUserLookupUtils $checkUserLookupUtils,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
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
			$this->checkUserLookupUtils,
			$this->titleFormatter,
			$this->specialPageFactory,
			$this->commentFormatter,
			$this->userFactory,
			$user,
			$language
		);
	}
}
