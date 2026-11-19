<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Extension\CheckUser\HookHandler\ParserFunctionsHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserRigorOptions;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\ParserFunctionsHandler
 */
class ParserFunctionsHandlerTest extends MediaWikiUnitTestCase {

	private UserNameUtils $userNameUtils;
	private UserInfoCardButtonRenderer $buttonRenderer;
	private ParserOutput $parserOutput;
	private Parser $parser;

	protected function setUp(): void {
		parent::setUp();

		$this->userNameUtils = $this->createMock( UserNameUtils::class );
		$this->buttonRenderer = $this->createMock( UserInfoCardButtonRenderer::class );
		$this->buttonRenderer->method( 'getIconName' )
			->willReturn( 'userAvatar' );
		$this->parserOutput = new ParserOutput();

		$localizer = new FakeQqxMessageLocalizer();

		$this->parser = $this->createMock( Parser::class );
		$this->parser->method( 'getOutput' )->willReturn( $this->parserOutput );
		$this->parser->method( 'msg' )
			->willReturnCallback( static fn ( string $msg, ...$params ) => $localizer->msg( $msg, ...$params ) );

		ParserFunctionsHandler::clearRecordedUserInfoCardTargets();
	}

	private function getHandler(): ParserFunctionsHandler {
		return new ParserFunctionsHandler( $this->userNameUtils, $this->buttonRenderer );
	}

	public function testOnParserFirstCallInitRegistersUicFunction(): void {
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->once() )
			->method( 'setFunctionHook' )
			->with( 'uic', $this->isType( 'callable' ) );

		$this->getHandler()->onParserFirstCallInit( $parser );
	}

	/** @dataProvider provideValidUsernames */
	public function testRenderReturnsRawHtmlForValidUsername( string $providedName, string $canonicalName ): void {
		$this->userNameUtils->method( 'getCanonical' )
			->with( trim( $providedName ), UserRigorOptions::RIGOR_VALID )
			->willReturn( $canonicalName );
		$this->buttonRenderer->expects( $this->once() )
			->method( 'render' )
			->with(
				$canonicalName,
				// Never the blocked variant, and always hidden
				'userAvatar',
				$this->anything(),
				true
			)
			->willReturn( '<button>button</button>' );

		$result = $this->getHandler()->renderUserInfoCardButton( $this->parser, $providedName );

		$this->assertSame( [ '<button>button</button>', 'isHTML' => true ], $result );
		$this->assertContains(
			'ext.checkUser.styles',
			$this->parserOutput->getModuleStyles()
		);
		// Interface messages lose the parser output, so the target is recorded here as well
		$this->assertSame( [ $canonicalName ], ParserFunctionsHandler::getRecordedUserInfoCardTargets() );
	}

	public static function provideValidUsernames(): array {
		return [
			'Normal username' => [
				'providedName' => 'Foo',
				'canonicalName' => 'Foo',
			],
			'Non-canonical username' => [
				'providedName' => "  user:foo_bar\n",
				'canonicalName' => 'Foo bar',
			],
			'Numeric username' => [
				'providedName' => '123',
				'canonicalName' => '123',
			],
		];
	}

	/** @dataProvider provideInvalidUsernames */
	public function testRenderReturnsNothingAndTracksInvalidUsernames( string $username, string $category ): void {
		$this->userNameUtils->method( 'getCanonical' )->willReturn( false );

		$this->buttonRenderer->expects( $this->never() )->method( 'render' );
		$this->parser->expects( $this->once() )
			->method( 'addTrackingCategory' )
			->with( $category );

		$this->assertSame(
			'',
			$this->getHandler()->renderUserInfoCardButton( $this->parser, $username )
		);
		$this->assertSame( [], $this->parserOutput->getModuleStyles() );
		$this->assertSame( [], ParserFunctionsHandler::getRecordedUserInfoCardTargets() );
	}

	public static function provideInvalidUsernames(): array {
		return [
			'Invalid (non-IP) username' => [
				'username' => '<3',
				'category' => 'checkuser-uic-invalid-username-category',
			],
			'IPv4 address' => [
				'username' => '1.2.3.4',
				'category' => 'checkuser-uic-ip-target-category',
			],
			'IPv4 range' => [
				'username' => '1.2.3.4/24',
				'category' => 'checkuser-uic-ip-target-category',
			],
			'IPv6 address' => [
				'username' => '2001:db8::1',
				'category' => 'checkuser-uic-ip-target-category',
			],
			'IPv6 range' => [
				'username' => '2001:db8::1/64',
				'category' => 'checkuser-uic-ip-target-category',
			],
		];
	}
}
