<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\CheckUser\HookHandler\PageDisplay;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PageDisplay
 */
class PageDisplayTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [
				'enwiki' => 'https://en.example.org',
				'metawiki' => 'https://meta.example.org',
			],
			'wgArticlePath' => [
				'enwiki' => '/wiki/$1',
				'metawiki' => '/wiki/$1',
			],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );
	}

	public function testBeforeInitializeHookWithoutConfigSet() {
		$pageDisplayHook = new PageDisplay(
			new HashConfig( [
				'CheckUserGlobalContributionsCentralWikiId' => false,
			] ),
			$this->createMock( PermissionManager::class ),
			$this->createMock( UserOptionsLookup::class )
		);
		$output = $this->createMock( OutputPage::class );
		$request = new FauxRequest();
		$pageDisplayHook->onBeforeInitialize(
			SpecialPage::getTitleFor( 'GlobalContributions' ),
			null,
			$output,
			$this->createMock( User::class ),
			$request,
			$this->createMock( ActionEntryPoint::class )
		);
		$output->expects( $this->never() )->method( 'redirect' );
	}

	public function testBeforeInitializeHookWithConfigSet() {
		$pageDisplayHook = new PageDisplay(
			new HashConfig( [
				'CheckUserGlobalContributionsCentralWikiId' => 'metawiki',
			] ),
			$this->createMock( PermissionManager::class ),
			$this->createMock( UserOptionsLookup::class )
		);
		$title = SpecialPage::getTitleFor( 'GlobalContributions' );
		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )->willReturn( $title );
		$request = new FauxRequest( [ 'target' => '127.0.0.1', 'title' => 'Special:GlobalContributions' ] );
		$output->method( 'getRequest' )->willReturn( $request );
		$output->expects( $this->once() )->method( 'redirect' )
			->with( 'https://meta.example.org/wiki/Special:GlobalContributions?target=127.0.0.1' );
		$pageDisplayHook->onBeforeInitialize(
			$title,
			null,
			$output,
			$this->createMock( User::class ),
			$request,
			$this->createMock( ActionEntryPoint::class )
		);
	}

}
