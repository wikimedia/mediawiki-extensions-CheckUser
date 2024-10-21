<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager
 * @group CheckUser
 * @group Database
 */
class GlobalContributionsPagerTest extends MediaWikiIntegrationTestCase {
	private function getPager( $userName ) {
		return $this->getServiceContainer()->get( 'CheckUserGlobalContributionsPagerFactory' )
			->createPager(
				RequestContext::getMain(),
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, $userName )
			);
	}

	private function getWrappedPager( $userName, $pageTitle ) {
		$pager = TestingAccessWrapper::newFromObject( $this->getPager( $userName ) );
		$pager->currentPage = Title::makeTitle( 0, $pageTitle );
		return $pager;
	}

	private function getRow( $options = [] ) {
		return (object)( array_merge(
			[
				'rev_id' => '2',
				'rev_page' => '1',
				'rev_actor' => '1',
				'rev_user' => '1',
				'rev_user_text' => '~2024-123',
				'rev_timestamp' => '20240101000000',
				'rev_minor_edit' => '0',
				'rev_deleted' => '0',
				'rev_len' => '100',
				'rev_parent_id' => '1',
				'rev_sha1' => '',
				'rev_comment_text' => '',
				'rev_comment_data' => null,
				'rev_comment_cid' => '1',
				'page_latest' => '2',
				'page_is_new' => '0',
				'page_namespace' => '0',
				'page_title' => 'Test page',
				'cuc_timestamp' => '20240101000000',
				'ts_tags' => null,
			],
			$options
		) );
	}

	public function testPopulateAttributes() {
		$this->setUserLang( 'qqx' );
		$pager = $this->getPager( '127.0.0.1' );
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );

		// We can't call populateAttributes directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		$this->assertStringNotContainsString( 'data-mw-revid', $formatted );
	}

	public function testFormatArticleLink() {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatArticleLink( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( $row->page_title, $formatted );
	}

	/**
	 * @dataProvider provideFormatDiffHistLinks
	 */
	public function testFormatDiffHistLinks( $isNewPage ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_parent_id' => $isNewPage ? '0' : '1',
			'rev_id' => '2',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDiffHistLinks( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( 'diff', $formatted );
		$this->assertStringContainsString( 'action=history', $formatted );
		if ( $isNewPage ) {
			$this->assertStringNotContainsString( 'oldid=2', $formatted );
		} else {
			$this->assertStringContainsString( 'oldid=2', $formatted );
		}
	}

	public function provideFormatDiffHistLinks() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatDateLink() {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_timestamp' => '20240101000000'
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDateLink( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( '2024', $formatted );
	}

	/**
	 * @dataProvider provideFormatTopMarkText
	 */
	public function testFormatTopMarkText( $revisionIsLatest ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_id' => '2',
			'page_latest' => $revisionIsLatest ? '2' : '3',
		] );
		$pager = $this->getPager( '127.0.0.1' );

		// We can't call formatTopMarkText directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		if ( $revisionIsLatest ) {
			$this->assertStringContainsString( 'uctop', $formatted );
		} else {
			$this->assertStringNotContainsString( 'uctop', $formatted );
		}
	}

	public function provideFormatTopMarkText() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatComment() {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatComment( $row );
		$this->assertSame( '', $formatted );
	}

	/**
	 * @dataProvider provideFormatUserLink
	 */
	public function testFormatUserLink( $userIsDeleted ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_user_text' => '~2024-123',
			'rev_deleted' => $userIsDeleted ? '4' : '8'
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatUserLink( $row );
		if ( $userIsDeleted ) {
			$this->assertStringContainsString( 'empty-username', $formatted );
			$this->assertStringNotContainsString( '~2024-123', $formatted );
		} else {
			$this->assertStringContainsString( '~2024-123', $formatted );
			$this->assertStringNotContainsString( 'empty-username', $formatted );
		}
	}

	public function provideFormatUserLink() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider provideFormatFlags
	 */
	public function testFormatFlags( $hasFlags ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_minor_edit' => $hasFlags ? '1' : '0',
			'rev_parent_id' => $hasFlags ? '0' : '1',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$flags = $pager->formatFlags( $row );
		if ( $hasFlags ) {
			$this->assertCount( 2, $flags );
		} else {
			$this->assertCount( 0, $flags );
		}
	}

	public function provideFormatFlags() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatVisibilityLink() {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatVisibilityLink( $row );
		$this->assertSame( '', $formatted );
	}

	/**
	 * @dataProvider provideFormatTags
	 */
	public function testFormatTags( $hasTags ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'ts_tags' => $hasTags ? 'sometag' : null
		] );
		$pager = $this->getPager( '127.0.0.1' );

		// We can't call formatTags directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		if ( $hasTags ) {
			$this->assertStringContainsString( 'sometag', $formatted );
		} else {
			$this->assertStringNotContainsString( 'sometag', $formatted );
		}
	}

	public function provideFormatTags() {
		return [ [ true ], [ false ] ];
	}
}
