<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\UserLinkRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager
 * @group CheckUser
 * @group Database
 */
class GlobalContributionsPagerTest extends MediaWikiIntegrationTestCase {
	private const TEMP_USERNAME = '~2024-123';

	/**
	 * This mock is used to prevent dealing with how
	 * UserLinkRenderer handles caching and external users.
	 *
	 * @var (UserLinkRenderer&MockObject)
	 */
	private UserLinkRenderer $userLinkRenderer;

	/**
	 * @var (LinkRenderer&MockObject)
	 */
	private LinkRenderer $linkRenderer;

	public function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		$this->setUserLang( 'qqx' );

		$this->userLinkRenderer = $this->createMock( UserLinkRenderer::class );
		$this->linkRenderer = $this->createMock( LinkRenderer::class );
		$this->linkRenderer
			->method( 'makeExternalLink' )
			->willReturnCallback(
				static fn ( $url ) => sprintf(
					'https://external.wiki/%s',
					str_replace( ' ', '_', $url )
				)
			);
	}

	private function getPagerWithOverrides( $overrides ) {
		$services = $this->getServiceContainer();
		return new GlobalContributionsPager(
			$this->linkRenderer,
			$overrides['LinkBatchFactory'] ?? $services->getLinkBatchFactory(),
			$overrides['HookContainer'] ?? $services->getHookContainer(),
			$overrides['RevisionStore'] ?? $services->getRevisionStore(),
			$overrides['NamespaceInfo'] ?? $services->getNamespaceInfo(),
			$overrides['CommentFormatter'] ?? $services->getCommentFormatter(),
			$overrides['UserFactory'] ?? $services->getUserFactory(),
			$overrides['TempUserConfig'] ?? $services->getTempUserConfig(),
			$overrides['CheckUserLookupUtils'] ?? $services->get( 'CheckUserLookupUtils' ),
			$overrides['CentralIdLookup'] ?? $services->get( 'CentralIdLookup' ),
			$overrides['RequestAggregator'] ?? $services->get( 'CheckUserApiRequestAggregator' ),
			$overrides['GlobalContributionsLookup'] ?? $services->get( 'CheckUserGlobalContributionsLookup' ),
			$overrides['PermissionManager'] ?? $services->getPermissionManager(),
			$overrides['PreferencesFactory'] ?? $services->getPreferencesFactory(),
			$overrides['LoadBalancerFactory'] ?? $services->getConnectionProvider(),
			$overrides['JobQueueGroup'] ?? $services->getJobQueueGroup(),
			$overrides['StatsFactory'] ?? $services->getStatsFactory(),
			$overrides['UserLinkRenderer'] ?? $this->userLinkRenderer,
			$overrides['MainWANObjectCache'] ?? $services->getMainWANObjectCache(),
			$overrides['Context'] ?? RequestContext::getMain(),
			$overrides['options'] ?? [ 'revisionsOnly' => true ],
			new UserIdentityValue( 0, $overrides['UserName'] ?? '127.0.0.1' )
		);
	}

	private function getPager( $userName ): GlobalContributionsPager {
		return $this->getServiceContainer()->get( 'CheckUserGlobalContributionsPagerFactory' )
			->createPager(
				RequestContext::getMain(),
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, $userName )
			);
	}

	private function getWrappedPager( string $userName, $pageTitle, $pageNamespace = 0 ) {
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
				'rev_user_text' => self::TEMP_USERNAME,
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
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );

		// formatRow() calls getTemplateParams(), which calls formatUserLink(),
		// which calls UserLinkRenderer's userLink(): We need to mock that to
		// prevent it from calling WikiMap static methods that check if the user
		// is external, since those calls can't be mocked and seeding the DB
		// with data that would make WikiMap behave the way we need may make
		// tests that also modify the sites table to fail.
		$this->userLinkRenderer
			->expects( $this->once() )
			->method( 'userLink' )
			->with(
				$this->isInstanceOf( UserIdentityValue::class ),
				RequestContext::getMain()
			)->willReturnCallback(
				function ( UserIdentityValue $user, IContextSource $context ) {
					$this->assertEquals(
						self::TEMP_USERNAME,
						$user->getName()
					);

					return '<a href="https://example.com/User:username">username</a>';
				}
			);

		// Get a pager that uses the mock in $this->userLinkRenderer. That's
		// needed to avoid the calls the regular UserLinkRenderer does to
		// WikiMap, since we can't mock static calls from a test.
		$pager = $this->getPagerWithOverrides( [
			'UserName' => '127.0.0.1',
		] );

		// We can't call populateAttributes directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		$this->assertStringNotContainsString( 'data-mw-revid', $formatted );
	}

	/**
	 * @dataProvider provideFormatArticleLink
	 */
	public function testFormatArticleLink( $namespace, $expectedPageLinkText ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'page_namespace' => $namespace,
			'page_title' => 'Test',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title, $row->page_namespace );

		$formatted = $pager->formatArticleLink( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( $row->page_title, $formatted );

		$this->assertStringContainsString(
			$expectedPageLinkText,
			$formatted
		);
	}

	public static function provideFormatArticleLink() {
		return [
			'Known external namespace is shown' => [
				'namespace' => NS_TALK,
				'expectedPageLinkText' => NamespaceInfo::CANONICAL_NAMES[NS_TALK] . ':Test',
			],
			'Unknown external namespace is not shown' => [
				'namespace' => 1000,
				'expectedPageLinkText' =>
					'(checkuser-global-contributions-page-when-no-namespace-translation-available: 1,000, Test)',
			],
		];
	}

	/**
	 * @dataProvider provideFormatDiffHistLinks
	 */
	public function testFormatDiffHistLinks( $isNewPage, $isHidden, $expectDiffLink ) {
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

	public static function provideFormatDiffHistLinks() {
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

	public static function provideFormatDateLink() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider provideFormatTopMarkText
	 */
	public function testFormatTopMarkText( $revisionIsLatest ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_id' => '2',
			'page_latest' => $revisionIsLatest ? '2' : '3',
		] );

		// formatRow() calls getTemplateParams(), which calls formatUserLink(),
		// which calls UserLinkRenderer's userLink(): We need to mock that to
		// prevent it from calling WikiMap static methods that check if the user
		// is external, since those calls can't be mocked and seeding the DB
		// with data that would make WikiMap behave the way we need may make
		// tests that also modify the sites table to fail.
		$this->userLinkRenderer
			->expects( $this->once() )
			->method( 'userLink' )
			->with(
				$this->isInstanceOf( UserIdentityValue::class ),
				RequestContext::getMain()
			)->willReturn(
				'<a href="http://example.com/User:username">username</a>'
			);

		// Get a pager that uses the mock in $this->userLinkRenderer. That's
		// needed to avoid the calls the regular UserLinkRenderer does to
		// WikiMap, since we can't mock static calls from a test.
		$pager = $this->getPagerWithOverrides( [
			'UserName' => '127.0.0.1',
		] );

		// We can't call formatTopMarkText directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		if ( $revisionIsLatest ) {
			$this->assertStringContainsString( 'uctop', $formatted );
		} else {
			$this->assertStringNotContainsString( 'uctop', $formatted );
		}
	}

	public static function provideFormatTopMarkText() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatComment() {
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatComment( $row );
		$this->assertSame(
			sprintf(
				'<span class="comment mw-comment-none">(%s)</span>',
				'checkuser-global-contributions-no-summary-available'
			),
			$formatted
		);
	}

	/**
	 * @dataProvider provideFormatUserLink
	 */
	public function testFormatUserLink(
		array $expectedStrings,
		array $unexpectedStrings,
		string $username,
		?string $sourcewiki,
		bool $hasRevisionRecord,
		bool $isDeleted
	): void {
		// The pager relies on UserLinkRenderer to provide for local and external users
		// and on LinkRenderer for talk and contribution links, so this test only checks
		// that the result from those methods is included in the output.
		$row = $this->getRow( [
			'sourcewiki' => $sourcewiki ?? WikiMap::getCurrentWikiId(),
			'rev_user' => 123,
			'rev_user_text' => $username,
			'rev_deleted' => $isDeleted ? '4' : '8'
		] );
		if ( $hasRevisionRecord ) {
			$mockRevRecord = $this->createMock( RevisionRecord::class );
			$mockRevRecord->method( 'getUser' )
				->willReturn( new UserIdentityValue( 123, $username ) );
		} else {
			$mockRevRecord = null;
		}

		$context = RequestContext::getMain();

		$this->userLinkRenderer
			->method( 'userLink' )
			->willReturnCallback(
				function (
					UserIdentity $user,
					IContextSource $linkContext
				) use ( $username, $context, $row ) {
					$this->assertEquals( $row->rev_user, $user->getId( $user->getWikiId() ) );
					$this->assertEquals( $row->rev_user_text, $user->getName() );

					$actualSourceWiki = $user->getWikiId();
					if ( $actualSourceWiki === WikiAwareEntity::LOCAL ) {
						$actualSourceWiki = WikiMap::getCurrentWikiId();
					}
					$this->assertEquals( $row->sourcewiki, $actualSourceWiki );

					$this->assertSame( $context, $linkContext );

					if ( $user->getWikiId() === WikiAwareEntity::LOCAL ) {
						$domain = 'https://local.wiki';
					} else {
						$domain = 'https://external.wiki';
					}
					return Html::element( 'a', [ 'href' => $domain . '/User:' . $username ], $username );
				}
			);

		$services = $this->getServiceContainer();
		$pager = $this->getMockBuilder( GlobalContributionsPager::class )
			->onlyMethods( [ 'getForeignUrl' ] )
			->setConstructorArgs( [
				$this->linkRenderer,
				$services->getLinkBatchFactory(),
				$services->getHookContainer(),
				$services->getRevisionStore(),
				$services->getNamespaceInfo(),
				$services->getCommentFormatter(),
				$services->getUserFactory(),
				$services->getTempUserConfig(),
				$services->get( 'CheckUserLookupUtils' ),
				$services->get( 'CentralIdLookup' ),
				$services->get( 'CheckUserApiRequestAggregator' ),
				$services->get( 'CheckUserGlobalContributionsLookup' ),
				$services->getPermissionManager(),
				$services->getPreferencesFactory(),
				$services->getDBLoadBalancerFactory(),
				$services->getJobQueueGroup(),
				$services->getStatsFactory(),
				$this->userLinkRenderer,
				$services->getMainWANObjectCache(),
				$context,
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, '127.0.0.1' )
			] )
			->getMock();
		$pager->expects( $this->any() )
			->method( 'getForeignUrl' )
			->willReturnArgument( 1 );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->currentPage = Title::makeTitle( 0, $row->page_title );
		$pager->currentRevRecord = $mockRevRecord;

		$formatted = $pager->formatUserLink( $row );

		foreach ( $expectedStrings as $value ) {
			$this->assertStringContainsString( $value, $formatted );
		}

		foreach ( $unexpectedStrings as $value ) {
			$this->assertStringNotContainsString( $value, $formatted );
		}
	}

	public static function provideFormatUserLink() {
		return [
			'Registered account, external wiki, hidden' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'RegisteredUser1',
					'https://local.wiki',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'username' => 'RegisteredUser1',
				'sourcewiki' => 'otherwiki',
				'hasRevisionRecord' => false,
				'isDeleted' => true,
			],
			'Registered account, external wiki, visible' => [
				'expectedStrings' => [
					'User_talk:RegisteredUser1',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'unexpectedStrings' => [ 'https://local.wiki' ],
				'username' => 'RegisteredUser1',
				'sourcewiki' => 'otherwiki',
				'hasRevisionRecord' => false,
				'isDeleted' => false,
			],
			'Registered account, local wiki, hidden' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'RegisteredUser1',
					'https://local.wiki',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'username' => 'RegisteredUser1',
				// null is replaced with the local wiki ID
				'sourcewiki' => null,
				'hasRevisionRecord' => true,
				'isDeleted' => true,
			],
			'Registered account, local wiki, visible' => [
				'expectedStrings' => [
					'User_talk:RegisteredUser1',
					'Special:Contributions/RegisteredUser1',
					'https://local.wiki',
				],
				'unexpectedStrings' => [ 'https://external.wiki' ],
				'username' => 'RegisteredUser1',
				'sourcewiki' => null,
				'hasRevisionRecord' => true,
				'isDeleted' => false,
			],
			'Registered account, local wiki, rev record is not found' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'RegisteredUser1',
					'https://local.wiki',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'username' => 'RegisteredUser1',
				'sourcewiki' => null,
				'hasRevisionRecord' => false,
				'isDeleted' => false,
			],
		];
	}

	/**
	 * @dataProvider provideFormatFlags
	 */
	public function testFormatFlags( $hasFlags ) {
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

	public static function provideFormatFlags() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatVisibilityLink() {
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatVisibilityLink( $row );
		$this->assertSame( '', $formatted );
	}

	/**
	 * @dataProvider provideFormatTags
	 */
	public function testFormatTags( $hasTags ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'ts_tags' => $hasTags ? 'sometag' : null
		] );

		// formatRow() calls getTemplateParams(), which calls formatUserLink(),
		// which calls UserLinkRenderer's userLink(): We need to mock that to
		// prevent it from calling WikiMap static methods that check if the user
		// is external, since those calls can't be mocked and seeding the DB
		// with data that would make WikiMap behave the way we need may make
		// tests that also modify the sites table to fail.
		$this->userLinkRenderer
			->expects( $this->once() )
			->method( 'userLink' )
			->with(
				$this->isInstanceOf( UserIdentityValue::class ),
				RequestContext::getMain()
			)->willReturn(
				'<a href="http://example.com/User:username">username</a>'
			);

		// Get a pager that uses the mock in $this->userLinkRenderer. That's
		// needed to avoid the calls the regular UserLinkRenderer does to
		// WikiMap, since we can't mock static calls from a test.
		$pager = $this->getPagerWithOverrides( [
			'UserName' => '127.0.0.1',
		] );

		// We can't call formatTags directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $pager->formatRow( $row );
		if ( $hasTags ) {
			$this->assertStringContainsString( 'sometag', $formatted );
		} else {
			$this->assertStringNotContainsString( 'sometag', $formatted );
		}
	}

	public static function provideFormatTags() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider provideExternalWikiPermissions
	 */
	public function testExternalWikiPermissions( $permissions, $expectedCount ) {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ $localWiki, $externalWiki ] );

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

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'RequestAggregator' => $apiRequestAggregator,
			'GlobalContributionsLookup' => $globalContributionsLookup
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertCount( $expectedCount, $wikis );
		$this->assertArrayHasKey( $externalWiki, $pager->permissions );
		$this->assertSame( array_keys( $permissions ), array_keys( $pager->permissions[$externalWiki] ) );
	}

	public static function provideExternalWikiPermissions() {
		return [
			'Can always reveal IP at external wiki' => [
				'actions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [],
				],
				1,
			],
			'Can reveal IP at external wiki with preference' => [
				'actions' => [
					'checkuser-temporary-account' => [],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				0,
			],
			'Can not reveal IP at external wiki' => [
				'actions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				0,
			]
		];
	}

	public function testGetExternalWikiPermissionsNoCentralId() {
		// Mock the user has no central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 0 );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->getExternalWikiPermissions( [] );

		$this->assertSame( [], $pager->permissions );
	}

	public function testExternalWikiPermissionsCache() {
		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

		// Mock making the permission API call
		$wikiIds = [ 'otherwiki' ];
		$permsByWiki = array_fill_keys(
			$wikiIds,
			[
				'query' => [
					'pages' => [
						[
							'actions' => [
								'checkuser-temporary-account' => [ 'error' ],
								'checkuser-temporary-account-no-preference' => [],
							]
						],
					],
				],
			],
		);
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator
			->expects( $this->exactly( 2 ) )
			->method( 'execute' )
			->willReturn( $permsByWiki );

		$services = $this->getServiceContainer();
		$pager = $this->getMockBuilder( GlobalContributionsPager::class )
			->onlyMethods( [ 'getExternalWikiPermissions' ] )
			->setConstructorArgs( [
				$this->linkRenderer,
				$services->getLinkBatchFactory(),
				$services->getHookContainer(),
				$services->getRevisionStore(),
				$services->getNamespaceInfo(),
				$services->getCommentFormatter(),
				$services->getUserFactory(),
				$services->getTempUserConfig(),
				$services->get( 'CheckUserLookupUtils' ),
				$centralIdLookup,
				$apiRequestAggregator,
				$services->get( 'CheckUserGlobalContributionsLookup' ),
				$services->getPermissionManager(),
				$services->getPreferencesFactory(),
				$services->getDBLoadBalancerFactory(),
				$services->getJobQueueGroup(),
				$services->getStatsFactory(),
				$this->userLinkRenderer,
				$services->getMainWANObjectCache(),
				RequestContext::getMain(),
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, '127.0.0.1' )
			] )
			->getMock();
		$pager = TestingAccessWrapper::newFromObject( $pager );

		// Set the value in the cache
		$pager->getExternalWikiPermissions( $wikiIds );
		$this->assertSame( [
			'otherwiki' => [
				'checkuser-temporary-account' => [ 'error' ],
				'checkuser-temporary-account-no-preference' => [],
			]
		], $pager->permissions );

		// Re-run the function, expecting that the API aggregator will not execute again
		$pager->getExternalWikiPermissions( $wikiIds );

		// Run the function with a different set of active wikis, expecting a cache miss
		$pager->getExternalWikiPermissions( [ 'otherwiki2' ] );
	}

	public function testExternalWikiPermissionsNotCheckedForUser() {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ $localWiki, $externalWiki ] );

		// Ensure the permission API call is not made
		$apiRequestAggregator = $this->createNoOpMock( CheckUserApiRequestAggregator::class );

		// Mock the central user exists
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromName' )
			->willReturn( 45678 );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'RequestAggregator' => $apiRequestAggregator,
			'GlobalContributionsLookup' => $globalContributionsLookup,
			'UserName' => 'SomeUser',
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertCount( 2, $wikis );
		$this->assertSame( [], $pager->permissions );
	}

	/**
	 * @dataProvider provideQueryData
	 *
	 * @param IResultWrapper[] $resultsByWiki Map of result sets keyed by wiki ID
	 * @param string[] $paginationParams The pagination parameters to set on the pager
	 * @param array $expectedParentSizeLookups The expected parent revision IDs to be queried for each wiki
	 * @param int $expectedCount The expected number of rows in the result set
	 * @param array|false $expectedPrevQuery The expected query parameters for the 'prev' page,
	 * or `false` if there is no previous page
	 * @param array|false $expectedNextQuery The expected query parameters for the 'next' page,
	 * or `false` if there is no next page
	 * @param int[] $expectedDiffSizes The expected byte sizes of the shown diffs
	 */
	public function testQuery(
		array $resultsByWiki,
		array $paginationParams,
		array $expectedParentSizeLookups,
		int $expectedCount,
		$expectedPrevQuery,
		$expectedNextQuery,
		array $expectedDiffSizes
	): void {
		$wikiIds = array_keys( $resultsByWiki );

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( $wikiIds );

		$parentSizeMap = [];
		foreach ( $expectedParentSizeLookups as $wikiId => $parentRevIds ) {
			$parentSizes = array_fill_keys( $parentRevIds, 5 );
			$parentSizeMap[] = [ $wikiId, $parentRevIds, $parentSizes ];
		}

		$globalContributionsLookup->method( 'getRevisionSizes' )
			->willReturnMap( $parentSizeMap );

		$checkUserDb = $this->createMock( IReadableDatabase::class );
		$dbMap = [
			[ CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN, null, $checkUserDb ],
		];

		foreach ( $resultsByWiki as $wikiId => $result ) {
			$localQueryBuilder = $this->createMock( SelectQueryBuilder::class );
			$localQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchResultSet' ) ) )
				->willReturnSelf();
			$localQueryBuilder->method( 'fetchResultSet' )
				->willReturn( $result );

			$localDb = $this->createMock( IReadableDatabase::class );
			$localDb->method( 'newSelectQueryBuilder' )
				->willReturn( $localQueryBuilder );

			$dbMap[] = [ $wikiId, null, $localDb ];
		}

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturnMap( $dbMap );

		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

		// Mock making the permission API call
		$permsByWiki = array_fill_keys(
			$wikiIds,
			[
				'query' => [
					'pages' => [
						[
							'actions' => [
								'checkuser-temporary-account' => [ 'error' ],
								'checkuser-temporary-account-no-preference' => [],
							]
						],
					],
				],
			],
		);
		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( $permsByWiki );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->expects( $this->never() )
			->method( 'run' );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'HookContainer' => $hookContainer,
			'RequestAggregator' => $apiRequestAggregator,
			'LoadBalancerFactory' => $dbProvider,
			'GlobalContributionsLookup' => $globalContributionsLookup,
		] );
		$pager->mIsBackwards = ( $paginationParams['dir'] ?? '' ) === 'prev';
		$pager->setLimit( $paginationParams['limit'] );
		$pager->setOffset( $paginationParams['offset'] ?? '' );

		$pager->doQuery();

		$pagingQueries = $pager->getPagingQueries();
		$result = $pager->getResult();
		$body = $pager->getBody();

		preg_match_all( '/\(rc-change-size: (\d+)\)/', $body, $matches, PREG_SET_ORDER );
		$diffSizes = array_map( static fn ( array $match ) => (int)$match[1], $matches );

		$this->assertSame( $expectedCount, $result->numRows(), 'Unexpected result row count' );
		$this->assertSame( $expectedPrevQuery, $pagingQueries['prev'], 'Invalid prev pagination link' );
		$this->assertSame( $expectedNextQuery, $pagingQueries['next'], 'Invalid next pagination link' );
		$this->assertSame( $expectedDiffSizes, $diffSizes, 'Mismatched byte counts in diff links' );
		$this->assertApiLookupErrorCount( 0 );
	}

	public static function provideQueryData(): iterable {
		$testResults = [
			'testwiki' => self::makeMockResult( [
				'20250110000000',
				'20250107000000',
				'20250108000000',
			] ),
			'otherwiki' => self::makeMockResult( [
				'20250109000000',
				'20250108000000',
			] )
		];

		yield '5 rows, limit=4, first page' => [
			$testResults,
			[ 'limit' => 4 ],
			[ 'testwiki' => [ 1 ], 'otherwiki' => [ 1 ] ],
			// 4 rows shown + 1 row for the next page link
			5,
			false,
			[ 'offset' => '20250108000000|-1|2', 'limit' => 4 ],
			[ 0, 0, 0, 95 ],
		];

		yield '5 rows, limit=4, second page' => [
			$testResults,
			[ 'offset' => '20250108000000|-1|2', 'limit' => 4 ],
			[ 'testwiki' => [ 1 ], 'otherwiki' => [ 1 ] ],
			1,
			[ 'dir' => 'prev', 'offset' => '20250107000000|0|2', 'limit' => 4 ],
			false,
			[ 95 ],
		];

		yield '5 rows, limit=4, backwards from second page' => [
			$testResults,
			[ 'dir' => 'prev', 'offset' => '20250107000000|0|2', 'limit' => 4 ],
			[ 'testwiki' => [ 2 ], 'otherwiki' => [ 1 ] ],
			4,
			false,
			[ 'offset' => '20250108000000|-1|2', 'limit' => 4 ],
			[ 0, 0, 95, 95 ],
		];

		$resultsWithIdenticalTimestamps = [
			'testwiki' => self::makeMockResult( [
				'20250108000000',
				'20250108000000',
			] ),
			'otherwiki' => self::makeMockResult( [
				'20250108000000',
			] )
		];

		yield '3 rows, identical timestamps, limit=2, first page' => [
			$resultsWithIdenticalTimestamps,
			[ 'limit' => 2 ],
			[ 'testwiki' => [ 1 ], 'otherwiki' => [ 1 ] ],
			// 2 rows shown + 1 row for the next page link
			3,
			false,
			[ 'offset' => '20250108000000|0|2', 'limit' => 2 ],
			[ 0, 95 ],
		];

		yield '3 rows, identical timestamps, limit=2, second page' => [
			$resultsWithIdenticalTimestamps,
			[ 'offset' => '20250108000000|0|2', 'limit' => 2 ],
			[ 'otherwiki' => [ 1 ] ],
			1,
			[ 'dir' => 'prev', 'offset' => '20250108000000|-1|2', 'limit' => 2 ],
			false,
			[ 95 ],
		];

		yield '3 rows, identical timestamps, limit=2, backwards from second page' => [
			$resultsWithIdenticalTimestamps,
			[ 'dir' => 'prev', 'offset' => '20250108000000|-1|2', 'limit' => 2 ],
			[ 'testwiki' => [ 1 ] ],
			2,
			false,
			[ 'offset' => '20250108000000|0|2', 'limit' => 2 ],
			[ 0, 95 ],
		];
	}

	/**
	 * Convenience function to create an ordered result set of mock revision data
	 * with the specified timestamps.
	 *
	 * @param string[] $timestamps The MW timestamps of the revisions.
	 * @return IResultWrapper
	 */
	private static function makeMockResult( array $timestamps ): IResultWrapper {
		$rows = [];
		$revId = 1 + count( $timestamps );

		// Sort the timestamps in descending order, since the DB would sort the revisions in the same way.
		usort( $timestamps, static fn ( string $ts, string $other ): int => $other <=> $ts );

		foreach ( $timestamps as $timestamp ) {
			$rows[] = (object)[
				'rev_id' => $revId,
				'rev_page' => '1',
				'rev_actor' => '1',
				'rev_user' => '1',
				'rev_user_text' => self::TEMP_USERNAME,
				'rev_timestamp' => $timestamp,
				'rev_minor_edit' => '0',
				'rev_deleted' => '0',
				'rev_len' => '100',
				'rev_parent_id' => $revId - 1,
				'rev_sha1' => '',
				'rev_comment_text' => '',
				'rev_comment_data' => null,
				'rev_comment_cid' => '1',
				'page_latest' => '2',
				'page_is_new' => '0',
				'page_namespace' => '0',
				'page_title' => 'Test page',
				'cuc_timestamp' => $timestamp,
				'ts_tags' => null,
			];

			$revId--;
		}

		return new FakeResultWrapper( $rows );
	}

	public function testBodyIsWrappedWithPlainlinksClass(): void {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder
			->method(
				$this->logicalOr(
					'select', 'from', 'distinct', 'where', 'andWhere',
					'join', 'orderBy', 'limit', 'queryInfo', 'caller'
				)
			)->willReturnSelf();
		$queryBuilder
			->method( 'fetchFieldValues' )
			->willReturn( [ $localWiki, $externalWiki ] );

		$database = $this->createMock( IReadableDatabase::class );
		$database
			->method( 'newSelectQueryBuilder' )
			->willreturn( $queryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider
			->method( 'getReplicaDatabase' )
			->willReturn( $database );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer
			->expects( $this->never() )
			->method( 'run' );

		$pager = $this->getPagerWithOverrides( [
			'HookContainer' => $hookContainer,
			'RequestAggregator' => $this->createMock( CheckUserApiRequestAggregator::class ),
			'LoadBalancerFactory' => $dbProvider,
		] );

		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->currentPage = Title::makeTitle( 0, 'Test page' );
		$pager->currentRevRecord = null;
		$pager->needsToEnableGlobalPreferenceAtWiki = false;
		$pager->externalApiLookupError = false;

		$this->assertSame(
			"<section class=\"mw-pager-body plainlinks\">\n",
			$pager->getStartBody()
		);
		$this->assertSame(
			"</section>\n",
			$pager->getEndBody()
		);
	}

	public function testShouldInstrumentForeignApiLookupErrors(): void {
		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ 'testwiki' ] );

		$checkUserDb = $this->createMock( IReadableDatabase::class );

		$localQueryBuilder = $this->createMock( SelectQueryBuilder::class );
		$localQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchResultSet' ) ) )
			->willReturnSelf();
		$localQueryBuilder->method( 'fetchResultSet' )
			->willReturn( new FakeResultWrapper( [] ) );

		$localDb = $this->createMock( IReadableDatabase::class );
		$localDb->method( 'newSelectQueryBuilder' )
			->willReturn( $localQueryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturnMap( [
				[ CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN, null, $checkUserDb ],
				[ 'testwiki', null, $localDb ]
			] );

		// Mock a failed permission API call
		$permsByWiki = [
			'testwiki' => [
				'error' => true,
			],
		];

		$apiRequestAggregator = $this->createMock( CheckUserApiRequestAggregator::class );
		$apiRequestAggregator->method( 'execute' )
			->willReturn( $permsByWiki );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createNoOpMock( HookContainer::class );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'HookContainer' => $hookContainer,
			'RequestAggregator' => $apiRequestAggregator,
			'LoadBalancerFactory' => $dbProvider,
			'GlobalContributionsLookup' => $globalContributionsLookup,
		] );
		$pager->doQuery();

		$this->assertApiLookupErrorCount( 1 );
	}

	/**
	 * Convenience function to assert that the API lookup error counter metric has a given count.
	 *
	 * @param int $expectedCount
	 * @return void
	 */
	private function assertApiLookupErrorCount( int $expectedCount ): void {
		$counter = $this->getServiceContainer()
			->getStatsFactory()
			->getCounter( GlobalContributionsPager::API_LOOKUP_ERROR_METRIC_NAME );

		$sampleValues = array_map( static fn ( $sample ) => $sample->getValue(), $counter->getSamples() );

		$this->assertSame( $expectedCount, $counter->getSampleCount() );
		$this->assertSame(
			(float)$expectedCount,
			(float)array_sum( $sampleValues )
		);
	}
}
