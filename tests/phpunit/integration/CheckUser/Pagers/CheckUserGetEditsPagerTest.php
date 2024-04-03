<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\User\UserIdentityValue;

/**
 * Test class for CheckUserGetEditsPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager
 */
class CheckUserGetEditsPagerTest extends CheckUserPagerCommonTest {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_changes',
				'cu_log',
			]
		);

		$this->checkSubtype = SpecialCheckUser::SUBTYPE_GET_EDITS;
		$this->defaultUserIdentity = UserIdentityValue::newAnonymous( '127.0.0.1' );
		$this->defaultCheckType = 'ipedits';
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager::isNavigationBarShown
	 * @dataProvider provideIsNavigationBarShown
	 */
	public function testIsNavigationBarShown( $results, $shown ) {
		$object = $this->setUpObject();
		$object->mResult = new \FakeResultWrapper( $results );
		$object->mQueryDone = true;
		if ( $shown ) {
			$this->assertTrue(
				$object->isNavigationBarShown(),
				'Navigation bar is not showing when it\'s supposed to'
			);
		} else {
			$this->assertFalse(
				$object->isNavigationBarShown(),
				'Navigation bar is showing when it is not supposed to'
			);
		}
	}

	public function provideIsNavigationBarShown() {
		return [
			[ [], false ],
			[ [ [ 'test' ] ], true ]
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager::preCacheMessages
	 * @dataProvider providePreCacheMessages
	 */
	public function testPreCacheMessages( $messageKeys ) {
		$object = $this->setUpObject();
		$this->assertArrayEquals(
			$messageKeys,
			array_keys( $object->message ),
			false,
			false,
			'preCacheMessage has missed or has too many message keys to cache.'
		);
		foreach ( $messageKeys as $key ) {
			$this->assertSame(
				wfMessage( $key )->escaped(),
				$object->message[$key],
				'preCacheMessage did not cache the correct message.'
			);
		}
	}

	public function providePreCacheMessages() {
		return [
			[ [ 'diff', 'hist', 'minoreditletter', 'newpageletter', 'blocklink', 'log' ] ]
		];
	}

	/**
	 * Tests that the template parameters provided to the GetEditsLine.mustache match
	 * the expected values. Does not test the mustache file which includes some
	 * conditional logic, HTML and whitespace.
	 *
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager::formatRow
	 * @dataProvider provideFormatRow
	 */
	public function testFormatRow( $row, $flagCache, $expectedTemplateParams ) {
		$object = $this->setUpObject();
		$object->templateParser = new TemplateParserMockTest();
		$row = array_merge( $this->getDefaultRowFieldValues(), $row );
		$object->flagCache = $flagCache;
		$object->formatRow( (object)$row );
		$this->assertNotNull(
			$object->templateParser->lastCalledWith,
			'The template parser was not called by formatRow.'
		);
		$this->assertSame(
			'GetEditsLine',
			$object->templateParser->lastCalledWith[0],
			'formatRow did not call the correct mustache file.'
		);
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$object->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			$expectedTemplateParams,
			'The template parameters do not match the expected template parameters. If changes have been
			made to the template parameters make sure you update the tests.'
		);
	}

	public function testFormatRowWhenTitleIsHiddenUser() {
		// Get a user which has been blocked with the 'hideuser' enabled.
		$hiddenUser = $this->getTestUser()->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser,
				$this->getTestUser( [ 'suppress', 'sysop' ] )->getAuthority(),
				'infinity',
				'block to hide the test user',
				[ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// Test that when the title is the username of a hidden user, the 'logs' link is not set (as this uses the
		// the title for the row).
		$this->testFormatRow(
			[
				'cuc_actiontext' => 'test',
				'cuc_namespace' => NS_USER,
				'cuc_title' => $hiddenUser->getUserPage()->getText(),
				'cuc_user_text' => $hiddenUser->getName(),
				'cuc_user' => $hiddenUser->getId(),
			],
			[ $hiddenUser->getName() => '' ],
			[ 'showLinks' => false ]
		);
	}

	public static function provideFormatRow() {
		// @todo test the rest of the template parameters.
		return [
			'Test agent' => [
				[ 'cuc_agent' => 'Testing' ],
				[ '127.0.0.1' => '' ],
				[ 'userAgent' => 'Testing' ]
			],
			'Test user link class' => [
				[ 'cuc_user' => 0, 'cuc_user_text' => 'Non existent user 1234' ],
				[ 'Non existent user 1234' => '' ],
				[ 'userLinkClass' => 'mw-checkuser-nonexistent-user' ]
			],
		];
	}

	/** @inheritDoc */
	public function getDefaultRowFieldValues(): array {
		return [
			'cuc_namespace' => 0,
			'cuc_title' => '',
			'cuc_user' => 0,
			'cuc_user_text' => '127.0.0.1',
			'cuc_actor' => 0,
			'cuc_actiontext' => '',
			'cuc_comment' => '',
			'cuc_comment_id' => 0,
			'cuc_minor' => 0,
			'cuc_page_id' => 0,
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type' => RC_LOG,
			'cuc_timestamp' => $this->db->timestamp(),
			'cuc_ip' => '127.0.0.1',
			'cuc_xff' => '',
			'cuc_agent' => '',
		];
	}
}
