<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter
 * @group CheckUser
 * @group Database
 */
class TimelineRowFormatterTest extends MediaWikiIntegrationTestCase {

	private function getObjectUnderTest( User $user = null ): TimelineRowFormatter {
		// Generate a testing user if no user was defined
		$user ??= $this->getTestUser()->getUser();
		return $this->getServiceContainer()->get( 'CheckUserTimelineRowFormatterFactory' )
			->createRowFormatter(
				$user,
				// Use qqx language to make testing easier.
				$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )
			);
	}

	/** @dataProvider provideGetFormattedRowItems */
	public function testGetFormattedRowItems( $row, $expectedArraySubmap ) {
		// Tests a subset of the items in the array returned by ::getFormattedRowItems
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge( $this->getDefaultsForTimelineRow(), $row );
		$this->assertArraySubmapSame(
			$expectedArraySubmap,
			$objectUnderTest->getFormattedRowItems( (object)$row ),
			'Array returned by ::getFormattedRowItems was not as expected.'
		);
	}

	public static function provideGetFormattedRowItems() {
		return [
			'Edit performed by IPv4' => [
				[ 'cuc_ip' => '127.0.0.1', 'cuc_agent' => 'Test' ],
				[
					'links' => [
						// No flags should be displayed if the action didn't create a page and wasn't marked as minor.
						'minorFlag' => '', 'newPageFlag' => '',
						// No log links if action is an edit. diffLinks, historyLinks etc. will be tested separately.
						'logLink' => '',
					],
					'info' => [
						'userAgent' => 'Test',
						'ipInfo' => '127.0.0.1',
						// No action text if the action is an edit.
						'actionText' => '',
					],
				],
			],
			'Log performed by IPv6' => [
				[
					'cuc_ip' => '2001:DB8::1', 'cuc_actiontext' => 'test action text',
					'cuc_agent' => 'Test', 'cuc_type' => RC_LOG,
				],
				[
					// All edit-specific links / flags should be empty for a log action.
					'links' => [ 'minorFlag' => '', 'newPageFlag' => '', 'historyLink' => '', 'diffLink' => '', ],
					'info' => [
						'userAgent' => 'Test',
						// The IP address should be in lower-case with shortened form.
						'ipInfo' => '2001:db8::1',
						'actionText' => 'test action text',
						// Title will not be defined for log events, because it is already used for the logs link.
						'title' => '',
					],
				],
			],
			'Edit with invalid title' => [
				// cuc_title should be a string, but using 0 is a way to test invalid title.
				[ 'cuc_title' => 0, 'cuc_namespace' => 0 ],
				[ 'links' => [ 'historyLink' => '', 'diffLink' => '' ], 'info' => [ 'title' => '' ] ],
			],
			'Log with invalid title' => [
				[ 'cuc_title' => 0, 'cuc_namespace' => 0, 'cuc_type' => RC_LOG ], [ 'links' => [ 'logLink' => '' ] ],
			],
			'Edit marked as a minor edit and created a page' => [
				[ 'cuc_minor' => 1, 'cuc_type' => RC_NEW ],
				[ 'links' => [
					'minorFlag' => '<span class="minor">(minoreditletter)</span>',
					'newPageFlag' => '<span class="newpage">(newpageletter)</span>',
				] ],
			],
		];
	}

	public function testGetTime() {
		$testUser = $this->getTestUser()->getUser();
		$objectUnderTest = $this->getObjectUnderTest();
		$row = $this->getDefaultsForTimelineRow();
		$expectedTime = $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )
			->userTime( '20210405060708', $testUser );
		$this->assertArraySubmapSame(
			[ 'info' => [ 'time' => $expectedTime ] ],
			$objectUnderTest->getFormattedRowItems( (object)$row ),
			'Array returned by ::getFormattedRowItems was not as expected.'
		);
	}

	/** @dataProvider provideCucTypeValues */
	public function testWhenTitleDefined( $rowType ) {
		// Get a test page
		$testPage = $this->getExistingTestPage()->getTitle();
		// Get the object under test and the row.
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[
				'cuc_namespace' => $testPage->getNamespace(), 'cuc_title' => $testPage->getText(),
				'cuc_page_id' => $testPage->getArticleID(), 'cuc_this_oldid' => $testPage->getLatestRevID(),
				'cuc_last_oldid' => 0, 'cuc_type' => $rowType,
			]
		);
		// Assert that the userLinks contain the rev-deleted-user message and not the username,
		// as the user is blocked with 'hideuser' and the current authority cannot see hidden users.
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		// Assertions that are specific to RC_EDIT types.
		if ( $rowType === RC_EDIT ) {
			$this->assertStringContainsString(
				$testPage->getLatestRevID(),
				$actualTimelineFormattedRowItems['links']['diffLink'],
				'The diffLink is not as expected in the links array.'
			);
			$this->assertStringContainsString(
				$testPage->getArticleID(),
				$actualTimelineFormattedRowItems['links']['historyLink'],
				'The historyLink is not as expected in the links array.'
			);
		}
		if ( $rowType !== RC_LOG ) {
			// Assertions that apply to all types except RC_LOG.
			$this->assertStringContainsString(
				'ext-checkuser-investigate-timeline-row-title',
				$actualTimelineFormattedRowItems['info']['title'],
				'The title is not as expected in the info array.'
			);
			$this->assertStringContainsString(
				$testPage->getText(),
				$actualTimelineFormattedRowItems['info']['title'],
				'The title is not as expected in the info array.'
			);
		} else {
			// Assertions that apply to RC_LOG types.
			$this->assertStringContainsString(
				wfUrlencode( $testPage->getText() ),
				$actualTimelineFormattedRowItems['links']['logLink'],
				'The logLink is not as expected in the links array.'
			);
		}
	}

	/** @dataProvider provideCucTypeValues */
	public function testTitleAsHiddenUser( $rowType ) {
		// Create a test user which is blocked with 'hideuser' enabled.
		$hiddenUser = $this->getTestUser()->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser,
				$this->getTestUser( [ 'suppress', 'sysop' ] )->getAuthority(),
				'infinity', 'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// ::testGetFormattedRowItems uses a test user which cannot see users which are hidden.
		$this->testGetFormattedRowItems(
			[ 'cuc_title' => $hiddenUser->getName(), 'cuc_namespace' => NS_USER, 'cuc_type' => $rowType ],
			[ 'links' => [ 'historyLink' => '', 'diffLink' => '', 'logLink' => '' ], 'info' => [ 'title' => '' ] ]
		);
	}

	public static function provideCucTypeValues() {
		return [
			'Edit' => [ RC_EDIT ],
			'Page creation' => [ RC_NEW ],
			'Log' => [ RC_LOG ],
		];
	}

	public function testHiddenUserAsPerformer() {
		// Create a test user which is blocked with 'hideuser' enabled.
		$hiddenUser = $this->getTestUser()->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser,
				$this->getTestUser( [ 'suppress', 'sysop' ] )->getAuthority(),
				'infinity', 'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// Get the object under test and the row.
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'cuc_user_text' => $hiddenUser->getName(), 'cuc_type' => RC_EDIT ]
		);
		// Assert that the userLinks contain the rev-deleted-user message and not the username,
		// as the user is blocked with 'hideuser' and the current authority cannot see hidden users.
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			'rev-deleted-user',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username and instead show the rev-deleted-user message.'
		);
		$this->assertStringNotContainsString(
			$hiddenUser->getName(),
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username if it is hidden from the current user.'
		);
	}

	public function testHiddenPerformerAndCommentForEdit() {
		$testUser = $this->getTestUser()->getUser();
		// Make an edit using this $testUser and then hide the performer on the edit by deleting the page.
		$testPage = $this->getNonexistingTestPage();
		$pageEditStatus = $this->editPage( $testPage, 'Testing1233', 'Test1233', NS_MAIN, $testUser );
		$this->assertTrue( $pageEditStatus->wasRevisionCreated() );
		$revId = $pageEditStatus->getNewRevision()->getId();
		// Set the rev_deleted field to hide the user and comment for the revision that was just created.
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'revision' )
			->set( [ 'rev_deleted' => RevisionRecord::DELETED_USER | RevisionRecord::DELETED_COMMENT ] )
			->where( [ 'rev_id' => $revId ] )
			->execute();
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'cuc_user_text' => $testUser->getName(), 'cuc_type' => RC_EDIT, 'cuc_this_oldid' => $revId ]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			'rev-deleted-user',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username and instead show the rev-deleted-user message.'
		);
		$this->assertStringNotContainsString(
			$testUser->getName(),
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username if the performer is hidden for the edit.'
		);
		$this->assertStringContainsString(
			'rev-deleted-comment',
			$actualTimelineFormattedRowItems['info']['comment'],
			'The comment should be hidden and instead be the rev-deleted-message if the comment.'
		);
	}

	public function testGetUserLinksForUser() {
		$testUser = $this->getTestUser()->getUser();
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'cuc_user_text' => $testUser->getName(), 'cuc_user' => $testUser->getId(), 'cuc_type' => RC_EDIT ]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			$testUser->getName(),
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should display the username in the userLinks.'
		);
	}

	public function testGetUserLinksForIP() {
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'cuc_user_text' => '127.0.0.1', 'cuc_type' => RC_EDIT ]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			'127.0.0.1',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should display the username in the userLinks.'
		);
	}

	private function getDefaultsForTimelineRow() {
		return [
			'cuc_namespace' => 0, 'cuc_title' => 'Test', 'cuc_actiontext' => '', 'cuc_timestamp' => '20210405060708',
			'cuc_minor' => 0, 'cuc_page_id' => 0, 'cuc_type' => RC_EDIT, 'cuc_this_oldid' => 0, 'cuc_last_oldid' => 0,
			'cuc_ip' => '127.0.0.1', 'cuc_xff' => '', 'cuc_agent' => '', 'cuc_id' => 0, 'cuc_user' => 0,
			'cuc_user_text' => '',
		];
	}
}
