<?php

namespace MediaWiki\CheckUser;

use IDatabase;
use MediaWiki\CommentStore\CommentStore;

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
	 * @param IDatabase $db The database to perform the SELECTs on.
	 * @return CheckUserUnionSelectQueryBuilder
	 */
	public function newCheckUserSelectQueryBuilder(
		IDatabase $db
	) {
		return new CheckUserUnionSelectQueryBuilder(
			$db,
			$this->commentStore
		);
	}
}
