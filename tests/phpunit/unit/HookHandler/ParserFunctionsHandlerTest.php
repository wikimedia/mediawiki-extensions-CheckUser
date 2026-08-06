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
		$this->parserOutput = new ParserOutput();

		$localizer = new FakeQqxMessageLocalizer();

		$this->parser = $this->createMock( Parser::class );
		$this->parser->method( 'getOutput' )->willReturn( $this->parserOutput );
		$this->parser->method( 'msg' )
			->willReturnCallback( static fn ( string $msg, ...$params ) => $localizer->msg( $msg, ...$params ) );
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

	public function testRenderReturnsRawHtmlForValidUsername(): void {
		$this->userNameUtils->method( 'getCanonical' )
			->with( 'Foo', UserRigorOptions::RIGOR_VALID )
			->willReturn( 'Foo' );
		$this->buttonRenderer->expects( $this->once() )
			->method( 'render' )
			->with(
				'Foo',
				// Never the blocked variant, and always hidden
				false,
				$this->anything(),
				true
			)
			->willReturn( '<button>button</button>' );

		$result = $this->getHandler()->renderUserInfoCardButton( $this->parser, 'Foo' );

		$this->assertSame( [ '<button>button</button>', 'isRawHTML' => true ], $result );
		$this->assertContains(
			'ext.checkUser.styles',
			$this->parserOutput->getModuleStyles()
		);
	}

	public function testRenderUsesTheCanonicalUsername(): void {
		$this->userNameUtils->method( 'getCanonical' )
			->with( 'user:foo_bar', UserRigorOptions::RIGOR_VALID )
			->willReturn( 'Foo bar' );
		$this->buttonRenderer->expects( $this->once() )
			->method( 'render' )
			->with(
				'Foo bar',
				false,
				$this->anything(),
				true
			)
			->willReturn( '<button>button</button>' );

		// Also covers the surrounding whitespace that template expansion tends to leave behind.
		$this->getHandler()->renderUserInfoCardButton( $this->parser, "  user:foo_bar\n" );
	}

	public function testRenderReturnsNothingAndTracksInvalidUsernames(): void {
		$this->userNameUtils->method( 'getCanonical' )->willReturn( false );

		$this->buttonRenderer->expects( $this->never() )->method( 'render' );
		$this->parser->expects( $this->once() )
			->method( 'addTrackingCategory' )
			->with( 'checkuser-uic-invalid-username-category' );

		$this->assertSame(
			'',
			$this->getHandler()->renderUserInfoCardButton( $this->parser, 'whatever' )
		);
		$this->assertSame( [], $this->parserOutput->getModuleStyles() );
	}
}
