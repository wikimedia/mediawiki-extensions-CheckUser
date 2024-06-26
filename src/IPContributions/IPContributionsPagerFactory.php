<?php

namespace MediaWiki\CheckUser\IPContributions;

use InvalidArgumentException;
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

class IPContributionsPagerFactory {
	private LinkRenderer $linkRenderer;
	private LinkBatchFactory $linkBatchFactory;
	private HookContainer $hookContainer;
	private RevisionStore $revisionStore;
	private NamespaceInfo $namespaceInfo;
	private CommentFormatter $commentFormatter;
	private UserFactory $userFactory;
	private TempUserConfig $tempUserConfig;
	private CheckUserLookupUtils $lookupUtils;
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
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @param IContextSource $context
	 * @param array $options
	 * @param UserIdentity $target IP address for temporary user contributions lookup
	 * @return IPContributionsPager
	 */
	public function createPager(
		IContextSource $context,
		array $options,
		UserIdentity $target
	): IPContributionsPager {
		$username = $target->getName();
		if ( !$this->lookupUtils->isValidIPOrRange( $username ) ) {
			throw new InvalidArgumentException( "Invalid target: $username" );
		}
		return new IPContributionsPager(
			$this->linkRenderer,
			$this->linkBatchFactory,
			$this->hookContainer,
			$this->revisionStore,
			$this->namespaceInfo,
			$this->commentFormatter,
			$this->userFactory,
			$this->tempUserConfig,
			$this->lookupUtils,
			$this->jobQueueGroup,
			$context,
			$options,
			$target
		);
	}
}
