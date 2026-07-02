<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Model;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsRevisionRevertsSummary;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsRevisionRevertsSummary
 */
class SuggestedInvestigationsRevisionRevertsSummaryTest extends MediaWikiUnitTestCase {

	public function testEmptySummaryHasNoMessage(): void {
		$summary = new SuggestedInvestigationsRevisionRevertsSummary();

		$this->assertSame( 0, $summary->getRevertedRevisionsCount() );
		$this->assertSame( 0, $summary->getTotalRevisionsCount() );
		$this->assertNull( $summary->getMessage() );
	}

	public function testSummary(): void {
		$summary = new SuggestedInvestigationsRevisionRevertsSummary(
			1,
			2,
		);
		$this->assertSame( 1, $summary->getRevertedRevisionsCount() );
		$this->assertSame( 2, $summary->getTotalRevisionsCount() );

		$message = $summary->getMessage();
		$this->assertInstanceOf( MessageValue::class, $message );
		$this->assertSame(
			'checkuser-suggestedinvestigations-revision-reverts-summary',
			$message->getKey()
		);
		$params = $message->getParams();
		$this->assertCount( 2, $params );
		$this->assertSame( 1, $params[0]->getValue() );
		$this->assertSame( 2, $params[1]->getValue() );
	}
}
