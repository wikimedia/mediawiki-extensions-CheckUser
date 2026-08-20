<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardNavigationHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardNavigationHandler
 */
class UserInfoCardNavigationHandlerTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	private const ITEM_KEY = 'checkuser-userinfocard';

	private function getViewer( bool $featureEnabled = true ): User {
		$viewer = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$viewer,
			Preferences::ENABLE_USER_INFO_CARD,
			$featureEnabled
		);
		$userOptionsManager->saveOptions( $viewer );
		return $viewer;
	}

	/**
	 * Runs the hook for a page and returns the resulting navigation links, plus the
	 * OutputPage the handler could add modules to.
	 *
	 * @param Title $title Title of the page being viewed
	 * @param User $viewer User viewing the page
	 * @param UserIdentity|null $relevantUser Relevant user to set on the skin, as special pages do
	 * @return array{0:array,1:\MediaWiki\Output\OutputPage}
	 */
	private function runHook( Title $title, User $viewer, ?UserIdentity $relevantUser = null ): array {
		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setUser( $viewer );
		$skin = $this->getServiceContainer()->getSkinFactory()->makeSkin( 'fallback' );
		$skin->setContext( $context );
		$context->setSkin( $skin );
		if ( $relevantUser ) {
			$skin->setRelevantUser( $relevantUser );
		}

		$handler = new UserInfoCardNavigationHandler(
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->get( 'CheckUserUserInfoCardBlockStatusCache' ),
			$this->getServiceContainer()->get( 'CheckUserUserInfoCardButtonRenderer' )
		);

		$links = [ 'views' => [], 'actions' => [] ];
		$handler->onSkinTemplateNavigation__Universal( $skin, $links );

		return [ $links, $context->getOutput() ];
	}

	public function testAddsItemOnUserPage() {
		$target = $this->getTestSysop()->getUser();
		[ $links, $output ] = $this->runHook(
			$target->getUserPage(),
			$this->getViewer()
		);

		$this->assertArrayHasKey( self::ITEM_KEY, $links['views'] );
		$item = $links['views'][self::ITEM_KEY];
		$this->assertSame( '#', $item['href'] );
		$this->assertSame( 'userAvatar', $item['icon'] );
		$this->assertSame(
			'ext-checkuser-userinfocard-navigation-item ' .
				'ext-checkuser-userinfocard-navigation-item--userAvatar',
			$item['class'],
			'Skins which drop the name of the icon need it as a class'
		);
		$this->assertSame( [ $target->getName() ], $item['tooltip-params'] );
		$this->assertNotSame( '', $item['text'] );

		$this->assertContains( 'ext.checkUser.userInfoCard', $output->getModules() );
		$this->assertContains( 'ext.checkUser.styles', $output->getModuleStyles() );
	}

	public function testAddsItemOnUserTalkPage() {
		$target = $this->getTestSysop()->getUser();
		[ $links ] = $this->runHook(
			$target->getTalkPage(),
			$this->getViewer()
		);

		$this->assertArrayHasKey( self::ITEM_KEY, $links['views'] );
	}

	public function testAddsItemOnUserSubpage() {
		$target = $this->getTestSysop()->getUser();
		[ $links ] = $this->runHook(
			Title::makeTitle( NS_USER, $target->getName() . '/sandbox' ),
			$this->getViewer()
		);

		$this->assertArrayHasKey(
			self::ITEM_KEY,
			$links['views'],
			'The user of the root page is the target on subpages'
		);
	}

	public function testDoesNotAddItemWithoutFeatureEnabled() {
		$target = $this->getTestSysop()->getUser();
		[ $links, $output ] = $this->runHook(
			$target->getUserPage(),
			$this->getViewer( false )
		);

		$this->assertArrayNotHasKey( self::ITEM_KEY, $links['views'] );
		$this->assertNotContains( 'ext.checkUser.userInfoCard', $output->getModules() );
	}

	public function testDoesNotAddItemForAnonymousViewer() {
		$this->disableAutoCreateTempUser();
		$target = $this->getTestSysop()->getUser();
		[ $links ] = $this->runHook(
			$target->getUserPage(),
			$this->getServiceContainer()->getUserFactory()->newAnonymous()
		);

		$this->assertArrayNotHasKey( self::ITEM_KEY, $links['views'] );
	}

	public function testDoesNotAddItemOnIPUserPage() {
		[ $links ] = $this->runHook(
			Title::makeTitle( NS_USER, '127.0.0.1' ),
			$this->getViewer()
		);

		$this->assertArrayNotHasKey( self::ITEM_KEY, $links['views'] );
	}

	public function testDoesNotAddItemForUserWhichDoesNotExist() {
		[ $links ] = $this->runHook(
			Title::makeTitle( NS_USER, 'User which does not exist' ),
			$this->getViewer()
		);

		$this->assertArrayNotHasKey( self::ITEM_KEY, $links['views'] );
	}

	public function testDoesNotAddItemOutsideUserNamespaces() {
		[ $links ] = $this->runHook(
			Title::makeTitle( NS_MAIN, 'Test page' ),
			$this->getViewer()
		);

		$this->assertArrayNotHasKey( self::ITEM_KEY, $links['views'] );
	}

	public function testDoesNotAddItemOnSpecialPageWithRelevantUser() {
		$target = $this->getTestSysop()->getUser();
		[ $links ] = $this->runHook(
			SpecialPage::getTitleFor( 'Contributions', $target->getName() ),
			$this->getViewer(),
			$target
		);

		$this->assertArrayNotHasKey(
			self::ITEM_KEY,
			$links['views'],
			'Special pages have a relevant user, but no page navigation to use'
		);
	}

	public function testUsesBlockedIconForBlockedUser() {
		$target = $this->getTestSysop()->getUser();

		$blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$blockStatusCache->method( 'isIndefinitelyBlockedOrLocked' )->willReturn( true );
		$this->setService( 'CheckUserUserInfoCardBlockStatusCache', $blockStatusCache );

		[ $links ] = $this->runHook(
			$target->getUserPage(),
			$this->getViewer()
		);

		$this->assertSame( 'userBlocked', $links['views'][self::ITEM_KEY]['icon'] );
		$this->assertStringContainsString(
			'ext-checkuser-userinfocard-navigation-item--userBlocked',
			$links['views'][self::ITEM_KEY]['class']
		);
	}

	public function testUsesTemporaryIconForTemporaryAccount() {
		$this->enableAutoCreateTempUser();
		$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();

		[ $links ] = $this->runHook(
			Title::makeTitle( NS_USER, $tempUser->getName() ),
			$this->getViewer()
		);

		$this->assertSame( 'userTemporary', $links['views'][self::ITEM_KEY]['icon'] );
		$this->assertStringContainsString(
			'ext-checkuser-userinfocard-navigation-item--userTemporary',
			$links['views'][self::ITEM_KEY]['class']
		);
	}
}
