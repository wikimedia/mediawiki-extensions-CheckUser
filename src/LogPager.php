<?php

namespace MediaWiki\CheckUser;

use CommentStore;
use Html;
use IContextSource;
use Linker;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Specials\SpecialCheckUserLog;
use RangeChronologicalPager;
use Wikimedia\Rdbms\IResultWrapper;

class LogPager extends RangeChronologicalPager {
	/**
	 * @var array
	 */
	protected $searchConds;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var CommentStore */
	private $commentStore;

	/**
	 * @param IContextSource $context
	 * @param array $opts A array of keys that can include 'target', 'initiator', 'start', 'end'
	 * 		'year' and 'month'. Target should be a user, IP address or IP range. Initiator should be a user.
	 * 		Start and end should be timestamps. Year and month are converted to end but ignored if end is
	 * 		provided.
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param CommentStore $commentStore
	 */
	public function __construct(
		IContextSource $context,
		array $opts,
		LinkBatchFactory $linkBatchFactory,
		CommentStore $commentStore
	) {
		parent::__construct( $context );
		// Default to all log entries - we'll add conditions below if a target was provided
		$targetSearchConds = [];
		$initiatorSearchConds = [];

		if ( $opts['target'] !== '' ) {
			$targetSearchConds = $this->getTargetSearchConds( $opts['target'] );
		}

		if ( $opts['initiator'] !== '' ) {
			$initiatorSearchConds = $this->getPerformerSearchConds( $opts['initiator'] );
		}

		if ( $targetSearchConds === null || $initiatorSearchConds === null ) {
			throw new \Exception( 'An invalid initiator or target was provided.' );
		}

		$reasonSearchConds = [];

		if ( $opts['reason'] ) {
			$reasonSearchConds = $this->getReasonSearchConds( $opts['reason'], $opts['wildcardSearch'] );
		}

		$this->searchConds = array_merge( $targetSearchConds, $initiatorSearchConds, $reasonSearchConds );

		// Date filtering: use timestamp if available - From SpecialContributions.php
		$startTimestamp = '';
		$endTimestamp = '';
		if ( isset( $opts['start'] ) && $opts['start'] ) {
			$startTimestamp = $opts['start'] . ' 00:00:00';
		}
		if ( isset( $opts['end'] ) && $opts['end'] ) {
			$endTimestamp = $opts['end'] . ' 23:59:59';
		}
		$this->getDateRangeCond( $startTimestamp, $endTimestamp );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->commentStore = $commentStore;
	}

	public function formatRow( $row ) {
		$user = Linker::userLink( $row->cul_user, $row->user_name );

		$target = Linker::userLink( $row->cul_target_id, $row->cul_target_text ) .
			Linker::userToolLinks( $row->cul_target_id, trim( $row->cul_target_text ) );

		$lang = $this->getLanguage();
		$contextUser = $this->getUser();
		// Give grep a chance to find the usages:
		// checkuser-log-entry-userips, checkuser-log-entry-ipedits,
		// checkuser-log-entry-ipusers, checkuser-log-entry-ipedits-xff
		// checkuser-log-entry-ipusers-xff, checkuser-log-entry-useredits
		$rowContent = $this->msg(
			'checkuser-log-entry-' . $row->cul_type,
			$user,
			$target,
			htmlspecialchars(
				$lang->userTimeAndDate( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser )
			),
			htmlspecialchars(
				$lang->userDate( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser )
			),
			htmlspecialchars(
				$lang->userTime( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser )
			)
		)->text();
		if ( isset( $row->cul_reason_text ) ) {
			// If the JOIN could be applied then use cul_reason_text.
			$rowContent .= Linker::commentBlock(
				$this->commentStore->getComment( 'cul_reason', $row )->text
			);
		} elseif ( isset( $row->cul_reason_id ) ) {
			// If cul_reason_id is both set and not null, then use that over cul_reason.
			$dbr = wfGetDB( DB_REPLICA );
			$rowContent .= Linker::commentBlock(
				$this->commentStore->getCommentLegacy( $dbr, 'cul_reason', $row )->text
			);
		} else {
			$rowContent .= Linker::commentBlock( $row->cul_reason );
		}

		$attribs = [
			'data-mw-culogid' => $row->cul_id,
		];
		return Html::rawElement( 'li', $attribs, $rowContent ) . "\n";
	}

	/**
	 * @return string
	 */
	public function getStartBody() {
		if ( $this->getNumRows() ) {
			return '<ul>';
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public function getEndBody() {
		if ( $this->getNumRows() ) {
			return '</ul>';
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public function getEmptyBody() {
		return '<p>' . $this->msg( 'checkuser-empty' )->escaped() . '</p>';
	}

	public function getQueryInfo() {
		$queryInfo = [
			'tables' => [ 'cu_log', 'user' ],
			'fields' => $this->selectFields(),
			'conds' => array_merge( $this->searchConds, [ 'user_id = cul_user' ] ),
			'join_conds' => [],
		];
		$dbr = wfGetDB( DB_REPLICA );
		$fieldInfo = $dbr->fieldInfo( 'cu_log', 'cul_reason_id' );
		if ( $fieldInfo !== false ) {
			if ( !$fieldInfo->isNullable() ) {
				// Only attempt to join if the field is not nullable
				// While the field is still nullable there could be null entries
				// which would cause the join to fail.
				$reasonCommentStore = $this->commentStore->getJoin( 'cul_reason' );
				$queryInfo['tables'] += $reasonCommentStore['tables'];
				$queryInfo['fields'] += $reasonCommentStore['fields'];
				$queryInfo['join_conds'] += $reasonCommentStore['joins'];
			} else {
				// cul_reason_id exists but could be null.
				// Therefore select for both cul_reason_id and cul_reason while
				// letting the code determine which to use. If cul_reason_id is used
				// the code will fall back to getCommentLegacy().
				$queryInfo['fields'][] = 'cul_reason_id';
				$queryInfo['fields'][] = 'cul_reason';
			}
		} else {
			// cul_reason_id does not exist therefore, the patch has not
			// been applied and so just read from cul_reason.
			$queryInfo['fields'][] = 'cul_reason';
		}
		$fieldInfo = $dbr->fieldInfo( 'cu_log', 'cul_reason_plaintext_id' );
		if ( $fieldInfo !== false ) {
			if ( !$fieldInfo->isNullable() ) {
				$plaintextReasonCommentStore = $this->commentStore->getJoin( 'cul_reason_plaintext' );
				$queryInfo['tables'] += $plaintextReasonCommentStore['tables'];
				$queryInfo['fields'] += $plaintextReasonCommentStore['fields'];
				$queryInfo['join_conds'] += $plaintextReasonCommentStore['joins'];
			} else {
				$queryInfo['fields'][] = 'cul_reason_plaintext_id';
			}
		}
		return $queryInfo;
	}

	public function getIndexField() {
		return 'cul_timestamp';
	}

	public function selectFields() {
		return [
			'cul_id', 'cul_timestamp', 'cul_user', 'cul_type',
			'cul_target_id', 'cul_target_text', 'user_name'
		];
	}

	/**
	 * Do a batch query for links' existence and add it to LinkCache
	 *
	 * @param IResultWrapper $result
	 */
	protected function preprocessResults( $result ) {
		if ( $this->getNumRows() === 0 ) {
			return;
		}

		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );
		foreach ( $result as $row ) {
			// Performer
			$lb->add( NS_USER, $row->user_name );

			if ( $row->cul_type == 'userips' || $row->cul_type == 'useredits' ) {
				$lb->add( NS_USER, $row->cul_target_text );
				$lb->add( NS_USER_TALK, $row->cul_target_text );
			}
		}
		$lb->execute();
		$result->seek( 0 );
	}

	/**
	 * Get DB search conditions for the initiator
	 *
	 * @param string $initiator the username of the initiator.
	 * @return array|null array if valid target, null if invalid
	 */
	public static function getPerformerSearchConds( string $initiator ) {
		$initiatorObject = SpecialCheckUserLog::verifyInitiator( $initiator );
		if ( $initiatorObject !== false ) {
			return [ 'cul_user' => $initiatorObject ];
		}
		return null;
	}

	/**
	 * Get DB search conditions according to the CU target given.
	 *
	 * @param string $target the username, IP address or range of the target.
	 * @return array|null array if valid target, null if invalid target given
	 */
	public static function getTargetSearchConds( string $target ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = SpecialCheckUserLog::verifyTarget( $target );
		if ( is_array( $result ) ) {
			switch ( count( $result ) ) {
				case 1:
					return [
						'cul_target_hex = ' . $dbr->addQuotes( $result[0] ) . ' OR ' .
						'(cul_range_end >= ' . $dbr->addQuotes( $result[0] ) . ' AND ' .
						'cul_range_start <= ' . $dbr->addQuotes( $result[0] ) . ')'
					];
				case 2:
					return [
						'(cul_target_hex >= ' . $dbr->addQuotes( $result[0] ) . ' AND ' .
						'cul_target_hex <= ' . $dbr->addQuotes( $result[1] ) . ') OR ' .
						'(cul_range_end >= ' . $dbr->addQuotes( $result[0] ) . ' AND ' .
						'cul_range_start <= ' . $dbr->addQuotes( $result[1] ) . ')'
					];
			}
		} elseif ( is_int( $result ) ) {
			return [
				'cul_type' => [ 'userips', 'useredits', 'investigate' ],
				'cul_target_id' => $result,
			];
		} else {
			return null;
		}
	}

	/**
	 * @param string $reason The reason search string
	 * @param bool $wildcardEnabled whether to use a wildcard.
	 * @return array|string[]
	 */
	private function getReasonSearchConds( $reason, $wildcardEnabled ) {
		if ( $reason ) {
			$dbr = wfGetDB( DB_REPLICA );
			$fieldReasonInfo = $dbr->fieldInfo( 'cu_log', 'cul_reason_id' );
			$fieldReasonPlaintextInfo = $dbr->fieldInfo( 'cu_log', 'cul_reason_plaintext_id' );
			$reasonFieldNames = [];
			if ( $fieldReasonPlaintextInfo !== false && !$fieldReasonPlaintextInfo->isNullable() ) {
				$reasonFieldNames[] = 'cul_reason_plaintext_text';
			}
			if ( $fieldReasonInfo !== false && !$fieldReasonInfo->isNullable() ) {
				$reasonFieldNames[] = 'cul_reason_text';
			} else {
				$reasonFieldNames[] = 'cul_reason';
			}
			$returnConds = [];
			foreach ( $reasonFieldNames as $fieldName ) {
				if ( $wildcardEnabled && $this->getConfig()->get( 'CheckUserLogEnableWildcardSearch' ) ) {
					$returnConds[] = $fieldName . $dbr->buildLike( $dbr->anyString(), $reason, $dbr->anyString() );
				} else {
					$returnConds[] = $fieldName . ' = ' . $dbr->addQuotes( $reason );
				}
			}
			return [ implode( ' OR ', $returnConds ) ];
		} else {
			return [];
		}
	}
}
