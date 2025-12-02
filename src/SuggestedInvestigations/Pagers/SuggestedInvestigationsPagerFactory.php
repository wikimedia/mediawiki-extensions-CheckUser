<?php

namespace MediaWiki\CheckUser\SuggestedInvestigations\Pagers;

use InvalidArgumentException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Allows code to stably construct pagers to display arbitrary revision or log IDs. Currently only support
 * for revision IDs (and deleted revision IDs) exists.
 *
 * NOTE: Private code uses the methods in this class, so changing the signatures of the methods may break code
 * not visible in codesearch.
 */
class SuggestedInvestigationsPagerFactory {

	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly HookContainer $hookContainer,
		private readonly RevisionStore $revisionStore,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly CommentFormatter $commentFormatter,
		private readonly UserFactory $userFactory,
		private readonly IConnectionProvider $dbProvider,
	) {
	}

	/**
	 * Returns a pager that is used by Suggested Investigations to display an arbitrary list of revisions in a
	 * Special:Contributions style format.
	 *
	 * This pager can show archived contributions if `$options['isArchive'] = true;`. However, the returned
	 * pager will either show deleted or non-deleted revisions. The caller is expected to determine if the
	 * user should be seeing deleted revisions.
	 *
	 * @throws InvalidArgumentException if the $revisionIds argument is an empty array
	 * @since 1.46
	 * @stable to call
	 */
	public function createRevisionPager(
		IContextSource $context,
		array $options,
		array $revisionIds,
		?UserIdentity $target = null
	): SuggestedInvestigationsRevisionsPager {
		if ( count( $revisionIds ) === 0 ) {
			throw new InvalidArgumentException( 'Revision IDs array must contain at least one ID' );
		}

		return new SuggestedInvestigationsRevisionsPager(
			$this->linkRenderer,
			$this->linkBatchFactory,
			$this->hookContainer,
			$this->revisionStore,
			$this->namespaceInfo,
			$this->commentFormatter,
			$this->userFactory,
			$context,
			$revisionIds,
			$options,
			$target
		);
	}

	/**
	 * Returns a pager that is used by Suggested Investigations to show suggested investigation cases.
	 *
	 * @internal Only for use by {@link SpecialSuggestedInvestigations}
	 */
	public function createCasesPager( IContextSource $context ): SuggestedInvestigationsCasesPager {
		return new SuggestedInvestigationsCasesPager( $this->dbProvider, $this->linkRenderer, $context );
	}
}
