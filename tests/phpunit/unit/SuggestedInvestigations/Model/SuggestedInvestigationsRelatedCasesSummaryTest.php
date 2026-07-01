<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Model;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsRelatedCasesSummary;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsRelatedCasesSummary
 */
class SuggestedInvestigationsRelatedCasesSummaryTest extends MediaWikiUnitTestCase {

	public function testEmptySummaryHasNoMessage(): void {
		$summary = new SuggestedInvestigationsRelatedCasesSummary( [] );

		$this->assertSame( [], $summary->getRelatedCaseIds() );
		$this->assertNull( $summary->getMessage() );
	}

	public function testSummaryWithRelatedCases(): void {
		$relatedCaseIds = [ 5, 9, 12 ];
		$summary = new SuggestedInvestigationsRelatedCasesSummary( $relatedCaseIds );

		$this->assertSame( $relatedCaseIds, $summary->getRelatedCaseIds() );

		$message = $summary->getMessage();
		$this->assertInstanceOf( MessageValue::class, $message );
		$this->assertSame(
			'checkuser-suggestedinvestigations-related-cases-summary',
			$message->getKey()
		);
		$params = $message->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 3, $params[0]->getValue() );
	}
}
