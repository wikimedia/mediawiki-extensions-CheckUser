<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use LogFormatter;
use ManualLogEntry;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\Tests\TemplateParserMockTest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
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

	/** @dataProvider providePreCacheMessages */
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

	public static function providePreCacheMessages() {
		return [
			'All message keys to be cached' => [ [
				'diff', 'hist', 'minoreditletter', 'newpageletter',
				'blocklink', 'checkuser-log-link-text', 'checkuser-logs-link-text'
			] ]
		];
	}

	/**
	 * Tests that the template parameters provided to the GetEditsLine.mustache match
	 * the expected values. Does not test the mustache file which includes some
	 * conditional logic, HTML and whitespace.
	 *
	 * @dataProvider provideFormatRow
	 */
	public function testFormatRow(
		$row, $flagCache, $usernameVisibility, $formattedRevisionComments,
		$expectedTemplateParams, $eventTablesMigrationStage
	) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTablesMigrationStage );
		$object = $this->setUpObject();
		$object->templateParser = new TemplateParserMockTest();
		$row = array_merge( $this->getDefaultRowFieldValues(), $row );
		$object->flagCache = $flagCache;
		$object->usernameVisibility = $usernameVisibility;
		$object->formattedRevisionComments = $formattedRevisionComments;
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

	public function testFormatRowLogNotFromCuChangesWhenReadingNew() {
		$deleteLogEntry = new ManualLogEntry( 'delete', 'delete' );
		$deleteLogEntry->setPerformer( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		$deleteLogEntry->setTarget( Title::newFromText( 'Testing page' ) );
		$this->testFormatRow(
			[
				'log_type' => $deleteLogEntry->getType(),
				'log_action' => $deleteLogEntry->getSubtype(),
				'title' => $deleteLogEntry->getTarget()->getText(),
				'user_text' => $deleteLogEntry->getPerformerIdentity()->getName(),
				'user' => $deleteLogEntry->getPerformerIdentity()->getId(),
			],
			[ $deleteLogEntry->getPerformerIdentity()->getName() => '' ],
			[ $deleteLogEntry->getPerformerIdentity()->getId() => true ],
			[],
			[ 'actionText' => LogFormatter::newFromEntry( $deleteLogEntry )->getActionText() ],
			SCHEMA_COMPAT_NEW
		);
	}

	public static function provideFormatRow() {
		// @todo test the rest of the template parameters.
		return [
			'Test user agent on log when reading old' => [
				[ 'agent' => 'Testing', 'actiontext' => 'Test' ],
				[ '127.0.0.1' => '' ],
				[ 0 => true ],
				[],
				[ 'userAgent' => 'Testing', 'actionText' => 'Test' ],
				SCHEMA_COMPAT_OLD
			],
			'Test user agent on log from cu_changes when reading new' => [
				[ 'agent' => 'Testing', 'actiontext' => 'Test' ],
				[ '127.0.0.1' => '' ],
				[ 0 => true ],
				[],
				[ 'userAgent' => 'Testing', 'actionText' => 'Test' ],
				SCHEMA_COMPAT_NEW
			],
			'Test non-existent user has appropriate CSS class when reading old' => [
				[ 'user' => 0, 'user_text' => 'Non existent user 1234' ],
				[ 'Non existent user 1234' => '' ],
				[ 0 => true ],
				[],
				[ 'userLinkClass' => 'mw-checkuser-nonexistent-user' ],
				SCHEMA_COMPAT_OLD
			],
			'Testing using a user that is hidden who made an edit and reading new' => [
				[ 'user' => 10, 'user_text' => 'User1234', 'type' => RC_EDIT ],
				[],
				[ 0 => false ],
				[ 0 => 'Test' ],
				[ 'comment' => 'Test' ],
				SCHEMA_COMPAT_NEW
			],
		];
	}

	/** @inheritDoc */
	public function getDefaultRowFieldValues(): array {
		$fieldValues = [
			'namespace' => 0,
			'title' => '',
			'user' => 0,
			'user_text' => '127.0.0.1',
			'actor' => 0,
			'actiontext' => '',
			'minor' => 0,
			'page_id' => 0,
			'this_oldid' => 0,
			'last_oldid' => 0,
			'type' => RC_LOG,
			'timestamp' => $this->db->timestamp(),
			'ip' => '127.0.0.1',
			'xff' => '',
			'agent' => '',
			'comment_id' => 0,
			'comment_text' => '',
			'comment_data' => null,
			'comment_cid' => 0,
		];
		$eventTableMigrationStage = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_READ_NEW ) {
			$fieldValues = array_merge( $fieldValues, [
				'comment_id' => 0,
				'comment_text' => '',
				'comment_data' => null,
				'comment_cid' => 0,
				'log_id' => 0,
				'log_type' => '',
				'log_action' => '',
				'log_params' => null,
			] );
		}
		return $fieldValues;
	}
}
