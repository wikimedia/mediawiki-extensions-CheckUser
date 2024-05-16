<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetActionsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\Html\FormOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRigorOptions;
use RequestContext;
use SpecialPageTestBase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for SpecialCheckUser class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser
 */
class SpecialCheckUserTest extends SpecialPageTestBase {

	use MockAuthorityTrait;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
	}

	/**
	 * Gets a test user with the checkuser group and also assigns that user as the user for the main request context.
	 *
	 * @return User
	 */
	private function getTestCheckUser(): User {
		$testCheckUser = $this->getTestUser( [ 'checkuser' ] )->getUser();
		RequestContext::getMain()->setUser( $testCheckUser );
		return $testCheckUser;
	}

	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		$this->getTestCheckUser();
		$object = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
		$testingWrapper = TestingAccessWrapper::newFromObject( $object );
		$testingWrapper->opts = new FormOptions();
		return $testingWrapper;
	}

	/** @dataProvider provideGetPager */
	public function testGetPager( $checkType, $userIdentity, $xfor = null ) {
		$object = $this->setUpObject();
		$object->opts->add( 'limit', 0 );
		$object->opts->add( 'reason', '' );
		$object->opts->add( 'period', 0 );
		if ( $checkType === SpecialCheckUser::SUBTYPE_GET_IPS ) {
			$this->assertTrue(
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor )
				instanceof CheckUserGetIPsPager,
				'The Get IPs checktype should return the Get IPs pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_ACTIONS ) {
			$this->assertTrue(
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor )
				instanceof CheckUserGetActionsPager,
				'The Get actions checktype should return the Get actions pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_USERS ) {
			$this->assertTrue(
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor )
				instanceof CheckUserGetUsersPager,
				'The Get users checktype should return the Get users pager.'
			);
		} else {
			$this->assertNull(
				$object->getPager( $checkType, $userIdentity, 'untested' ),
				'An unrecognised check type should return no pager.'
			);
		}
	}

	public static function provideGetPager() {
		return [
			'Get IPs checktype' =>
				[ SpecialCheckUser::SUBTYPE_GET_IPS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get actions checktype with a registered user' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get actions checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get actions checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'Get users checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get users checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'An invalid checktype' => [ '', UserIdentityValue::newRegistered( 1, 'test' ) ],
		];
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$checkUserLog = $this->getServiceContainer()->getSpecialPageFactory()
			->getPage( 'CheckUser' );
		if ( $checkUserLog === null ) {
			$this->fail( 'CheckUser special page does not exist' );
		}
		$requiredRight = $checkUserLog->getRestriction();
		if ( !is_array( $groups ) ) {
			$groups = [ $groups ];
		}
		$rightsGivenInGroups = $this->getServiceContainer()->getGroupPermissionsLookup()
			->getGroupPermissions( $groups );
		if ( $allowed ) {
			$this->assertContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should allow it to access CheckUser.'
			);
		} else {
			$this->assertNotContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should not include access to CheckUser.'
			);
		}
	}

	public static function provideRequiredGroupAccess() {
		return [
			'No user groups' => [ '', false ],
			'Checkuser only' => [ 'checkuser', true ],
			'Checkuser and sysop' => [ [ 'checkuser', 'sysop' ], true ],
		];
	}

	/**
	 * @dataProvider provideRequiredRights
	 */
	public function testRequiredRights( $groups, $allowed ) {
		if ( ( is_array( $groups ) && isset( $groups['checkuser-log'] ) ) || $groups === "checkuser-log" ) {
			$this->setGroupPermissions(
				[ 'checkuser-log' => [ 'checkuser-log' => true, 'read' => true ] ]
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public static function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser right only' => [ 'checkuser', true ],
		];
	}

	/** @dataProvider provideTagPage */
	public function testTagPage( $tag, $summary, $pageExists ) {
		$object = $this->setUpObject();
		if ( $pageExists ) {
			$page = $this->getExistingTestPage();
		} else {
			$page = $this->getNonexistingTestPage();
		}
		$tagPageResult = $object->tagPage( $page->getTitle(), $tag, $summary );
		$page = $this->getExistingTestPage( $page->getTitle() );
		$this->assertInstanceOf(
			Status::class,
			$tagPageResult,
			'Result of ::tagPage should be a Status object.'
		);
		$this->assertStatusGood(
			$tagPageResult,
			'::tagPage should have returned a good Status.'
		);
		$this->assertTrue(
			$page->exists(),
			'Page should now exist as the page should have been tagged.'
		);
		$this->assertSame(
			$page->getRevisionRecord()->getComment()->text,
			$summary,
			'The summary used to add the tag was not correct.'
		);
		$this->assertSame(
			$page->getRevisionRecord()->getContentOrThrow( SlotRecord::MAIN )->getWikitextForTransclusion(),
			$tag,
			'Page was not tagged with the correct text.'
		);
	}

	public static function provideTagPage() {
		return [
			'Tagging existing page' => [
				'Test tag1234 {{testing}}', 'Summary [[test]]', true
			],
			'Tagging non-existing page' => [
				'Testing {{test}}', 'Test summary1234', false
			]
		];
	}

	public function testDoMassUserBlockInternal() {
		$blockParams = [
			'reason' => 'Test reason',
			'email' => true,
			'talk' => false,
			'reblock' => true,
		];
		$users = [
			$this->getMutableTestUser()->getUserIdentity()->getName(),
			$this->getMutableTestUser()->getUserIdentity()->getName(),
			'1.2.3.4'
		];
		$this->commonTestDoMassUserBlockInternal(
			$users, $blockParams, true, 'Test tag', true, 'Test talk tag',
			$users, $users
		);
	}

	public function commonTestDoMassUserBlockInternal(
		$users, $blockParams, $useTag, $tag, $useTalkTag, $talkTag, $expectedTaggedUsers, $expectedBlockedUsers
	) {
		$object = $this->setUpObject();
		RequestContext::getMain()->setUser( $this->getTestUser( [ 'checkuser', 'sysop' ] )->getUser() );
		$massBlockResult = $object->doMassUserBlockInternal(
			$users, $blockParams, $useTag, $tag, $useTalkTag, $talkTag
		);

		// Convert expected users into wikilinks to those users
		// to match the output of ::doMassUserBlockInternal
		$expectedTaggedUsersWikiTextFormat = array_map( static function ( $item ) {
			return '[[User:' . $item . '|' . $item . ']]';
		}, $expectedTaggedUsers );
		$expectedBlockedUsersWikiTextFormat = array_map( static function ( $item ) {
			return '[[User:' . $item . '|' . $item . ']]';
		}, $expectedBlockedUsers );

		// Check that the returned arrays are as expected.
		$this->assertArrayEquals(
			$expectedBlockedUsersWikiTextFormat,
			$massBlockResult[0],
			false,
			false,
			'::doMassUserBlockInternal did not return the expected users that were blocked.'
		);
		$this->assertArrayEquals(
			$expectedTaggedUsersWikiTextFormat,
			$massBlockResult[1],
			false,
			false,
			'::doMassUserBlockInternal did not return the expected users that were tagged.'
		);

		// Check that users are actually blocked
		foreach ( $expectedBlockedUsers as $user ) {
			$this->assertNotNull(
				MediaWikiServices::getInstance()->getUserFactory()
					->newFromName( $user, UserRigorOptions::RIGOR_NONE )->getBlock(),
				'User should be blocked as ::doMassUserBlockInternal said the user was blocked.'
			);
		}

		// Check that the users were tagged
		foreach ( $expectedTaggedUsers as $user ) {
			if ( $useTag ) {
				$userPage = $this->getServiceContainer()->getWikiPageFactory()
					->newFromTitle( Title::newFromText( $user, NS_USER ) );
				$this->assertTrue(
					$userPage->exists(),
					'User page should exist as it was tagged.'
				);
				$this->assertSame(
					$userPage->getRevisionRecord()->getContentOrThrow( SlotRecord::MAIN )
						->getWikitextForTransclusion(),
					$tag,
					'User page was not tagged correctly.'
				);
			}
			if ( $useTalkTag ) {
				$userTalkPage = $this->getServiceContainer()->getWikiPageFactory()
					->newFromTitle( Title::newFromText( $user, NS_USER_TALK ) );
				$this->assertTrue(
					$userTalkPage->exists(),
					'User talk page should exist as it was tagged.'
				);
				$this->assertSame(
					$userTalkPage->getRevisionRecord()->getContentOrThrow( SlotRecord::MAIN )
						->getWikitextForTransclusion(),
					$talkTag,
					'User talk page was not tagged correctly.'
				);
			}
		}
	}

	public function testLoadSpecialPageBeforeFormSubmission() {
		// Execute the special page. We need the full HTML to verify the subtitle links.
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestCheckUser(), true );
		// Assert that the "Try out Special:Investigate" link is present
		$this->assertStringContainsString( '(checkuser-link-investigate-label', $html );
		// Assert that the normal subtitle links are present (those without a specific target)
		$this->assertStringContainsString( '(checkuser-show-investigate', $html );
		$this->assertStringContainsString( '(checkuser-showlog', $html );
		// Verify that the summary text is present
		$this->assertStringContainsString( '(checkuser-summary', $html );
		// Verify that the form fields that are expected are present.
		$this->assertStringContainsString( '(checkuser-target', $html );
		$this->assertStringContainsString( '(checkuser-period', $html );
		$this->assertStringContainsString( '(checkuser-reason', $html );
		$this->assertStringContainsString( '(checkuser-ips', $html );
		$this->assertStringContainsString( '(checkuser-actions', $html );
		$this->assertStringContainsString( '(checkuser-users', $html );
		// Verify that the submit button is present
		$this->assertStringContainsString( '(checkuser-check', $html );
		// Verify that the CIDR calculator is present
		$this->assertStringContainsString( '(checkuser-cidr-label', $html );
	}
}
