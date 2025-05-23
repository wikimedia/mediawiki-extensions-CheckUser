<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\CheckUser\HookHandler\UserLinkRendererUserLinkPostRenderHandler
 */
class UserLinkRendererUserLinkPostRenderHandlerTest extends MediaWikiIntegrationTestCase {

	public function testRenderWithoutFeatureEnabled() {
		$user = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user,
			Preferences::ENABLE_USER_INFO_CARD,
			false
		);
		$userOptionsManager->saveOptions( $user );
		$output = $this->createMock( OutputPage::class );
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getOutput' )->willReturn( $output );
		$this->assertStringStartsWith( '<a href=', $this->getServiceContainer()->getUserLinkRenderer()->userLink(
			$user,
			$context
		) );
	}

	public function testRenderWithFeatureEnabled() {
		$user = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user,
			Preferences::ENABLE_USER_INFO_CARD,
			true
		);
		$userOptionsManager->saveOptions( $user );
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$wikiId = WikiMap::getCurrentWikiId();
		$userId = $user->getUser()->getId();
		$expected = "<span class=\"cdx-button__icon";
		$html = $this->getServiceContainer()->getUserLinkRenderer()->userLink(
			$user,
			$context
		);
		$this->assertStringContainsString( $expected, $html, 'Output does not contain Codex button' );
		$expected = "class=\"ext-checkuser-userinfocard-button ext-checkuser-userinfocard-id-$wikiId:$userId";
		$this->assertStringContainsString( $expected, $html, 'Output does not contain expected CSS classes' );
	}
}
