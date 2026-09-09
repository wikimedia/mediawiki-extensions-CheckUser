<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer
 */
class UserInfoCardButtonRendererTest extends MediaWikiUnitTestCase {

	/**
	 * @param string[] $tempUsers Users for whom UserNameUtils reports a temporary account
	 * @param string[] $blockedUsers Users whom the block status cache reports as blocked or locked
	 */
	private function getRenderer(
		array $tempUsers = [],
		array $blockedUsers = [],
	): UserInfoCardButtonRenderer {
		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'isTemp' )
			->willReturnCallback( static fn ( $name ) => in_array( $name, $tempUsers, true ) );

		$blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$blockStatusCache->method( 'getIndefinitelyBlockedOrLockedUsers' )
			->willReturnCallback(
				static fn ( $names ) => array_values( array_intersect( $names, $blockedUsers ) )
			);

		return new UserInfoCardButtonRenderer(
			$userNameUtils,
			$blockStatusCache,
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

	public static function provideIconVariants() {
		return [
			[ 'userAvatar' ],
			[ 'userTemporary' ],
			[ 'userBlocked' ],
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
				'options' => [],
				'expectedIconName' => 'userAvatar',
			],
			'Temporary account' => [
				'isBlocked' => false,
				'isTemp' => true,
				'options' => [],
				'expectedIconName' => 'userTemporary',
			],
			'Blocked user' => [
				'isBlocked' => true,
				'isTemp' => false,
				'options' => [],
				'expectedIconName' => 'userBlocked',
			],

			// Check priorities
			'Blocked temporary account - blocked wins over temporary' => [
				'isBlocked' => true,
				'isTemp' => true,
				'options' => [],
				'expectedIconName' => 'userBlocked',
			],

			// customIcons tests
			'customIcons set to false does not skip temporary account icon' => [
				'isBlocked' => false,
				'isTemp' => true,
				'options' => [
					'customIcons' => false,
				],
				'expectedIconName' => 'userTemporary',
			],
			'Blocked user, but customIcons is false - block is ignored' => [
				'isBlocked' => true,
				'isTemp' => false,
				'options' => [
					'customIcons' => false,
				],
				'expectedIconName' => 'userAvatar',
			],
		];
	}

	/** @dataProvider provideIconNames */
	public function testGetIconName(
		bool $isBlocked,
		bool $isTemp,
		array $options,
		string $expectedIconName
	): void {
		$this->assertSame(
			$expectedIconName,
			$this->getRenderer(
				$isTemp ? [ 'Foo' ] : [],
				$isBlocked ? [ 'Foo' ] : [],
			)->getIconName( 'Foo', $options )
		);
	}

	public function testGetIconNamesForUsersMapsEveryUser(): void {
		$renderer = $this->getRenderer(
			[ '~2026-1' ],
			[ 'BlockedUser' ],
		);

		$actual = $renderer->getIconNamesForUsers( [ 'NamedUser', '~2026-1', 'BlockedUser' ] );
		ksort( $actual );

		$expected = [
			'BlockedUser' => 'userBlocked',
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
