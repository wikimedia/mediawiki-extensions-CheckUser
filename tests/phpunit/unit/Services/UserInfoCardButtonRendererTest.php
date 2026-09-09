<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardSuggestedInvestigationsCache;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserSelectQueryBuilder;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer
 */
class UserInfoCardButtonRendererTest extends MediaWikiUnitTestCase {

	use MockAuthorityTrait;

	/**
	 * @param string[] $tempUsers Users for whom UserNameUtils reports a temporary account
	 * @param string[] $blockedUsers Users whom the block status cache reports as blocked or locked
	 * @param string[] $hiddenUsers Users who were hidden
	 * @param string[] $usersInOpenCase Users whom the SI cache reports as members of an open case
	 * @param bool $siEnabled Value of $wgCheckUserSuggestedInvestigationsEnabled
	 */
	private function getRenderer(
		array $tempUsers = [],
		array $blockedUsers = [],
		array $hiddenUsers = [],
		array $usersInOpenCase = [],
		bool $siEnabled = true
	): UserInfoCardButtonRenderer {
		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'isTemp' )
			->willReturnCallback( static fn ( $name ) => in_array( $name, $tempUsers, true ) );

		$blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$blockStatusCache->method( 'getIndefinitelyBlockedOrLockedUsers' )
			->willReturnCallback(
				static fn ( $names ) => array_values( array_intersect( $names, $blockedUsers ) )
			);

		// Treat every queried name as an existing user, so that only the hidden ones are dropped
		$queriedNames = [];
		$queryBuilder = $this->createMock( UserSelectQueryBuilder::class );
		$queryBuilder->method( 'whereUserNames' )
			->willReturnCallback( static function ( $names ) use ( &$queriedNames, $queryBuilder ) {
				$queriedNames = (array)$names;
				return $queryBuilder;
			} );
		$queryBuilder->method( 'hidden' )->willReturnSelf();
		$queryBuilder->method( 'caller' )->willReturnSelf();
		$queryBuilder->method( 'fetchUserNames' )
			->willReturnCallback( static function () use ( &$queriedNames, $hiddenUsers ) {
				return array_values( array_diff( $queriedNames, $hiddenUsers ) );
			} );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		$siCache = $this->createMock( UserInfoCardSuggestedInvestigationsCache::class );
		$siCache->method( 'getUsersWithOpenCases' )
			->willReturnCallback(
				static fn ( $names ) => array_values( array_intersect( $names, $usersInOpenCase ) )
			);

		return new UserInfoCardButtonRenderer(
			$userNameUtils,
			$blockStatusCache,
			$userIdentityLookup,
			$siCache,
			new ServiceOptions(
				[ 'CheckUserSuggestedInvestigationsEnabled' ],
				[ 'CheckUserSuggestedInvestigationsEnabled' => $siEnabled ]
			),
		);
	}

	public function testRenderProducesExpectedMarkup(): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer()->render( 'Foo', 'userAvatar', $localizer );

		$this->assertStringContainsString( '<button', $html );
		$this->assertStringContainsString( 'data-username="Foo"', $html );
		$this->assertStringContainsString(
			'aria-label="(checkuser-userinfocard-toggle-button-aria-label: Foo)"',
			$html
		);
		$this->assertStringContainsString( 'ext-checkuser-userinfocard-button__icon', $html );
		$this->assertStringNotContainsString( 'hidden="', $html );
	}

	public static function provideIconVariants(): array {
		return [
			'userAvatar' => [ 'userAvatar' ],
			'userTemporary' => [ 'userTemporary' ],
			'userBlocked' => [ 'userBlocked' ],
			'suggestedInvestigations' => [ 'suggestedInvestigations' ],
		];
	}

	/** @dataProvider provideIconVariants */
	public function testAppliesPassedIcon( string $iconName ): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer()->render( 'Foo', $iconName, $localizer );

		$this->assertStringContainsString(
			"ext-checkuser-userinfocard-button__icon--$iconName",
			$html
		);
	}

	public function testUsernameIsEscaped(): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer()->render( 'Foo "&"', 'userAvatar', $localizer );

		$this->assertStringContainsString( 'data-username="Foo &quot;&amp;&quot;"', $html );
		$this->assertStringNotContainsString( '<bar>', $html );
	}

	public function testHiddenByDefault(): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer()->render( 'Foo', 'userAvatar', $localizer, true );
		$this->assertStringContainsString( 'hidden="', $html );
	}

	public static function provideIconNames(): array {
		return [
			'Named user' => [
				'isBlocked' => false,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => false,
				'options' => [],
				'expectedIconName' => 'userAvatar',
			],
			'Temporary account' => [
				'isBlocked' => false,
				'isTemp' => true,
				'isHidden' => false,
				'isInOpenCase' => false,
				'options' => [],
				'expectedIconName' => 'userTemporary',
			],
			'Blocked user' => [
				'isBlocked' => true,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => false,
				'options' => [],
				'expectedIconName' => 'userBlocked',
			],
			'User in SI case' => [
				'isBlocked' => false,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => true,
				'options' => [
					'viewer' => [ 'checkuser-suggested-investigations' ],
				],
				'expectedIconName' => 'suggestedInvestigations',
			],

			// Check priorities
			'Blocked temporary account - blocked wins over temporary' => [
				'isBlocked' => true,
				'isTemp' => true,
				'isHidden' => false,
				'isInOpenCase' => false,
				'options' => [],
				'expectedIconName' => 'userBlocked',
			],
			'Blocked account that is in SI case - block wins over SI' => [
				'isBlocked' => true,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => true,
				'options' => [
					'viewer' => [ 'checkuser-suggested-investigations' ],
				],
				'expectedIconName' => 'userBlocked',
			],

			// customIcons tests
			'customIcons set to false does not skip temporary account icon' => [
				'isBlocked' => false,
				'isTemp' => true,
				'isHidden' => false,
				'isInOpenCase' => false,
				'options' => [
					'customIcons' => false,
				],
				'expectedIconName' => 'userTemporary',
			],
			'Blocked user, but customIcons is false - block is ignored' => [
				'isBlocked' => true,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => false,
				'options' => [
					'customIcons' => false,
				],
				'expectedIconName' => 'userAvatar',
			],
			'User in SI case, but customIcons is false - case is ignored' => [
				'isBlocked' => false,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => true,
				'options' => [
					'customIcons' => false,
				],
				'expectedIconName' => 'userAvatar',
			],

			// Hidden users
			'User is hidden, but viewer cannot see that' => [
				'isBlocked' => true,
				'isTemp' => false,
				'isHidden' => true,
				'isInOpenCase' => false,
				'options' => [
					'viewer' => [],
				],
				'expectedIconName' => 'userAvatar',
			],
			'User is hidden, and viewer can see that' => [
				'isBlocked' => true,
				'isTemp' => false,
				'isHidden' => true,
				'isInOpenCase' => false,
				'options' => [
					'viewer' => [ 'hideuser' ],
				],
				'expectedIconName' => 'userBlocked',
			],
			'User is hidden, and viewer is null' => [
				'isBlocked' => true,
				'isTemp' => false,
				'isHidden' => true,
				'isInOpenCase' => false,
				'options' => [
					'viewer' => null,
				],
				'expectedIconName' => 'userAvatar',
			],

			// SI and viewer rights
			'User in SI case, but viewer cannot see it' => [
				'isBlocked' => false,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => true,
				'options' => [
					'viewer' => [],
				],
				'expectedIconName' => 'userAvatar',
			],
			'User in SI case, but viewer is null' => [
				'isBlocked' => false,
				'isTemp' => false,
				'isHidden' => false,
				'isInOpenCase' => true,
				'options' => [
					'viewer' => null,
				],
				'expectedIconName' => 'userAvatar',
			],
		];
	}

	/** @dataProvider provideIconNames */
	public function testGetIconName(
		bool $isBlocked,
		bool $isTemp,
		bool $isHidden,
		bool $isInOpenCase,
		array $options,
		string $expectedIconName
	): void {
		if ( is_array( $options['viewer'] ?? null ) ) {
			$options['viewer'] = $this->mockAnonAuthorityWithPermissions( $options['viewer'] );
		}

		$this->assertSame(
			$expectedIconName,
			$this->getRenderer(
				$isTemp ? [ 'Foo' ] : [],
				$isBlocked ? [ 'Foo' ] : [],
				$isHidden ? [ 'Foo' ] : [],
				$isInOpenCase ? [ 'Foo' ] : [],
			)->getIconName( 'Foo', $options )
		);
	}

	public function testOpenCaseIsIgnoredWhenSuggestedInvestigationsAreDisabled(): void {
		$renderer = $this->getRenderer( [], [], [], [ 'Foo' ], false );
		$viewer = $this->mockAnonAuthorityWithPermissions( [ 'checkuser-suggested-investigations' ] );

		$this->assertSame(
			'userAvatar',
			$renderer->getIconName( 'Foo', [ 'viewer' => $viewer ] )
		);
	}

	public function testGetIconNamesForUsersMapsEveryUser(): void {
		$renderer = $this->getRenderer(
			[ '~2026-1' ],
			[ 'BlockedUser' ],
			[],
			[ 'CaseUser', 'BlockedUser' ]
		);
		$viewer = $this->mockAnonAuthorityWithPermissions( [ 'checkuser-suggested-investigations' ] );

		$actual = $renderer->getIconNamesForUsers(
			[ 'NamedUser', '~2026-1', 'BlockedUser', 'CaseUser' ],
			[ 'viewer' => $viewer ]
		);
		ksort( $actual );

		$expected = [
			'BlockedUser' => 'userBlocked',
			'CaseUser' => 'suggestedInvestigations',
			'NamedUser' => 'userAvatar',
			'~2026-1' => 'userTemporary',
		];
		ksort( $expected );

		$this->assertSame( $expected, $actual );
	}

	public function testGetIconNamesForUsersWithNoUsers(): void {
		$this->assertSame(
			[],
			$this->getRenderer()->getIconNamesForUsers( [] )
		);
	}
}
