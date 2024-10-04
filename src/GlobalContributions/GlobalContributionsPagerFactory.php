<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use JobQueueGroup;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\Rdbms\IConnectionProvider;

class GlobalContributionsPagerFactory {
	private LinkRenderer $linkRenderer;
	private LinkBatchFactory $linkBatchFactory;
	private HookContainer $hookContainer;
	private RevisionStore $revisionStore;
	private NamespaceInfo $namespaceInfo;
	private CommentFormatter $commentFormatter;
	private UserFactory $userFactory;
	private TempUserConfig $tempUserConfig;
	private CheckUserLookupUtils $lookupUtils;
	private IConnectionProvider $dbProvider;
	private JobQueueGroup $jobQueueGroup;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param HookContainer $hookContainer
	 * @param RevisionStore $revisionStore
	 * @param NamespaceInfo $namespaceInfo
	 * @param CommentFormatter $commentFormatter
	 * @param UserFactory $userFactory
	 * @param TempUserConfig $tempUserConfig
	 * @param CheckUserLookupUtils $lookupUtils
	 * @param IConnectionProvider $dbProvider
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory,
		HookContainer $hookContainer,
		RevisionStore $revisionStore,
		NamespaceInfo $namespaceInfo,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		TempUserConfig $tempUserConfig,
		CheckUserLookupUtils $lookupUtils,
		IConnectionProvider $dbProvider,
		JobQueueGroup $jobQueueGroup
	) {
		$this->linkRenderer = $linkRenderer;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->hookContainer = $hookContainer;
		$this->revisionStore = $revisionStore;
		$this->namespaceInfo = $namespaceInfo;
		$this->commentFormatter = $commentFormatter;
		$this->userFactory = $userFactory;
		$this->tempUserConfig = $tempUserConfig;
		$this->lookupUtils = $lookupUtils;
		$this->dbProvider = $dbProvider;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @param IContextSource $context
	 * @param array $options
	 * @param UserIdentity $target IP address for temporary user contributions lookup
	 * @return GlobalContributionsPager
	 * @throws ParameterAssertionException
	 */
	public function createPager(
		IContextSource $context,
		array $options,
		UserIdentity $target
	): GlobalContributionsPager {
		$username = $target->getName();
		Assert::parameter( $this->lookupUtils->isValidIPOrRange( $username ), 'username', 'target is invalid' );

		return new GlobalContributionsPager(
			$this->linkRenderer,
			$this->linkBatchFactory,
			$this->hookContainer,
			$this->revisionStore,
			$this->namespaceInfo,
			$this->commentFormatter,
			$this->userFactory,
			$this->tempUserConfig,
			$this->lookupUtils,
			$this->dbProvider,
			$this->jobQueueGroup,
			$context,
			$options,
			$target
		);
	}
}
