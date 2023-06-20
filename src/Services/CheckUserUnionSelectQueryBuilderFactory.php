<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWiki\CommentStore\CommentStore;
use Wikimedia\Rdbms\IReadableDatabase;

class CheckUserUnionSelectQueryBuilderFactory {
	/** @var CommentStore */
	private CommentStore $commentStore;

	/**
	 * @param CommentStore $commentStore
	 */
	public function __construct(
		CommentStore $commentStore
	) {
		$this->commentStore = $commentStore;
	}

	/**
	 * Gets a CheckUserUnionSelectQueryBuilder.
	 *
	 * @param IReadableDatabase $db The database to perform the SELECTs on.
	 * @return CheckUserUnionSelectQueryBuilder
	 */
	public function newCheckUserSelectQueryBuilder(
		IReadableDatabase $db
	) {
		return new CheckUserUnionSelectQueryBuilder(
			$db,
			$this->commentStore
		);
	}
}
