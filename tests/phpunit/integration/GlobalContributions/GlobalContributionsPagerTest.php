<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
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

	private function getWrappedPager( $userName, $pageTitle, $pageNamespace = 0 ) {
		$pager = TestingAccessWrapper::newFromObject( $this->getPager( $userName ) );
		$pager->currentPage = Title::makeTitle( $pageNamespace, $pageTitle );
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

	/**
	 * @dataProvider provideFormatArticleLink
	 */
	public function testFormatArticleLink( $namespace, $expectShowNamespace ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'page_namespace' => $namespace,
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title, $row->page_namespace );

		$formatted = $pager->formatArticleLink( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( $row->page_title, $formatted );

		if ( $expectShowNamespace ) {
			$this->assertStringContainsString(
				NamespaceInfo::CANONICAL_NAMES[$namespace],
				$formatted
			);
		} else {
			$this->assertStringContainsString(
				'>' . $row->page_title,
				$formatted
			);
		}
	}

	public function provideFormatArticleLink() {
		return [
			'Known external namespace is shown' => [
				'namespace' => NS_TALK,
				'expectShowNamespace' => true
			],
			'Unknown external namespace is not shown' => [
				'namespace' => 1000,
				'expectShowNamespace' => false
			],
		];
	}

	/**
	 * @dataProvider provideFormatDiffHistLinks
	 */
	public function testFormatDiffHistLinks( $isNewPage, $isHidden, $expectDiffLink ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_parent_id' => $isNewPage ? '0' : '1',
			'rev_id' => '2',
			'rev_deleted' => $isHidden ? '1' : '0',
			'rev_page' => '100',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDiffHistLinks( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( 'diff', $formatted );
		$this->assertStringContainsString( 'action=history', $formatted );
		$this->assertStringContainsString( 'curid=100', $formatted );
		if ( $expectDiffLink ) {
			$this->assertStringContainsString( 'oldid=2', $formatted );
		} else {
			$this->assertStringNotContainsString( 'oldid=2', $formatted );
		}
	}

	public function provideFormatDiffHistLinks() {
		return [
			'No diff link for a new page' => [ true, false, false ],
			'No diff link for not a new page, hidden from user' => [ false, true, false ],
			'Diff link for not a new page, visible to user' => [ false, false, true ],
		];
	}

	/**
	 * @dataProvider provideFormatDateLink
	 */
	public function testFormatDateLink( $isHidden ) {
		$this->setUserLang( 'qqx' );
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_timestamp' => '20240101000000',
			'rev_deleted' => $isHidden ? '1' : '0'
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDateLink( $row );
		$this->assertStringContainsString( '2024', $formatted );
		if ( $isHidden ) {
			$this->assertStringNotContainsString( 'external', $formatted );
		} else {
			$this->assertStringContainsString( 'external', $formatted );
		}
	}

	public function provideFormatDateLink() {
		return [ [ true ], [ false ] ];
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

		$services = $this->getServiceContainer();
		$pager = $this->getMockBuilder( GlobalContributionsPager::class )
			->onlyMethods( [ 'getForeignUrl' ] )
			->setConstructorArgs( [
				$services->getLinkRenderer(),
				$services->getLinkBatchFactory(),
				$services->getHookContainer(),
				$services->getRevisionStore(),
				$services->getNamespaceInfo(),
				$services->getCommentFormatter(),
				$services->getUserFactory(),
				$services->getTempUserConfig(),
				$services->get( 'CheckUserLookupUtils' ),
				$services->get( 'CheckUserApiRequestAggregator' ),
				$services->getDBLoadBalancerFactory(),
				$services->getJobQueueGroup(),
				RequestContext::getMain(),
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, '127.0.0.1' )
			] )
			->getMock();
		$pager->expects( $this->any() )
			->method( 'getForeignUrl' )
			->willReturnArgument( 1 );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->currentPage = Title::makeTitle( 0, $row->page_title );

		$formatted = $pager->formatUserLink( $row );
		if ( $userIsDeleted ) {
			$this->assertStringContainsString( 'empty-username', $formatted );
			$this->assertStringNotContainsString( '~2024-123', $formatted );
		} else {
			$this->assertStringContainsString( 'Special:Contributions/~2024-123', $formatted );
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

	/**
	 * @dataProvider provideExternalWikiPermissions
	 */
	public function testExternalWikiPermissions( $permissions, $expectedCount ) {
		$this->setUserLang( 'qqx' );
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder->method( $this->logicalOr( 'select', 'from', 'distinct', 'where', 'join', 'caller' ) )
			->willReturnSelf();
		$queryBuilder->method( 'fetchFieldValues' )
			->willReturn( [ $localWiki, $externalWiki ] );

		$database = $this->createMock( IReadableDatabase::class );
		$database->method( 'newSelectQueryBuilder' )
			->willreturn( $queryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturn( $database );

		// Mock making the permission API call
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( [
				$externalWiki => [
					'query' => [
						'pages' => [
							[
								'actions' => $permissions,
							],
						],
					],
				],
			] );

		$services = $this->getServiceContainer();
		$pager = new GlobalContributionsPager(
			$services->getLinkRenderer(),
			$services->getLinkBatchFactory(),
			$services->getHookContainer(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getTempUserConfig(),
			$services->get( 'CheckUserLookupUtils' ),
			$apiRequestAggregator,
			$dbProvider,
			$services->getJobQueueGroup(),
			RequestContext::getMain(),
			[ 'revisionsOnly' => true ],
			new UserIdentityValue( 0, '127.0.0.1' )
		);
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertCount( $expectedCount, $wikis );
		$this->assertArrayHasKey( $externalWiki, $pager->permissions );
		$this->assertSame( array_keys( $permissions ), array_keys( $pager->permissions[$externalWiki] ) );

		if ( $expectedCount < 2 ) {
			$this->assertStringContainsString(
				'checkuser-global-contributions-ip-reveal-permissions-error',
				$pager->getOutput()->getHtml()
			);
		}
	}

	public function provideExternalWikiPermissions() {
		return [
			'Can always reveal IP at external wiki' => [
				'actions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [],
				],
				2,
			],
			'Can reveal IP at external wiki with preference' => [
				'actions' => [
					'checkuser-temporary-account' => [],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				2,
			],
			'Can not reveal IP at external wiki' => [
				'actions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				1,
			]
		];
	}
}
