<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer
 */
class UserInfoCardButtonRendererTest extends MediaWikiUnitTestCase {

	private function getRenderer( bool $isTemp = false ): UserInfoCardButtonRenderer {
		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'isTemp' )->willReturn( $isTemp );
		return new UserInfoCardButtonRenderer( $userNameUtils );
	}

	public function testRenderProducesExpectedMarkup(): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer()->render( 'Foo', false, $localizer );

		$this->assertStringContainsString( '<button', $html );
		$this->assertStringContainsString( 'data-username="Foo"', $html );
		$this->assertStringContainsString(
			'aria-label="(checkuser-userinfocard-toggle-button-aria-label: Foo)"',
			$html
		);
		$this->assertStringContainsString( 'ext-checkuser-userinfocard-button__icon', $html );
		$this->assertStringNotContainsString( 'hidden="', $html );
	}

	/** @dataProvider provideIconVariants */
	public function testIconVariant( bool $isBlocked, bool $isTemp, string $expectedIconClass ): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer( $isTemp )->render( 'Foo', $isBlocked, $localizer );

		$this->assertStringContainsString(
			"ext-checkuser-userinfocard-button__icon--$expectedIconClass",
			$html
		);
	}

	public static function provideIconVariants(): array {
		return [
			'named user' => [ false, false, 'userAvatar' ],
			'temporary account' => [ false, true, 'userTemporary' ],
			'blocked user' => [ true, false, 'userBlocked' ],
			// Blocked wins over temporary, so that an indefinitely blocked temporary account is
			// not shown as merely temporary.
			'blocked temporary account' => [ true, true, 'userBlocked' ],
		];
	}

	/** @dataProvider provideIconVariants */
	public function testGetIconName( bool $isBlocked, bool $isTemp, string $expectedIconName ): void {
		$this->assertSame(
			$expectedIconName,
			$this->getRenderer( $isTemp )->getIconName( 'Foo', $isBlocked )
		);
	}

	public function testUsernameIsEscaped(): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer()->render( 'Foo "&"', false, $localizer );

		$this->assertStringContainsString( 'data-username="Foo &quot;&amp;&quot;"', $html );
		$this->assertStringNotContainsString( '<bar>', $html );
	}

	public function testHiddenByDefault(): void {
		$localizer = new FakeQqxMessageLocalizer();
		$html = $this->getRenderer()->render( 'Foo', false, $localizer, true );
		$this->assertStringContainsString( 'hidden="', $html );
	}
}
