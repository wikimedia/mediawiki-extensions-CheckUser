<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\SidebarLinksHandler;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Skin;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\SidebarLinksHandler
 */
class SidebarLinksHandlerTest extends MediaWikiIntegrationTestCase {
	/** @var (Authority&MockObject) */
	private Authority $authority;

	/** @var (Skin&MockObject) */
	private Skin $skin;

	/** @var (CheckUserPermissionManager&MockObject) */
	private CheckUserPermissionManager $permissionManager;

	/** @var (CheckUserPermissionStatus&MockObject) */
	private CheckUserPermissionStatus $permissionStatus;

	/** @var (UserIdentity&MockObject) */
	private UserIdentity $relevantUser;

	private SidebarLinksHandler $sut;

	public function setUp(): void {
		parent::setUp();

		$this->authority = $this->createMock( Authority::class );
		$this->skin = $this->createMock( Skin::class );
		$this->permissionStatus = $this->createMock(
			CheckUserPermissionStatus::class
		);
		$this->permissionManager = $this->createMock(
			CheckUserPermissionManager::class
		);
		$this->relevantUser = $this->createMock(
			UserIdentity::class
		);

		$this->sut = new SidebarLinksHandler( $this->permissionManager );
	}

	/**
	 * @dataProvider whenTheLinkShouldNotBeAddedDataProvider
	 */
	public function testWhenTheLinkShouldNotBeAdded(
		array $expected,
		array $sidebar,
		bool $hasRelevantUser,
		bool $hasAccess
	): void {
		$this->skin
			->method( 'getRelevantUser' )
			->willReturn( $hasRelevantUser ? $this->relevantUser : null );
		$this->skin
			->method( 'getAuthority' )
			->willReturn( $this->authority );
		$this->skin
			->method( 'msg' )
			->willReturnCallback( function ( $key ): Message {
				$messageMock = $this->createMock( Message::class );
				$messageMock
					->method( 'text' )
					->willReturn( $key );

				return $messageMock;
			} );

		if ( $hasRelevantUser ) {
			$this->relevantUser
				->method( 'getName' )
				->willReturn( 'Relevant User name' );

			$this->permissionManager
				->expects( $this->once() )
				->method( 'canAccessUserGlobalContributions' )
				->with( $this->authority, 'Relevant User name' )
				->willReturn( $this->permissionStatus );
		} else {
			$this->permissionManager
				->expects( $this->never() )
				->method( 'canAccessUserGlobalContributions' );
		}

		$this->permissionStatus
			->method( 'isGood' )
			->willReturn( $hasAccess );

		$this->sut->onSidebarBeforeOutput( $this->skin, $sidebar );
		$this->assertEquals( $expected, $sidebar );
	}

	public function whenTheLinkShouldNotBeAddedDataProvider(): array {
		return [
			// Cases when the link is not added
			//
			'When there is no relevant user' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => false,
				'hasAccess' => false,
			],
			'When the accessing user lacks access' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => false,
			],
			'When access is not granted and the sidebar is empty' => [
				// Tests for errors checking preconditions for an empty sidebar
				// (i.e. "Undefined array key 'TOOLBOX'" errors)
				'expected' => [],
				'sidebar' => [],
				'hasRelevantUser' => false,
				'hasAccess' => false,
			],
			// Cases when the link is added
			//
			'When access is granted and the sidebar is empty' => [
				// Tests for errors updating the sidebar when it was previously
				// empty (i.e. "Undefined array key 'TOOLBOX'" errors)
				'expected' => [
					'TOOLBOX' => [
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => 'checkuser-global-contributions-link-sidebar',
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						],
					],
				],
				'sidebar' => [],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			],
			'When access is granted and the "contributions" link is the first one' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => 'checkuser-global-contributions-link-sidebar',
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						],
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			],
			'When preconditions are met and the "contributions" link is between others' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => 'checkuser-global-contributions-link-sidebar',
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						],
						'something-else' => [
							'id' => 't-something-else',
							'text' => 'something-else',
						]
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'something-else' => [
							'id' => 't-something-else',
							'text' => 'something-else',
						]
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			],
			'When preconditions are met and the "contributions" link is the last one' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => 'checkuser-global-contributions-link-sidebar',
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						]
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			]
		];
	}
}
