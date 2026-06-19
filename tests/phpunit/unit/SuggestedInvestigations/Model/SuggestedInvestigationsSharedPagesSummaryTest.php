<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Model;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsSharedPagesSummary;
use MediaWiki\Page\PageIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsSharedPagesSummary
 */
class SuggestedInvestigationsSharedPagesSummaryTest extends MediaWikiUnitTestCase {

	public function testEmptySummaryHasNoMessage(): void {
		$summary = new SuggestedInvestigationsSharedPagesSummary( 0, [] );

		$this->assertSame( 0, $summary->getEditCount() );
		$this->assertSame( 0, $summary->getPageCount() );
		$this->assertNull( $summary->getMessage() );
	}

	public function testSummaryWithSharedPages(): void {
		$pages = [
			PageIdentityValue::localIdentity( 42, 0, 'Foo' ),
			PageIdentityValue::localIdentity( 0, 1, 'Talk_page' ),
		];
		$summary = new SuggestedInvestigationsSharedPagesSummary( 10, $pages );

		$this->assertSame( 10, $summary->getEditCount() );
		$this->assertSame( 2, $summary->getPageCount() );

		$message = $summary->getMessage();
		$this->assertInstanceOf( MessageValue::class, $message );
		$this->assertSame(
			'checkuser-suggestedinvestigations-shared-pages-summary',
			$message->getKey()
		);
		$params = $message->getParams();
		$this->assertCount( 2, $params );
		$this->assertSame( 10, $params[0]->getValue() );
		$this->assertSame( 2, $params[1]->getValue() );
	}
}
