<?php

/**
 * CheckUser API Query Module
 */
class ApiQueryCheckUserLog extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cul' );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		if ( !$this->getUser()->isAllowed( 'checkuser-log' ) ) {
			$this->dieUsage( 'You need the checkuser-log right', 'permissionerror' );
		}

		$limit = $params['limit'];
		$continue = $params['continue'];
		$dir = $params['dir'];

		$this->addTables( 'cu_log' );
		$this->addOption( 'LIMIT', $limit + 1 );
		$this->addTimestampWhereRange( 'cul_timestamp', $dir, $params['from'], $params['to'] );
		$this->addFields( array(
			'cul_id', 'cul_timestamp', 'cul_user_text', 'cul_reason', 'cul_type', 'cul_target_text' ) );

		// Order by both timestamp and id
		$order = ( $dir === 'newer' ? '' : ' DESC' );
		$this->addOption( 'ORDER BY', array( 'cul_timestamp' . $order, 'cul_id' . $order ) );

		if ( isset( $params['user'] ) ) {
			$this->addWhereFld( 'cul_user_text', $params['user'] );
		}
		if ( isset( $params['target'] ) ) {
			$this->addWhereFld( 'cul_target_text', $params['target'] );
		}

		if ( $continue !== null ) {
			$cont = explode( '|', $continue );
			$op = $dir === 'older' ? '<' : '>';
			if ( count( $cont ) !== 2 || wfTimestamp( TS_UNIX, $cont[0] ) === false ) {
				$this->dieUsage( 'Invalid continue param. You should pass the ' .
								'original value returned by the previous query', '_badcontinue' );
			}

			$db = $this->getDB();
			$timestamp = $db->addQuotes( $db->timestamp( $cont[0] ) );
			$id = intval( $cont[1] );

			$this->addWhere(
				"cul_timestamp $op $timestamp OR " .
				"(cul_timestamp = $timestamp AND " .
				"cul_id $op= $id)"
			);
		}

		$res = $this->select( __METHOD__ );
		$result = $this->getResult();

		$count = 0;
		$makeContinue = function ( $row ) {
			return wfTimestamp( TS_ISO_8601, $row->cul_timestamp ) . '|' . $row->cul_id;
		};
		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $makeContinue( $row ) );
				break;
			}
			$log = array(
				'timestamp' => wfTimestamp( TS_ISO_8601, $row->cul_timestamp ),
				'checkuser' => $row->cul_user_text,
				'type'      => $row->cul_type,
				'reason'    => $row->cul_reason,
				'target'    => $row->cul_target_text,
			);
			$fit = $result->addValue( array( 'query', $this->getModuleName(), 'entries' ), null, $log );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $makeContinue( $row ) );
				break;
			}
		}

		$result->setIndexedTagName_internal(
			array( 'query', $this->getModuleName(), 'entries' ), 'entry' );
	}

	public function getAllowedParams() {
		return array(
			'user'   => null,
			'target' => null,
			'limit'  => array(
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN  => 1,
				ApiBase::PARAM_MAX  => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			),
			'dir' => array(
				ApiBase::PARAM_DFLT => 'older',
				ApiBase::PARAM_TYPE => array(
					'newer',
					'older'
				)
			),
			'from'  => array(
				ApiBase::PARAM_TYPE => 'timestamp',
			),
			'to'    => array(
				ApiBase::PARAM_TYPE => 'timestamp',
			),
			'continue' => null,
		);
	}

	public function getParamDescription() {
		$p = $this->getModulePrefix();
		return array(
			'user'   => 'Username of CheckUser',
			'target' => "Checked user or IP-address/range",
			'limit'  => 'Limit of rows',
			'dir' => array( "In which direction to enumerate}",
				" newer          - List oldest first. Note: {$p}from has to be before {$p}to.",
				" older          - List newest first (default). Note: {$p}from has to be later than {$p}to." ),
			'from'   => 'The timestamp to start enumerating from',
			'to'     => 'The timestamp to end enumerating',
			'continue' => 'When more results are available, use this to continue',
		);
	}

	public function getDescription() {
		return 'Allows get entries of CheckUser log';
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(),
			array(
				array( 'permissionerror' ),
			)
		);
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=checkuserlog&culuser=WikiSysop&cullimit=25',
			'api.php?action=query&list=checkuserlog&cultarget=127.0.0.1&culfrom=20111015230000',
		);
	}

	public function getHelpUrls() {
		return 'http://www.mediawiki.org/wiki/Extension:CheckUser#API';
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
