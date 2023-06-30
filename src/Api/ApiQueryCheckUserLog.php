<?php

namespace MediaWiki\CheckUser\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use MediaWiki\CheckUser\LogPager;
use MediaWiki\User\UserFactory;
use Wikimedia\IPUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * CheckUser API Query Module
 */
class ApiQueryCheckUserLog extends ApiQueryBase {

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param UserFactory $userFactory
	 */
	public function __construct( $query, $moduleName, UserFactory $userFactory ) {
		parent::__construct( $query, $moduleName, 'cul' );
		$this->userFactory = $userFactory;
	}

	public function execute() {
		$db = $this->getDB();
		$params = $this->extractRequestParams();
		$this->checkUserRightsAny( 'checkuser-log' );

		$limit = $params['limit'];
		$continue = $params['continue'];
		$dir = $params['dir'];

		$this->addTables( 'cu_log' );
		$this->addOption( 'LIMIT', $limit + 1 );
		$this->addTimestampWhereRange( 'cul_timestamp', $dir, $params['from'], $params['to'] );
		$this->addFields( [
			'cul_id', 'cul_timestamp', 'cul_user_text', 'cul_reason', 'cul_type', 'cul_target_text'
		] );

		// Order by both timestamp and id
		$order = ( $dir === 'newer' ? '' : ' DESC' );
		$this->addOption( 'ORDER BY', [ 'cul_timestamp' . $order, 'cul_id' . $order ] );

		if ( isset( $params['user'] ) ) {
			$this->addWhereFld( 'cul_user_text', $params['user'] );
		}
		if ( isset( $params['target'] ) ) {
			if ( IPUtils::isIPAddress( $params['target'] ) ) {
				$cond = LogPager::getTargetSearchConds( $params['target'] );
				if ( !$cond ) {
					$this->dieWithError( 'apierror-badip', 'invalidip' );
				}
				$this->addWhere( $cond );
			} else {
				$this->addWhereFld( 'cul_target_text', $params['target'] );
			}
		}

		if ( $continue !== null ) {
			$cont = explode( '|', $continue );
			$op = $dir === 'older' ? '<' : '>';
			$this->dieContinueUsageIf( count( $cont ) !== 2 );
			$this->dieContinueUsageIf( wfTimestamp( TS_UNIX, $cont[0] ) === false );

			$timestamp = $db->addQuotes( $db->timestamp( $cont[0] ) );
			$id = intval( $cont[1] );
			$this->dieContinueUsageIf( $cont[1] !== (string)$id );

			$this->addWhere(
				"cul_timestamp $op $timestamp OR " .
				"(cul_timestamp = $timestamp AND " .
				"cul_id $op= $id)"
			);
		}

		$res = $this->select( __METHOD__ );
		$result = $this->getResult();

		$count = 0;
		$makeContinue = static function ( $row ) {
			return wfTimestamp( TS_ISO_8601, $row->cul_timestamp ) . '|' . $row->cul_id;
		};
		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $makeContinue( $row ) );
				break;
			}
			$log = [
				'timestamp' => wfTimestamp( TS_ISO_8601, $row->cul_timestamp ),
				'checkuser' => $row->cul_user_text,
				'type'      => $row->cul_type,
				'reason'    => $row->cul_reason,
				'target'    => $row->cul_target_text,
			];

			$checkUser = $this->userFactory->newFromName( $row->cul_user_text );
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
				$this->setContinueEnumParameter( 'continue', $makeContinue( $row ) );
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

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
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
