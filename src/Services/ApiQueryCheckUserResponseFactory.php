<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserAbstractResponse;
use MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserIpUsersResponse;
use MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserUserIpsResponse;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Config\Config;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use Wikimedia\Rdbms\IConnectionProvider;

class ApiQueryCheckUserResponseFactory {

	private IConnectionProvider $dbProvider;
	private Config $config;
	private MessageLocalizer $messageLocalizer;
	private CheckUserLogService $checkUserLogService;
	private UserNameUtils $userNameUtils;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private UserIdentityLookup $userIdentityLookup;
	private CommentStore $commentStore;
	private RevisionStore $revisionStore;
	private ArchivedRevisionLookup $archivedRevisionLookup;
	private UserFactory $userFactory;

	public function __construct(
		IConnectionProvider $dbProvider,
		Config $config,
		MessageLocalizer $messageLocalizer,
		CheckUserLogService $checkUserLogService,
		UserNameUtils $userNameUtils,
		CheckUserLookupUtils $checkUserLookupUtils,
		UserIdentityLookup $userIdentityLookup,
		CommentStore $commentStore,
		RevisionStore $revisionStore,
		ArchivedRevisionLookup $archivedRevisionLookup,
		UserFactory $userFactory
	) {
		$this->dbProvider = $dbProvider;
		$this->config = $config;
		$this->messageLocalizer = $messageLocalizer;
		$this->checkUserLogService = $checkUserLogService;
		$this->userNameUtils = $userNameUtils;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->commentStore = $commentStore;
		$this->revisionStore = $revisionStore;
		$this->archivedRevisionLookup = $archivedRevisionLookup;
		$this->userFactory = $userFactory;
	}

	/**
	 * @param ApiQueryCheckUser $module The module that is handling the request (you should be able to use $this).
	 * @return ApiQueryCheckUserAbstractResponse
	 */
	public function newFromRequest( ApiQueryCheckUser $module ): ApiQueryCheckUserAbstractResponse {
		// No items for the factory method exist yet, but will be added later.
		switch ( $module->extractRequestParams()['request'] ) {
			case 'userips':
				return new ApiQueryCheckUserUserIpsResponse(
					$module,
					$this->dbProvider,
					$this->config,
					$this->messageLocalizer,
					$this->checkUserLogService,
					$this->userNameUtils,
					$this->checkUserLookupUtils,
					$this->userIdentityLookup,
				);
			case 'ipusers':
				return new ApiQueryCheckUserIpUsersResponse(
					$module,
					$this->dbProvider,
					$this->config,
					$this->messageLocalizer,
					$this->checkUserLogService,
					$this->userNameUtils,
					$this->checkUserLookupUtils
				);
			default:
				$module->dieWithError( 'apierror-checkuser-invalidmode', 'invalidmode' );
		}
	}
}
