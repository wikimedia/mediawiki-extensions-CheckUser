<?php

namespace MediaWiki\CheckUser\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use LogicException;
use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\IPUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\EnumDef;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * CheckUser API Query Module
 */
class ApiQueryCheckUser extends ApiQueryBase {

	private UserIdentityLookup $userIdentityLookup;
	private RevisionLookup $revisionLookup;
	private ArchivedRevisionLookup $archivedRevisionLookup;
	private CheckUserLogService $checkUserLogService;
	private CommentStore $commentStore;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param RevisionLookup $revisionLookup
	 * @param ArchivedRevisionLookup $archivedRevisionLookup
	 * @param CheckUserLogService $checkUserLogService
	 * @param CommentStore $commentStore
	 */
	public function __construct(
		$query,
		$moduleName,
		UserIdentityLookup $userIdentityLookup,
		RevisionLookup $revisionLookup,
		ArchivedRevisionLookup $archivedRevisionLookup,
		CheckUserLogService $checkUserLogService,
		CommentStore $commentStore
	) {
		parent::__construct( $query, $moduleName, 'cu' );
		$this->userIdentityLookup = $userIdentityLookup;
		$this->revisionLookup = $revisionLookup;
		$this->archivedRevisionLookup = $archivedRevisionLookup;
		$this->checkUserLogService = $checkUserLogService;
		$this->commentStore = $commentStore;
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

		// Remove whitespace from the beginning and end of the reason. This also prevents the user from providing a
		// reason that is only whitespace (and therefore is not a valid reason) when wgCheckUserForceSummary is true.
		$reason = trim( $reason );
		if ( $this->getConfig()->get( 'CheckUserForceSummary' ) && $reason === '' ) {
			$this->dieWithError( 'apierror-checkuser-missingsummary', 'missingdata' );
		}

		$reason = $this->msg( 'checkuser-reason-api', $reason )->inContentLanguage()->escaped();
		// absolute time
		$timeCutoff = strtotime( $timecond, ConvertibleTimestamp::time() );
		if ( !$timeCutoff || $timeCutoff < 0 || $timeCutoff > ConvertibleTimestamp::time() ) {
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

				$this->addFields( [ 'timestamp' => 'cuc_timestamp', 'ip' => 'cuc_ip' ] );
				$this->addWhereFld( 'actor_user', $user_id );
				$res = $this->select( __METHOD__ );
				$result = $this->getResult();

				$ips = [];
				foreach ( $res as $row ) {
					$timestamp = ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp );
					$ip = strval( $row->ip );

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
				$this->addDeprecation(
					[
						'apiwarn-deprecation-withreplacement', 'curequest=edits', 'curequest=actions'
					],
					'curequest=edits'
				);
				// fall-through for now, eventually delete the entire case statement
			case 'actions':
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
					'namespace' => 'cuc_namespace',
					'title' => 'cuc_title',
					'actiontext' => 'cuc_actiontext',
					'timestamp' => 'cuc_timestamp',
					'minor' => 'cuc_minor',
					'type' => 'cuc_type',
					'this_oldid' => 'cuc_this_oldid',
					'ip' => 'cuc_ip',
					'xff' => 'cuc_xff',
					'agent' => 'cuc_agent',
					'user' => 'actor_cuc_user.actor_user',
					'user_text' => 'actor_cuc_user.actor_name',
				] + $commentQuery['fields'] );
				$this->addJoinConds( $commentQuery['joins'] );

				$res = $this->select( __METHOD__ );
				$result = $this->getResult();

				$edits = [];
				foreach ( $res as $row ) {
					$edit = [
						'timestamp' => ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp ),
						'ns'        => intval( $row->namespace ),
						'title'     => $row->title,
						'user'      => $row->user_text,
						'ip'        => $row->ip,
						'agent'     => $row->agent,
					];

					$comment = $this->commentStore->getComment( 'cuc_comment', $row )->text;

					if ( $row->actiontext ) {
						$edit['summary'] = $row->actiontext;
					} elseif ( $comment ) {
						$edit['summary'] = $comment;
						if ( $row->this_oldid != 0 &&
							( $row->type == RC_EDIT || $row->type == RC_NEW )
						) {
							$revRecord = $this->revisionLookup
								->getRevisionById( $row->this_oldid );
							if ( !$revRecord ) {
								$revRecord = $this->archivedRevisionLookup
									->getArchivedRevisionRecord( null, $row->this_oldid );
							}
							if ( !$revRecord ) {
								// This shouldn't happen, CheckUser points to a revision
								// that isn't in revision nor archive table?
								throw new LogicException(
									"Couldn't fetch revision cu_changes table links to " .
										"(cuc_this_oldid {$row->this_oldid})"
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
					if ( $row->minor ) {
						$edit['minor'] = 'm';
					}
					if ( $row->xff ) {
						$edit['xff'] = $row->xff;
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

				$this->addFields( [
					'timestamp' => 'cuc_timestamp',
					'ip' => 'cuc_ip',
					'agent' => 'cuc_agent',
					'user_text' => 'actor_cuc_user.actor_name'
				] );

				$res = $this->select( __METHOD__ );
				$result = $this->getResult();

				$users = [];
				foreach ( $res as $row ) {
					$user = $row->user_text;
					$ip = $row->ip;
					$agent = $row->agent;

					if ( !isset( $users[$user] ) ) {
						$users[$user] = [
							'end' => ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp ),
							'editcount' => 1,
							'ips' => [ $ip ],
							'agents' => [ $agent ]
						];
					} else {
						$users[$user]['start'] = ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp );
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
					'actions',
					'ipusers',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'edits' => 'apihelp-query+checkuser-paramvalue-request-actions'
				],
				EnumDef::PARAM_DEPRECATED_VALUES => [
					'edits' => true,
				]
			],
			'target'   => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'ip', 'temp', 'cidr' ],
			],
			'reason'   => [
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_REQUIRED => $this->getConfig()->get( 'CheckUserForceSummary' )
			],
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
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=checkuser&curequest=userips&cutarget=Jimbo_Wales'
				=> 'apihelp-query+checkuser-example-1',
			'action=query&list=checkuser&curequest=actions&cutarget=127.0.0.1/16&xff=1&cureason=Some_check'
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
