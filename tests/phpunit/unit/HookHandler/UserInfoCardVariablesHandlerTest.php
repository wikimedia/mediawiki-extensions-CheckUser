<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardVariablesHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Output\OutputPage;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardVariablesHandler
 */
class UserInfoCardVariablesHandlerTest extends MediaWikiUnitTestCase {
	private UserInfoCardButtonRenderer $buttonRendererMock;
	private HashConfig $configMock;
	private OutputPage $outputMock;

	private UserInfoCardVariablesHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->buttonRendererMock = $this->createMock( UserInfoCardButtonRenderer::class );

		$this->configMock = new HashConfig();
		$this->configMock->set( 'CheckUserEnableUserInfoCardInstrumentation', true );
		$this->configMock->set( 'CheckUserUserInfoCardShowXToolsLink', false );

		$userMock = $this->createMock( User::class );

		$this->outputMock = $this->createMock( OutputPage::class );
		$this->outputMock->method( 'getConfig' )->willReturn( $this->configMock );
		$this->outputMock->method( 'getUser' )->willReturn( $userMock );

		$this->handler = new UserInfoCardVariablesHandler( $this->buttonRendererMock );
	}

	public function testSkipIfDisabled() {
		$this->buttonRendererMock->expects( $this->once() )
			->method( 'isUicEnabledForUser' )
			->willReturn( false );

		$vars = [];
		$this->handler->onMakeGlobalVariablesScript( $vars, $this->outputMock );

		$this->assertArrayEquals( [], $vars );
	}

	public function testCheckUser() {
		$this->buttonRendererMock->expects( $this->once() )
			->method( 'isUicEnabledForUser' )
			->willReturn( true );

		$vars = [];
		$this->handler->onMakeGlobalVariablesScript( $vars, $this->outputMock );

		$this->assertArrayEquals( [
			'wgCheckUserEnableUserInfoCardInstrumentation' => true,
			'wgCheckUserUserInfoCardShowXToolsLink' => false,
		], $vars );
	}

	public function testGrowthExperiments() {
		$this->buttonRendererMock->expects( $this->once() )
			->method( 'isUicEnabledForUser' )
			->willReturn( true );

		$this->configMock->set( 'GEUserImpactMaxEdits', 1234 );
		$this->configMock->set( 'GEUserImpactMaxThanks', 4321 );

		$vars = [];
		$this->handler->onMakeGlobalVariablesScript( $vars, $this->outputMock );

		$this->assertArrayEquals( [
			'wgCheckUserGEUserImpactMaxEdits' => 1234,
			'wgCheckUserGEUserImpactMaxThanks' => 4321,
			'wgCheckUserEnableUserInfoCardInstrumentation' => true,
			'wgCheckUserUserInfoCardShowXToolsLink' => false,
		], $vars );
	}
}
