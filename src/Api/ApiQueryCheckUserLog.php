<?php

namespace MediaWiki\CheckUser\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserLogPager;
use MediaWiki\CheckUser\CheckUserLogService;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\User\UserFactory;
use Wikimedia\IPUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * CheckUser API Query Module
 */
class ApiQueryCheckUserLog extends ApiQueryBase {
	/** @var CommentStore */
	private $commentStore;

	/** @var CheckUserLogService */
	private $checkUserLogService;

	private UserFactory $userFactory;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param CommentStore $commentStore
	 * @param CheckUserLogService $checkUserLogService
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		$query, $moduleName,
		CommentStore $commentStore,
		CheckUserLogService $checkUserLogService,
		UserFactory $userFactory
	) {
		parent::__construct( $query, $moduleName, 'cul' );
		$this->commentStore = $commentStore;
		$this->checkUserLogService = $checkUserLogService;
		$this->userFactory = $userFactory;
	}

	public function execute() {
		$db = $this->getDB();
		$params = $this->extractRequestParams();
		$this->checkUserRightsAny( 'checkuser-log' );

		$limit = $params['limit'];
		$continue = $params['continue'];
		$dir = $params['dir'];

		$this->addTables( [ 'cu_log', 'actor' ] );
		$this->addOption( 'LIMIT', $limit + 1 );
		$this->addTimestampWhereRange( 'cul_timestamp', $dir, $params['from'], $params['to'] );
		$fields = [
			'cul_id', 'cul_timestamp', 'cul_type', 'cul_target_text', 'actor_name'
		];
		$this->addJoinConds( [ 'actor' => [ 'JOIN', 'actor_id=cul_actor' ] ] );

		$reasonCommentQuery = $this->commentStore->getJoin( 'cul_reason' );
		$this->addTables( $reasonCommentQuery['tables'] );
		$this->addJoinConds( $reasonCommentQuery['joins'] );
		$fields += $reasonCommentQuery['fields'];

		if ( isset( $params['reason'] ) ) {
			$plaintextReasonCommentQuery = $this->commentStore->getJoin( 'cul_reason_plaintext' );
			$this->addTables( $plaintextReasonCommentQuery['tables'] );
			$this->addJoinConds( $plaintextReasonCommentQuery['joins'] );
			$fields += $plaintextReasonCommentQuery['fields'];
		}

		$this->addFields( $fields );

		// Order by both timestamp and id
		$order = ( $dir === 'newer' ? '' : ' DESC' );
		$this->addOption( 'ORDER BY', [ 'cul_timestamp' . $order, 'cul_id' . $order ] );

		if ( isset( $params['user'] ) ) {
			$this->addWhereFld( 'actor_name', $params['user'] );
		}
		if ( isset( $params['target'] ) ) {
			if ( IPUtils::isIPAddress( $params['target'] ) ) {
				$cond = CheckUserLogPager::getTargetSearchConds( $params['target'] );
				if ( !$cond ) {
					$this->dieWithError( 'apierror-badip', 'invalidip' );
				}
				$this->addWhere( $cond );
			} else {
				$this->addWhereFld( 'cul_target_text', $params['target'] );
			}
		}

		if ( isset( $params['reason'] ) ) {
			$plaintextReason = $this->checkUserLogService->getPlaintextReason( $params['reason'] );
			$this->addWhereFld(
				'comment_cul_reason_plaintext.comment_text',
				$plaintextReason
			);
		}

		if ( $continue !== null ) {
			$cont = $this->parseContinueParamOrDie( $continue, [ 'timestamp', 'int' ] );
			$op = $dir === 'older' ? '<=' : '>=';
			$this->addWhere( $db->buildComparison( $op, [
				'cul_timestamp' => $db->timestamp( $cont[0] ),
				'cul_id' => $cont[1],
			] ) );
		}

		$res = $this->select( __METHOD__ );
		$result = $this->getResult();

		$count = 0;
		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', "$row->cul_timestamp|$row->cul_id" );
				break;
			}
			$log = [
				'timestamp' => wfTimestamp( TS_ISO_8601, $row->cul_timestamp ),
				'checkuser' => $row->actor_name,
				'type'      => $row->cul_type,
				'reason'    => $this->commentStore->getComment( 'cul_reason', $row )->text,
				'target'    => $row->cul_target_text,
			];

			$checkUser = $this->userFactory->newFromName( $row->actor_name );
			if (
				$checkUser &&
				$checkUser->isHidden() &&
				!$this->getAuthority()->isAllowed( 'hideuser' )
			) {
				$log['checkuser'] = $this->msg( 'rev-deleted-user' )->plain();
			}

			$targetUser = $this->userFactory->newFromName( $row->cul_target_text );
			if (
				$targetUser &&
				$targetUser->isHidden() &&
				!$this->getAuthority()->isAllowed( 'hideuser' )
			) {
				$log['target'] = $this->msg( 'rev-deleted-user' )->plain();
			}
			$fit = $result->addValue( [ 'query', $this->getModuleName(), 'entries' ], null, $log );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', "$row->cul_timestamp|$row->cul_id" );
				break;
			}
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName(), 'entries' ], 'entry' );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'user'   => null,
			'target' => null,
			'reason' => null,
			'limit'  => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN  => 1,
				IntegerDef::PARAM_MAX  => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			],
			'dir' => [
				ParamValidator::PARAM_DEFAULT => 'older',
				ParamValidator::PARAM_TYPE => [
					'newer',
					'older'
				],
				ApiBase::PARAM_HELP_MSG => 'checkuser-api-help-param-direction',
			],
			'from'  => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			'to'    => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=checkuserlog&culuser=Example&cullimit=25'
				=> 'apihelp-query+checkuserlog-example-1',
			'action=query&list=checkuserlog&cultarget=192.0.2.0/24&culfrom=2011-10-15T23:00:00Z'
				=> 'apihelp-query+checkuserlog-example-2',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:CheckUser#API';
	}
}
