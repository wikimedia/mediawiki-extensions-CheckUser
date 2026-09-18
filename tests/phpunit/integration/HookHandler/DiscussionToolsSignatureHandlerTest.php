<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\HookHandler\DiscussionToolsSignatureHandler;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Output\OutputPage;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\DiscussionToolsSignatureHandler
 */
class DiscussionToolsSignatureHandlerTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'DiscussionTools' );
	}

	private function getHandler( ?UserNameUtils $userNameUtils = null ): DiscussionToolsSignatureHandler {
		$services = $this->getServiceContainer();

		return new DiscussionToolsSignatureHandler(
			$userNameUtils ?? $services->getUserNameUtils(),
			$services->getService( 'CheckUserUserInfoCardButtonRenderer' )
		);
	}

	/**
	 * Make the thread item data that DiscussionTools passes for the given authors, keyed by
	 * thread item ID, together with the markup array that the hook caller pre-populates.
	 *
	 * @param string[] $authors
	 * @return array{0:string[],1:array[]} The markup array and the thread item data
	 */
	private function getHookArguments( array $authors ): array {
		$threadItemsData = [];
		foreach ( $authors as $index => $author ) {
			$itemId = 'c-' . $author . '-2025010112000' . $index;
			$threadItemsData[$itemId] = [
				'id' => $itemId,
				'author' => $author,
			];
		}

		return [ array_fill_keys( array_keys( $threadItemsData ), '' ), $threadItemsData ];
	}

	/**
	 * Make a user that has the UserInfoCard preference set as given.
	 */
	private function setUpViewingUser( bool $enabled ): User {
		$user = $this->getMutableTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user,
			Preferences::ENABLE_USER_INFO_CARD,
			$enabled
		);
		$userOptionsManager->saveOptions( $user );

		return $user;
	}

	private function makeContext( User $viewingUser ): RequestContext {
		$context = new RequestContext();
		$context->setUser( $viewingUser );
		$context->setTitle( $this->getExistingTestPage()->getTitle() );
		$context->setOutput( new OutputPage( $context ) );

		return $context;
	}

	public function testNoButtonWhenPreferenceDisabled() {
		$viewingUser = $this->setUpViewingUser( false );
		$context = $this->makeContext( $viewingUser );
		[ $markupByItemId, $threadItemsData ] = $this->getHookArguments( [ $viewingUser->getName() ] );

		$this->getHandler()->onDiscussionToolsAddSignatureUserInfoMarkup(
			$markupByItemId,
			$threadItemsData,
			$context
		);

		$this->assertSame( [ '' ], array_values( $markupByItemId ) );
		$this->assertNotContains( 'ext.checkUser.userInfoCard', $context->getOutput()->getModules() );
	}

	public function testButtonRenderedForEveryComment() {
		$userNames = [
			'User 1',
			'User 2',
			// The same author can sign more than one comment
			'User 1',
			// Let's ensure all-numeric names are processed correctly
			'12345',
			// Check temporary users getting their own icon as well
			'~1',
		];
		$icons = [
			// If not specified, default to userAvatar
			4 => 'ext-checkuser-userinfocard-button__icon--userTemporary',
		];

		$this->enableAutoCreateTempUser();
		$context = $this->makeContext( $this->setUpViewingUser( true ) );
		[ $markupByItemId, $threadItemsData ] = $this->getHookArguments( $userNames );

		$this->getHandler()->onDiscussionToolsAddSignatureUserInfoMarkup(
			$markupByItemId,
			$threadItemsData,
			$context
		);

		$this->assertSameSize( $userNames, $markupByItemId );
		foreach ( array_values( $markupByItemId ) as $index => $markup ) {
			$expectedIcon = $icons[ $index ] ?? 'ext-checkuser-userinfocard-button__icon--userAvatar';
			$this->assertStringContainsString( 'ext-checkuser-userinfocard-button', $markup );
			$this->assertStringContainsString( $expectedIcon, $markup );
			$this->assertStringContainsString( 'data-username="' . $userNames[$index] . '"', $markup );
		}

		$output = $context->getOutput();
		$this->assertContains( 'ext.checkUser.userInfoCard', $output->getModules() );
		$this->assertContains( 'ext.checkUser.styles', $output->getModuleStyles() );
	}

	public function testNoButtonForAuthorsThatAreNotUsers() {
		$context = $this->makeContext( $this->setUpViewingUser( true ) );
		[ $markupByItemId, $threadItemsData ] = $this->getHookArguments( [
			'127.0.0.1',
			'2001:db8::1',
			'127.0.0.0/24',
			'Foo|Bar',
		] );

		$this->getHandler()->onDiscussionToolsAddSignatureUserInfoMarkup(
			$markupByItemId,
			$threadItemsData,
			$context
		);

		$this->assertSame( [ '', '', '', '' ], array_values( $markupByItemId ) );
		$this->assertNotContains( 'ext.checkUser.userInfoCard', $context->getOutput()->getModules() );
	}

	public function testMixOfValidAndInvalidUsers() {
		$context = $this->makeContext( $this->setUpViewingUser( true ) );
		[ $markupByItemId, $threadItemsData ] = $this->getHookArguments( [
			'127.0.0.1',
			'Admin',
		] );

		$this->getHandler()->onDiscussionToolsAddSignatureUserInfoMarkup(
			$markupByItemId,
			$threadItemsData,
			$context
		);

		$this->assertSame( '', array_values( $markupByItemId )[0] );
		$this->assertNotSame( '', array_values( $markupByItemId )[1] );
		$this->assertContains( 'ext.checkUser.userInfoCard', $context->getOutput()->getModules() );
	}

	public function testMarkupIsAppendedNotReplaced() {
		$context = $this->makeContext( $this->setUpViewingUser( true ) );
		[ $markupByItemId, $threadItemsData ] = $this->getHookArguments( [
			$this->getTestSysop()->getUser()->getName(),
		] );
		$itemId = array_key_first( $markupByItemId );
		$markupByItemId[$itemId] = '<span class="from-another-handler"></span>';

		$this->getHandler()->onDiscussionToolsAddSignatureUserInfoMarkup(
			$markupByItemId,
			$threadItemsData,
			$context
		);

		$this->assertStringStartsWith(
			'<span class="from-another-handler"></span>',
			$markupByItemId[$itemId]
		);
		$this->assertStringContainsString(
			'ext-checkuser-userinfocard-button',
			$markupByItemId[$itemId]
		);
	}
}
