<?php

namespace MediaWiki\CheckUser\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use Exception;
use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * CheckUser API Query Module
 */
class ApiQueryCheckUser extends ApiQueryBase {

	private UserIdentityLookup $userIdentityLookup;
	private RevisionLookup $revisionLookup;
	private ArchivedRevisionLookup $archivedRevisionLookup;
	private CheckUserLogService $checkUserLogService;
	private CommentStore $commentStore;
	private UserFactory $userFactory;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param RevisionLookup $revisionLookup
	 * @param ArchivedRevisionLookup $archivedRevisionLookup
	 * @param CheckUserLogService $checkUserLogService
	 * @param CommentStore $commentStore
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		$query,
		$moduleName,
		UserIdentityLookup $userIdentityLookup,
		RevisionLookup $revisionLookup,
		ArchivedRevisionLookup $archivedRevisionLookup,
		CheckUserLogService $checkUserLogService,
		CommentStore $commentStore,
		UserFactory $userFactory
	) {
		parent::__construct( $query, $moduleName, 'cu' );
		$this->userIdentityLookup = $userIdentityLookup;
		$this->revisionLookup = $revisionLookup;
		$this->archivedRevisionLookup = $archivedRevisionLookup;
		$this->checkUserLogService = $checkUserLogService;
		$this->commentStore = $commentStore;
		$this->userFactory = $userFactory;
	}

	public function execute() {
		$dbr = $this->getDB();

		[
			'request' => $request,
			'target' => $target,
			'reason' => $reason,
			'timecond' => $timecond,
			'limit' => $limit,
			'xff' => $xff,
		] = $this->extractRequestParams();

		$this->checkUserRightsAny( 'checkuser' );

		if ( $this->getConfig()->get( 'CheckUserForceSummary' ) && $reason === null ) {
			$this->dieWithError( 'apierror-checkuser-missingsummary', 'missingdata' );
		}

		$reason = $this->msg( 'checkuser-reason-api', $reason )->inContentLanguage()->escaped();
		// absolute time
		$timeCutoff = strtotime( $timecond );
		if ( !$timeCutoff || $timeCutoff < 0 || $timeCutoff > time() ) {
			$this->dieWithError( 'apierror-checkuser-timelimit', 'invalidtime' );
		}

		$targetTitle = Title::makeTitleSafe( NS_USER, $target );
		$target = $targetTitle ? $targetTitle->getText() : '';

		$commentQuery = $this->commentStore->getJoin( 'cuc_comment' );

		$this->addTables( [ 'cu_changes', 'actor_cuc_user' => 'actor' ] );
		$this->addOption( 'LIMIT', $limit + 1 );
		$this->addOption( 'ORDER BY', 'cuc_timestamp DESC' );
		$this->addWhere( "cuc_timestamp > " . $dbr->addQuotes( $dbr->timestamp( $timeCutoff ) ) );
		$this->addJoinConds( [ 'actor_cuc_user' => [ 'JOIN', 'actor_cuc_user.actor_id=cuc_actor' ] ] );

		switch ( $request ) {
			case 'userips':
				$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $target );
				if ( $userIdentity && $userIdentity->getId() ) {
					$user_id = $userIdentity->getId();
				} else {
					$this->dieWithError(
						[ 'nosuchusershort', wfEscapeWikiText( $target ) ], 'nosuchuser'
					);
				}

				$this->addFields( [ 'cuc_timestamp', 'cuc_ip', 'cuc_xff' ] );
				$this->addWhereFld( 'actor_user', $user_id );
				$res = $this->select( __METHOD__ );
				$result = $this->getResult();

				$ips = [];
				foreach ( $res as $row ) {
					$timestamp = wfTimestamp( TS_ISO_8601, $row->cuc_timestamp );
					$ip = strval( $row->cuc_ip );

					if ( !isset( $ips[$ip] ) ) {
						$ips[$ip] = [
							'end' => $timestamp,
							'editcount' => 1
						];
					} else {
						$ips[$ip]['start'] = $timestamp;
						$ips[$ip]['editcount']++;
					}
				}

				$resultIPs = [];
				foreach ( $ips as $ip => $data ) {
					$data['address'] = $ip;
					$resultIPs[] = $data;
				}

				$this->checkUserLogService->addLogEntry( $this->getUser(), 'userips',
					'user', $target, $reason, $user_id );
				$result->addValue( [
					'query', $this->getModuleName() ], 'userips', $resultIPs );
				$result->addIndexedTagName( [
					'query', $this->getModuleName(), 'userips' ], 'ip' );
				break;

			case 'edits':
				if ( IPUtils::isIPAddress( $target ) ) {
					$cond = AbstractCheckUserPager::getIpConds( $dbr, $target, isset( $xff ) );
					if ( !$cond ) {
						$this->dieWithError( 'apierror-badip', 'invalidip' );
					}
					$this->addWhere( $cond );
					$log_type = [];
					if ( isset( $xff ) ) {
						$log_type[] = 'ipedits-xff';
					} else {
						$log_type[] = 'ipedits';
					}
					$log_type[] = 'ip';
				} else {
					$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $target );
					if ( $userIdentity && $userIdentity->getId() ) {
						$user_id = $userIdentity->getId();
					} else {
						$this->dieWithError(
							[ 'nosuchusershort', wfEscapeWikiText( $target ) ], 'nosuchuser'
						);
					}
					$this->addWhereFld( 'actor_user', $user_id );
					$log_type = [ 'useredits', 'user' ];
				}

				$this->addTables( $commentQuery['tables'] );
				$this->addFields( [
					'cuc_namespace', 'cuc_title', 'cuc_actiontext', 'cuc_this_oldid',
					'cuc_minor', 'cuc_timestamp', 'cuc_ip', 'cuc_xff', 'cuc_agent', 'cuc_type',
					'cuc_user' => 'actor_cuc_user.actor_user',
					'cuc_user_text' => 'actor_cuc_user.actor_name',
				] + $commentQuery['fields'] );
				$this->addJoinConds( $commentQuery['joins'] );

				$res = $this->select( __METHOD__ );
				$result = $this->getResult();

				$edits = [];
				foreach ( $res as $row ) {
					$edit = [
						'timestamp' => wfTimestamp( TS_ISO_8601, $row->cuc_timestamp ),
						'ns'        => intval( $row->cuc_namespace ),
						'title'     => $row->cuc_title,
						'user'      => $row->cuc_user_text,
						'ip'        => $row->cuc_ip,
						'agent'     => $row->cuc_agent,
					];

					$user = $this->userFactory->newFromUserIdentity(
						new UserIdentityValue( $row->cuc_user ?? 0, $row->cuc_user_text )
					);

					// If the 'user' key is a username which the current authority cannot see, then replace it with the
					// 'rev-deleted-user' message.
					if ( $user->isHidden() && !$this->getUser()->isAllowed( 'hideuser' ) ) {
						$edit['user'] = $this->msg( 'rev-deleted-user' )->text();
					}

					// If the title is a user page and the username in this user page link is hidden
					// from the current authority, then replace the title with the 'rev-deleted-user' message.
					$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
					if ( $title->getNamespace() === NS_USER ) {
						$titleUser = $this->userFactory->newFromName( $title->getBaseText() );
						if (
							$titleUser &&
							$titleUser->isHidden() &&
							!$this->getUser()->isAllowed( 'hideuser' )
						) {
							$edit['title'] = $this->msg( 'rev-deleted-user' )->text();
						}
					}

					$comment = $this->commentStore->getComment( 'cuc_comment', $row )->text;

					if ( $row->cuc_actiontext ) {
						$edit['summary'] = $row->cuc_actiontext;
					} elseif ( $comment ) {
						$edit['summary'] = $comment;
						if ( $row->cuc_this_oldid != 0 &&
							( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW )
						) {
							$revRecord = $this->revisionLookup
								->getRevisionById( $row->cuc_this_oldid );
							if ( !$revRecord ) {
								$revRecord = $this->archivedRevisionLookup
									->getArchivedRevisionRecord( null, $row->cuc_this_oldid );
							}
							if ( !$revRecord ) {
								// This shouldn't happen, CheckUser points to a revision
								// that isn't in revision nor archive table?
								throw new Exception(
									"Couldn't fetch revision cu_changes table links to " .
										"(cuc_this_oldid {$row->cuc_this_oldid})"
								);
							}
							if ( !RevisionRecord::userCanBitfield(
								$revRecord->getVisibility(),
								RevisionRecord::DELETED_COMMENT,
								$this->getUser()
							) ) {
								$edit['summary'] = $this->msg( 'rev-deleted-comment' )->text();
							}
							if ( !RevisionRecord::userCanBitfield(
								$revRecord->getVisibility(),
								RevisionRecord::DELETED_USER,
								$this->getUser()
							) ) {
								$edit['user'] = $this->msg( 'rev-deleted-user' )->text();
							}
						}
					}
					if ( $row->cuc_minor ) {
						$edit['minor'] = 'm';
					}
					if ( $row->cuc_xff ) {
						$edit['xff'] = $row->cuc_xff;
					}
					$edits[] = $edit;
				}

				$this->checkUserLogService->addLogEntry( $this->getUser(), $log_type[0], $log_type[1],
					$target, $reason, $user_id ?? '0' );
				$result->addValue( [
					'query', $this->getModuleName() ], 'edits', $edits );
				$result->addIndexedTagName( [
					'query', $this->getModuleName(), 'edits' ], 'action' );
				break;

			case 'ipusers':
				if ( IPUtils::isIPAddress( $target ) ) {
					$cond = AbstractCheckUserPager::getIpConds( $dbr, $target, isset( $xff ) );
					$this->addWhere( $cond );
					$log_type = 'ipusers';
					if ( isset( $xff ) ) {
						$log_type .= '-xff';
					}
				} else {
					$this->dieWithError( 'apierror-badip', 'invalidip' );
				}

				$this->addFields( [ 'cuc_timestamp', 'cuc_ip', 'cuc_agent',
					'cuc_user_text' => 'actor_cuc_user.actor_name' ] );

				$res = $this->select( __METHOD__ );
				$result = $this->getResult();

				$users = [];
				foreach ( $res as $row ) {
					$user = $row->cuc_user_text;
					$ip = $row->cuc_ip;
					$agent = $row->cuc_agent;

					if ( !isset( $users[$user] ) ) {
						$users[$user] = [
							'end' => wfTimestamp( TS_ISO_8601, $row->cuc_timestamp ),
							'editcount' => 1,
							'ips' => [ $ip ],
							'agents' => [ $agent ]
						];
					} else {
						$users[$user]['start'] = wfTimestamp( TS_ISO_8601, $row->cuc_timestamp );
						$users[$user]['editcount']++;
						if ( !in_array( $ip, $users[$user]['ips'] ) ) {
							$users[$user]['ips'][] = $ip;
						}
						if ( !in_array( $agent, $users[$user]['agents'] ) ) {
							$users[$user]['agents'][] = $agent;
						}
					}
				}

				$resultUsers = [];
				foreach ( $users as $userName => $userData ) {
					// Hide the user name if it is hidden from the current authority.
					$user = $this->userFactory->newFromName( $userName );
					if ( $user !== null && $user->isHidden() && !$this->getUser()->isAllowed( 'hideuser' ) ) {
						// If the username is hidden from the current user, then hide the username in the
						// results using the 'rev-deleted-user' message.
						$userName = $this->msg( 'rev-deleted-user' )->text();
					}

					$userData['name'] = $userName;
					ApiResult::setIndexedTagName( $userData['ips'], 'ip' );
					ApiResult::setIndexedTagName( $userData['agents'], 'agent' );

					$resultUsers[] = $userData;
				}

				$this->checkUserLogService->addLogEntry( $this->getUser(), $log_type,
					'ip', $target, $reason );
				$result->addValue( [
					'query', $this->getModuleName() ], 'ipusers', $resultUsers );
				$result->addIndexedTagName( [
					'query', $this->getModuleName(), 'ipusers' ], 'user' );
				break;

			default:
				$this->dieWithError( 'apierror-checkuser-invalidmode', 'invalidmode' );
		}
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'request'  => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [
					'userips',
					'edits',
					'ipusers',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'target'   => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reason'   => null,
			'limit'    => [
				ParamValidator::PARAM_DEFAULT => 500,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN  => 1,
				IntegerDef::PARAM_MAX  => 500,
				IntegerDef::PARAM_MAX2 => $this->getConfig()->get( 'CheckUserMaximumRowCount' ),
			],
			'timecond' => [
				ParamValidator::PARAM_DEFAULT => '-2 weeks'
			],
			'xff'      => null,
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=checkuser&curequest=userips&cutarget=Jimbo_Wales'
				=> 'apihelp-query+checkuser-example-1',
			'action=query&list=checkuser&curequest=edits&cutarget=127.0.0.1/16&xff=1&cureason=Some_check'
				=> 'apihelp-query+checkuser-example-2',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:CheckUser#API';
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}
}
