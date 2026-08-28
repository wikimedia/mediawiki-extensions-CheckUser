<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Extension\CheckUser\HookHandler\ParserFunctionsHandler;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\Ext\DOMUtils;

/**
 * Exercises {{#uic:}} through the real parser, which is the only way to cover how the raw HTML
 * survives the rest of the parse.
 *
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\ParserFunctionsHandler
 */
class ParserFunctionsHandlerTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();

		ParserFunctionsHandler::clearRecordedUserInfoCardTargets();
	}

	private function parse( string $wikitext ): ParserOutput {
		return $this->getServiceContainer()->getParserFactory()->getInstance()->parse(
			$wikitext,
			Title::makeTitle( NS_PROJECT, 'Page title' ),
			ParserOptions::newFromAnon()
		);
	}

	public function testParsesToButtonAndRequestsStyles(): void {
		$parserOutput = $this->parse( '{{#uic:Foo}}' );
		$html = $parserOutput->getContentHolderText();

		$document = DOMUtils::parseHTML( $html );
		$button = DOMCompat::querySelector( $document, '.ext-checkuser-userinfocard-button' );

		$this->assertNotNull( $button );
		$this->assertTrue( $button->hasAttribute( 'hidden' ), 'Button should be hidden by default' );
		$this->assertSame( 'Foo', $button->getAttribute( 'data-username' ) );

		// Only styles module should be added by parser
		$this->assertContains( 'ext.checkUser.styles', $parserOutput->getModuleStyles() );
		$this->assertNotContains( 'ext.checkUser.userInfoCard', $parserOutput->getModules() );
	}

	public function testRecordsTargetInExtensionData(): void {
		$parserOutput = $this->parse( '{{#uic:Foo}}' );

		$this->assertSame(
			[ 'Foo' => true ],
			$parserOutput->getExtensionData( ParserFunctionsHandler::TARGETS_EXTENSION_DATA_KEY )
		);
		$this->assertSame(
			[],
			$parserOutput->getJsConfigVars(),
			'The targets must not reach the client as a JS config variable'
		);
		$this->assertSame(
			[ 'Foo' ],
			ParserFunctionsHandler::getRecordedUserInfoCardTargets()
		);
	}

	public function testRecordsEveryTargetOnce(): void {
		$parserOutput = $this->parse( '{{#uic:Foo}} {{#uic:Bar}} {{#uic:Foo}}' );

		$targets = $parserOutput->getExtensionData( ParserFunctionsHandler::TARGETS_EXTENSION_DATA_KEY );
		ksort( $targets );
		$this->assertSame( [ 'Bar' => true, 'Foo' => true ], $targets );
	}

	public function testRecordsCanonicalisedTarget(): void {
		$this->assertSame(
			[ 'Foo bar' => true ],
			$this->parse( '{{#uic:user:foo_bar}}' )
				->getExtensionData( ParserFunctionsHandler::TARGETS_EXTENSION_DATA_KEY )
		);
	}

	public function testDoesNotUseAnyCacheVaryingParserOption(): void {
		// The whole design rests on the output being the same for every viewer. Reading anything
		// per-viewer during the parse, such as the gendered aria label or the block status, would
		// either fragment the parser cache or, worse, cache one viewer's output for everyone.
		$baseline = array_intersect(
			$this->parse( 'Foo' )->getUsedOptions(),
			ParserOptions::allCacheVaryingOptions()
		);
		$withParserFunction = array_intersect(
			$this->parse( '{{#uic:Foo}}' )->getUsedOptions(),
			ParserOptions::allCacheVaryingOptions()
		);

		$this->assertSame(
			[],
			array_values( array_diff( $withParserFunction, $baseline ) )
		);
	}

	public function testRendersIdenticallyForDifferentViewers(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->getInstance();
		$title = Title::makeTitle( NS_PROJECT, 'Page title' );
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();

		$withCard = $this->getTestUser()->getUser();
		$userOptionsManager->setOption( $withCard, Preferences::ENABLE_USER_INFO_CARD, true );
		$userOptionsManager->saveOptions( $withCard );
		$withoutCard = $this->getTestSysop()->getUser();
		$userOptionsManager->setOption( $withoutCard, Preferences::ENABLE_USER_INFO_CARD, false );
		$userOptionsManager->saveOptions( $withoutCard );

		$this->assertSame(
			$parser->parse( '{{#uic:Foo}}', $title, ParserOptions::newFromUser( $withCard ) )
				->getContentHolderText(),
			$parser->parse( '{{#uic:Foo}}', $title, ParserOptions::newFromUser( $withoutCard ) )
				->getContentHolderText()
		);
	}

	public function testNormalisesUsername(): void {
		$html = $this->parse( '{{#uic:user:foo_bar}}' )->getContentHolderText();

		$this->assertStringContainsString( 'data-username="Foo bar"', $html );
	}

	/**
	 * The markup is returned with 'isRawHTML', which is unstripped only after
	 * BlockLevelPass::doBlockLevels() has run, so block markup around it must still work.
	 */
	public function testSurvivesBlockLevelMarkup(): void {
		$html = $this->parse( "* Reported: {{#uic:Foo}} [[User:Foo]]\n" )->getContentHolderText();

		$this->assertStringContainsString( '<li>', $html );
		$this->assertStringContainsString( 'data-username="Foo"', $html );
		// The button element must not have been mangled into escaped text.
		$this->assertStringContainsString( '<button type="button"', $html );
	}

	/** @dataProvider provideInvalidTargets */
	public function testRendersNothingForInvalidTargets( string $wikitext, string $category ): void {
		$parserOutput = $this->parse( $wikitext );

		$this->assertStringNotContainsString(
			'ext-checkuser-userinfocard-button',
			$parserOutput->getContentHolderText()
		);
		$this->assertNotContains(
			'ext.checkUser.styles',
			$parserOutput->getModuleStyles()
		);
		$this->assertNull(
			$parserOutput->getExtensionData( ParserFunctionsHandler::TARGETS_EXTENSION_DATA_KEY ),
		);

		$trackingCategories = $parserOutput->getCategoryNames();
		$this->assertSame( [ $category ], $trackingCategories );
	}

	public static function provideInvalidTargets(): array {
		return [
			'no argument' => [
				'wikitext' => '{{#uic:}}',
				'category' => 'Pages_with_an_invalid_user_info_card_target',
			],
			'whitespace only' => [
				'wikitext' => '{{#uic: }}',
				'category' => 'Pages_with_an_invalid_user_info_card_target',
			],
			'not a possible user name' => [
				'wikitext' => '{{#uic:Foo#bar}}',
				'category' => 'Pages_with_an_invalid_user_info_card_target',
			],
			'IP address' => [
				'wikitext' => '{{#uic:1.2.3.4}}',
				'category' => 'Pages_with_an_IP_as_user_info_card_target',
			],
		];
	}
}
