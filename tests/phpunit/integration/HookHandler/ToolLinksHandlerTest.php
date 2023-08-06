<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\ToolLinksHandler;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RequestContext;
use SpecialPage;
use Title;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\ToolLinksHandler
 */
class ToolLinksHandlerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideOnUserToolLinksEdit */
	public function testOnUserToolLinksEdit( string $requestTitle, array $expectedItems ) {
		$testUser = new UserIdentityValue( 42, 'Foobar' );
		$mainRequest = RequestContext::getMain();
		$mainRequest->setTitle( Title::newFromText( $requestTitle ) );
		$mainRequest->getRequest()->setVal( 'reason', 'testing' );
		$mockLinkRenderer = $this->createMock( LinkRenderer::class );
		if ( $requestTitle == 'Special:CheckUserLog' ) {
			$mockLinkRenderer->method( 'makeLink' )
				->with(
					SpecialPage::getTitleFor( 'CheckUserLog', $testUser->getName() ),
					wfMessage( 'checkuser-log-checks-on' )->text()
				)->willReturn( 'CheckUserLog mocked link' );
		} else {
			$mockLinkRenderer->method( 'makeLink' )
				->with(
					SpecialPage::getTitleFor( 'CheckUser', $testUser->getName() ),
					wfMessage( 'checkuser-toollink-check' )->text(),
					[],
					[ 'reason' => 'testing' ]
				)->willReturn( 'CheckUser mocked link' );
		}
		$items = [];
		$services = $this->getServiceContainer();
		( new ToolLinksHandler(
			$services->get( 'PermissionManager' ),
			$services->get( 'SpecialPageFactory' ),
			$mockLinkRenderer
		) )->onUserToolLinksEdit( $testUser->getId(), $testUser->getName(), $items );
		if ( count( $expectedItems ) != 0 ) {
			$this->assertCount(
				1, $items, 'A tool link should have been added'
			);
			$this->assertArrayEquals(
				$expectedItems,
				$items,
				'The link was not correctly generated'
			);
		} else {
			$this->assertCount(
				0, $items, 'A tool link should not have been added'
			);
		}
	}

	public static function provideOnUserToolLinksEdit() {
		return [
			'Current title is not in special namespace' => [
				'Testing1234', []
			],
			'Current title is in the special namespace, but not the CheckUserLog or CheckUser' => [
				'Special:History', []
			],
			'Current title is Special:CheckUser' => [
				'Special:CheckUser', [ 'CheckUser mocked link' ]
			],
			'Current title is Special:CheckUserLog' => [
				'Special:CheckUserLog', [ 'CheckUserLog mocked link' ]
			]
		];
	}
}
