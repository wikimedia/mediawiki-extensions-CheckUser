<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use Html;
use IContextSource;
use MediaWiki\CheckUser\Hook\CheckUserFormatRowHook;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use ParserOutput;
use Psr\Log\LoggerInterface;
use ReverseChronologicalPager;
use Wikimedia\Rdbms\FakeResultWrapper;

class TimelinePager extends ReverseChronologicalPager {
	private CheckUserFormatRowHook $formatRowHookRunner;
	private TimelineService $timelineService;
	private TimelineRowFormatter $timelineRowFormatter;
	private TokenQueryManager $tokenQueryManager;

	/** @var string */
	private $start;

	/** @var string|null */
	private $lastDateHeader;

	/**
	 * Targets whose results should not be included in the investigation.
	 * Targets in this list may or may not also be in the $targets list.
	 * Either way, no activity related to these targets will appear in the
	 * results.
	 *
	 * @var string[]
	 */
	private $excludeTargets;

	/**
	 * Targets that have been added to the investigation but that are not
	 * present in $excludeTargets. These are the targets that will actually
	 * be investigated.
	 *
	 * @var string[]
	 */
	private $filteredTargets;

	private LoggerInterface $logger;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param CheckUserFormatRowHook $formatRowHookRunner
	 * @param TokenQueryManager $tokenQueryManager
	 * @param DurationManager $durationManager
	 * @param TimelineService $timelineService
	 * @param TimelineRowFormatter $timelineRowFormatter
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		CheckUserFormatRowHook $formatRowHookRunner,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		TimelineService $timelineService,
		TimelineRowFormatter $timelineRowFormatter,
		LoggerInterface $logger
	) {
		parent::__construct( $context, $linkRenderer );
		$this->formatRowHookRunner = $formatRowHookRunner;
		$this->timelineService = $timelineService;
		$this->timelineRowFormatter = $timelineRowFormatter;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->logger = $logger;

		$tokenData = $tokenQueryManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $tokenData['offset'] ?? '';
		$this->excludeTargets = $tokenData['exclude-targets'] ?? [];
		$this->filteredTargets = array_diff(
			$tokenData['targets'] ?? [],
			$this->excludeTargets
		);
		$this->start = $durationManager->getTimestampFromRequest( $context->getRequest() );
	}

	/**
	 * @inheritDoc
	 *
	 * Handle special case where all targets are filtered.
	 */
	public function reallyDoQuery( $offset, $limit, $order ) {
		// If there are no targets, there is no need to run the query and an empty result can be used.
		if ( $this->filteredTargets === [] ) {
			return new FakeResultWrapper( [] );
		}
		return parent::reallyDoQuery( $offset, $limit, $order );
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return $this->timelineService->getQueryInfo(
			$this->filteredTargets,
			$this->excludeTargets,
			$this->start
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [ [ 'cuc_timestamp', 'cuc_id' ] ];
	}

	/**
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
		$line = '';
		$dateHeader = $this->getLanguage()->userDate( wfTimestamp( TS_MW, $row->cuc_timestamp ), $this->getUser() );
		if ( $this->lastDateHeader === null ) {
			$this->lastDateHeader = $dateHeader;
			$line .= Html::element( 'h4', [], $dateHeader );
			$line .= Html::openElement( 'ul' );
		} elseif ( $this->lastDateHeader !== $dateHeader ) {
			$this->lastDateHeader = $dateHeader;

			// Start a new list with a new date header
			$line .= Html::closeElement( 'ul' );
			$line .= Html::element( 'h4', [], $dateHeader );
			$line .= Html::openElement( 'ul' );
		}

		$rowItems = $this->timelineRowFormatter->getFormattedRowItems( $row );

		$this->formatRowHookRunner->onCheckUserFormatRow( $this->getContext(), $row, $rowItems );

		if ( !is_array( $rowItems ) || !isset( $rowItems['links'] ) || !isset( $rowItems['info'] ) ) {
			$this->logger->warning(
				__METHOD__ . ': Expected array with keys \'links\' and \'info\''
					. ' from CheckUserFormatRow $rowItems param'
			);
			return '';
		}

		$formattedLinks = implode( ' ', array_filter(
			$rowItems['links'],
			static function ( $item ) {
				return $item !== '';
			} )
		);

		$formatted = implode( ' . . ', array_filter(
			array_merge(
				[ $formattedLinks ],
				$rowItems['info']
			), static function ( $item ) {
				return $item !== '';
			} )
		);

		$line .= Html::rawElement(
			'li',
			[],
			$formatted
		);

		return $line;
	}

	/**
	 * @inheritDoc
	 */
	public function getEmptyBody() {
		return Html::rawElement( 'p', [], $this->msg( 'checkuser-investigate-timeline-empty' )->text() );
	}

	/**
	 * @inheritDoc
	 *
	 * Conceal the offset which may reveal private data.
	 */
	public function getPagingQueries() {
		return $this->tokenQueryManager->getPagingQueries(
			$this->getRequest(), parent::getPagingQueries()
		);
	}

	/**
	 * Get the formatted result list, with navigation bars.
	 *
	 * @return ParserOutput
	 */
	public function getFullOutput(): ParserOutput {
		return new ParserOutput(
			$this->getNavigationBar() . $this->getBody() . $this->getNavigationBar()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getEndBody() {
		return $this->getNumRows() ? Html::closeElement( 'ul' ) : '';
	}
}
