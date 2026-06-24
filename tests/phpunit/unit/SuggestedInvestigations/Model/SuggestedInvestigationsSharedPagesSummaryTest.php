<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Model;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsSharedPagesSummary;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsSharedPagesSummary
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCaseMetadata
 */
class SuggestedInvestigationsSharedPagesSummaryTest extends MediaWikiUnitTestCase {

	public function testEmptySummaryHasNoMessage(): void {
		$summary = new SuggestedInvestigationsSharedPagesSummary();

		$this->assertSame( [], $summary->getRevisionIds() );
		$this->assertSame( [], $summary->getSharedPages() );
		$this->assertNull( $summary->getMessage() );
		$this->assertSame( [], $summary->getCommonEditors() );
	}

	public function testSummaryWithSharedPages(): void {
		$pages = [
			PageIdentityValue::localIdentity( 42, 0, 'Foo' ),
			PageIdentityValue::localIdentity( 0, 1, 'Talk_page' ),
		];
		$revisionIds = range( 1, 10 );
		$alice = new UserIdentityValue( 1, 'Alice' );
		$bob = new UserIdentityValue( 2, 'Bob' );
		$summary = new SuggestedInvestigationsSharedPagesSummary(
			$revisionIds,
			$pages,
			'20260101000000',
			'20260101000300',
			[ $alice, $bob ]
		);

		$this->assertSame( $revisionIds, $summary->getRevisionIds() );
		$this->assertCount( 2, $summary->getSharedPages() );

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
		$this->assertSame( [ $alice, $bob ], $summary->getCommonEditors() );
	}

	public function testNoDisplayedMessageOverrideByDefault(): void {
		$summary = new SuggestedInvestigationsSharedPagesSummary();

		$this->assertFalse( $summary->isMessageOverridden() );
		$this->assertNull( $summary->getMessageOverride() );
	}

	public function testSetDisplayedMessageOverride(): void {
		$summary = new SuggestedInvestigationsSharedPagesSummary();
		$override = new MessageValue( 'some-override-message' );

		$summary->overrideMessage( $override );

		$this->assertTrue( $summary->isMessageOverridden() );
		$this->assertSame( $override, $summary->getMessageOverride() );
	}

	public function testSetDisplayedMessageOverrideToNullHides(): void {
		$summary = new SuggestedInvestigationsSharedPagesSummary();

		$summary->overrideMessage( null );

		// A null override is distinct from no override: it means "display nothing".
		$this->assertTrue( $summary->isMessageOverridden() );
		$this->assertNull( $summary->getMessageOverride() );
	}
}
