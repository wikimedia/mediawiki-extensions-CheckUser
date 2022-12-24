<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use CommentStore;
use Html;
use IContextSource;
use Linker;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog;
use MediaWiki\CommentFormatter\CommentFormatter;
use RangeChronologicalPager;
use SpecialPage;
use Wikimedia\Rdbms\IResultWrapper;

class CheckUserLogPager extends RangeChronologicalPager {
	/** @var array */
	protected $searchConds;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var CommentStore */
	private $commentStore;

	/** @var CommentFormatter */
	private $commentFormatter;

	/** @var array */
	private $opts;

	/**
	 * @param IContextSource $context
	 * @param array $opts A array of keys that can include 'target', 'initiator', 'start', 'end'
	 * 		'year' and 'month'. Target should be a user, IP address or IP range. Initiator should be a user.
	 * 		Start and end should be timestamps. Year and month are converted to end but ignored if end is
	 * 		provided.
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param CommentStore $commentStore
	 * @param CommentFormatter $commentFormatter
	 */
	public function __construct(
		IContextSource $context,
		array $opts,
		LinkBatchFactory $linkBatchFactory,
		CommentStore $commentStore,
		CommentFormatter $commentFormatter
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

		$this->searchConds = array_merge( $targetSearchConds, $initiatorSearchConds );

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
		$this->commentFormatter = $commentFormatter;
		$this->opts = $opts;
	}

	/**
	 * If appropriate, generate a link that wraps around the provided date, time, or
	 * date and time. The date and time is escaped by this function.
	 *
	 * @param string $dateAndTime The string representation of the date, time or date and time.
	 * @param array|\stdClass $row The current row being formatted in formatRow().
	 * @return string|null The date and time wrapped in a link if appropriate.
	 */
	protected function generateTimestampLink( string $dateAndTime, $row ) {
		$highlight = $this->getRequest()->getVal( 'highlight' );
		// Add appropriate classes to the date and time.
		$dateAndTimeClasses = [];
		if (
			$highlight === strval( $row->cul_timestamp )
		) {
			$dateAndTimeClasses[] = 'mw-checkuser-log-highlight-entry';
		}
		// If the CU log search has a specified target or initiator then
		// provide a link to this log entry without the current filtering
		// for these values.
		if (
			$this->opts['target'] ||
			$this->opts['initiator']
		) {
			return $this->getLinkRenderer()->makeLink(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				$dateAndTime,
				[
					'class' => $dateAndTimeClasses,
				],
				[
					'offset' => $row->cul_timestamp + 3600,
					'highlight' => $row->cul_timestamp,
				]
			);
		} elseif ( $dateAndTimeClasses ) {
			return Html::element(
				'span',
				[ 'class' => $dateAndTimeClasses ],
				$dateAndTime
			);
		} else {
			return htmlspecialchars( $dateAndTime );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
		$user = Linker::userLink( $row->cul_user, $row->actor_name ) .
			$this->msg( 'word-separator' )->escaped()
			. Html::rawElement( 'span', [ 'classes' => 'mw-usertoollinks' ],
				$this->msg( 'parentheses' )->params( $this->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'CheckUserLog' ),
					$this->msg( 'checkuser-log-checks-by' )->text(),
					[],
					[
						'cuInitiator' => $row->actor_name,
					]
				) )->text()
			);

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
			$this->generateTimestampLink(
				$lang->userTimeAndDate(
					wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser
				),
				$row
			),
			$this->generateTimestampLink(
				$lang->userDate( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser ),
				$row
			),
			$this->generateTimestampLink(
				$lang->userTime( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser ),
				$row
			)
		)->text();
		$rowContent .= $this->commentFormatter->formatBlock( $row->cul_reason );

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
		}

		return '';
	}

	/**
	 * @return string
	 */
	public function getEndBody() {
		if ( $this->getNumRows() ) {
			return '</ul>';
		}

		return '';
	}

	/**
	 * @return string
	 */
	public function getEmptyBody() {
		return '<p>' . $this->msg( 'checkuser-empty' )->escaped() . '</p>';
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return [
			'tables' => [ 'cu_log', 'actor' ],
			'fields' => $this->selectFields(),
			'conds' => array_merge( $this->searchConds, [ 'actor_user = cul_user' ] )
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return 'cul_timestamp';
	}

	/**
	 * @inheritDoc
	 */
	public function selectFields() {
		return [
			'cul_id', 'cul_timestamp', 'cul_user', 'cul_reason', 'cul_type',
			'cul_target_id', 'cul_target_text', 'actor_name'
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
			$lb->add( NS_USER, $row->actor_name );

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
		}
		return null;
	}
}
