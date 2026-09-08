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

	private function getRenderer( bool $isTemp = false, bool $isBlocked = false ): UserInfoCardButtonRenderer {
		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'isTemp' )->willReturn( $isTemp );
		$blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$blockStatusCache->method( 'getIndefinitelyBlockedOrLockedUsers' )
			->willReturnCallback( static fn ( $users ) => $isBlocked ? $users : [] );
		return new UserInfoCardButtonRenderer( $userNameUtils, $blockStatusCache );
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

	public static function provideIconNames(): array {
		return [
			'named user' => [ false, false, 'userAvatar' ],
			'temporary account' => [ false, true, 'userTemporary' ],
			'blocked user' => [ true, false, 'userBlocked' ],
			// Blocked wins over temporary, so that an indefinitely blocked temporary account is
			// not shown as merely temporary.
			'blocked temporary account' => [ true, true, 'userBlocked' ],
		];
	}

	/** @dataProvider provideIconNames */
	public function testGetIconName( bool $isBlocked, bool $isTemp, string $expectedIconName ): void {
		$this->assertSame(
			$expectedIconName,
			$this->getRenderer( $isTemp, $isBlocked )->getIconName( 'Foo' )
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

	public function testNoCustomIconsIgnoresBlock(): void {
		$this->assertSame(
			'userAvatar',
			$this->getRenderer( false, true )->getIconName( 'Foo', [ 'customIcons' => false ] )
		);
	}
}
