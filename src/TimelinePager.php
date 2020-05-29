<?php

namespace MediaWiki\CheckUser;

use Html;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use ParserOutput;
use ReverseChronologicalPager;

class TimelinePager extends ReverseChronologicalPager {

	/** @var TimelineService */
	private $timelineService;

	/** @var TimelineRowFormatter */
	private $timelineRowFormatter;

	/** @var string|null */
	private $lastDateHeader;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var array */
	protected $tokenData;

	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		TokenQueryManager $tokenQueryManager,
		TimelineService $timelineService,
		TimelineRowFormatter $timelineRowFormatter
	) {
		parent::__construct( $context, $linkRenderer );
		$this->timelineService = $timelineService;
		$this->timelineRowFormatter = $timelineRowFormatter;
		$this->tokenQueryManager = $tokenQueryManager;

		$this->tokenData = $tokenQueryManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $this->tokenData['offset'] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return $this->timelineService->getQueryInfo( $this->tokenData['targets'] );
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
			$line .= Html::rawElement( 'h4', [], $dateHeader );
			$line .= Html::openElement( 'ul' );
		} elseif ( $this->lastDateHeader !== $dateHeader ) {
			$this->lastDateHeader = $dateHeader;

			// Start a new list with a new date header
			$line .= Html::closeElement( 'ul' );
			$line .= Html::rawElement( 'h4', [], $dateHeader );
			$line .= Html::openElement( 'ul' );
		}

		$line .= Html::rawElement(
			'li',
			[],
			$this->timelineRowFormatter->format( $row )
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
	 * Get the formatted result list, with naviation bars.
	 *
	 * @return ParserOutput
	 */
	public function getFullOutput() : ParserOutput {
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
