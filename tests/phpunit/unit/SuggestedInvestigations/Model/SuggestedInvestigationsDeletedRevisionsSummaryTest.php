<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Model;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsDeletedRevisionsSummary;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsDeletedRevisionsSummary
 */
class SuggestedInvestigationsDeletedRevisionsSummaryTest extends MediaWikiUnitTestCase {

	public function testEmptySummaryHasNoMessage(): void {
		$summary = new SuggestedInvestigationsDeletedRevisionsSummary();

		$this->assertSame( 0, $summary->getTotalDeletedRevisionsCount() );
		$this->assertNull( $summary->getMessage() );
	}

	public function testSummary(): void {
		$summary = new SuggestedInvestigationsDeletedRevisionsSummary( 1 );
		$this->assertSame( 1, $summary->getTotalDeletedRevisionsCount() );

		$message = $summary->getMessage();
		$this->assertInstanceOf( MessageValue::class, $message );
		$this->assertSame(
			'checkuser-suggestedinvestigations-deleted-revisions-summary',
			$message->getKey()
		);
		$params = $message->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 1, $params[0]->getValue() );
	}
}
