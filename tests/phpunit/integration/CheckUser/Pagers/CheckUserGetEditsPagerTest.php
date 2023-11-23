<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use LogEntryBase;
use LogFormatter;
use LogPage;
use ManualLogEntry;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\ClientHints\ClientHintsBatchFormatterResults;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\TemplateParserMockTest;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;

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
		$formattedClientHintsData, $expectedTemplateParams,
		$eventTablesMigrationStage, $displayClientHints
	) {
		$this->setMwGlobals( [
			'wgCheckUserEventTablesMigrationStage' => $eventTablesMigrationStage,
			'wgCheckUserDisplayClientHints' => $displayClientHints,
		] );
		$object = $this->setUpObject();
		$object->templateParser = new TemplateParserMockTest();
		$row = array_merge( $this->getDefaultRowFieldValues(), $row );
		$object->flagCache = $flagCache;
		$object->usernameVisibility = $usernameVisibility;
		$object->formattedRevisionComments = $formattedRevisionComments;
		if ( $formattedClientHintsData !== null ) {
			$object->formattedClientHintsData = $formattedClientHintsData;
		}
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
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
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
				'log_deleted' => 0,
				'user_text' => $deleteLogEntry->getPerformerIdentity()->getName(),
				'user' => $deleteLogEntry->getPerformerIdentity()->getId(),
				'client_hints_reference_id' => 1,
				'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			],
			[ $deleteLogEntry->getPerformerIdentity()->getName() => '' ],
			[ $deleteLogEntry->getPerformerIdentity()->getId() => true ],
			[],
			new ClientHintsBatchFormatterResults( [ 0 => [ 1 => 0 ] ], [ 0 => 'Test Client Hints data' ] ),
			[
				'actionText' => LogFormatter::newFromEntry( $deleteLogEntry )->getActionText(),
				'clientHints' => 'Test Client Hints data',
			],
			SCHEMA_COMPAT_NEW,
			true,
		);
	}

	public function testFormatRowLogNotFromCuChangesWhenReadingNewWithDeletedActionText() {
		$deleteLogEntry = new ManualLogEntry( 'delete', 'delete' );
		$deleteLogEntry->setPerformer( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		$deleteLogEntry->setTarget( Title::newFromText( 'Testing page' ) );
		$deleteLogEntry->setDeleted( LogPage::DELETED_ACTION );
		$logFormatter = LogFormatter::newFromEntry( $deleteLogEntry );
		$logFormatter->setAudience( LogFormatter::FOR_THIS_USER );
		$this->testFormatRow(
			[
				'log_type' => $deleteLogEntry->getType(),
				'log_action' => $deleteLogEntry->getSubtype(),
				'title' => $deleteLogEntry->getTarget()->getText(),
				'log_deleted' => LogPage::DELETED_ACTION,
				'user_text' => $deleteLogEntry->getPerformerIdentity()->getName(),
				'user' => $deleteLogEntry->getPerformerIdentity()->getId(),
				'client_hints_reference_id' => 1,
				'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			],
			[ $deleteLogEntry->getPerformerIdentity()->getName() => '' ],
			[ $deleteLogEntry->getPerformerIdentity()->getId() => true ],
			[],
			new ClientHintsBatchFormatterResults( [], [] ),
			[
				'actionText' => $logFormatter->getActionText()
			],
			SCHEMA_COMPAT_NEW,
			false,
		);
	}

	public function testFormatRowLogFromUnnormalisedIPv6() {
		$user_text = '2A02:EC80:101:0:0:0:2:8';
		$ip = '2a02:ec80:101::2:8';

		$normalisedIP = IPUtils::prettifyIP( $user_text ) ?? $user_text;
		$wrapper = $this->setUpObject();

		$this->testFormatRow(
			[
				'user_text' => $user_text,
				'ip' => $ip,
			],
			[ $user_text => '' ],
			[],
			[],
			null,
			[
				'userLink' => Linker::userLink( 0, $normalisedIP, $normalisedIP ),
				'ipLink' => $wrapper->getSelfLink( $normalisedIP,
					[
						'user' => $normalisedIP,
						'reason' => ''
					]
				)
			],
			SCHEMA_COMPAT_NEW,
			false,
		);
	}

	/** @dataProvider provideFormatRowLogNotFromCuChangesWhenReadingNewWithLogParameters */
	public function testFormatRowLogNotFromCuChangesWhenReadingNewWithLogParameters(
		$logParametersAsArray, $logParametersAsBlob
	) {
		$moveLogEntry = new ManualLogEntry( 'move', 'move' );
		$moveLogEntry->setPerformer( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		$moveLogEntry->setTarget( Title::newFromText( 'Testing page' ) );
		$moveLogEntry->setParameters( $logParametersAsArray );
		$this->testFormatRow(
			[
				'log_type' => $moveLogEntry->getType(),
				'log_action' => $moveLogEntry->getSubtype(),
				'log_deleted' => 0,
				'title' => $moveLogEntry->getTarget()->getText(),
				'user_text' => $moveLogEntry->getPerformerIdentity()->getName(),
				'user' => $moveLogEntry->getPerformerIdentity()->getId(),
				'log_params' => $logParametersAsBlob,
				'client_hints_reference_id' => 1,
				'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			],
			[ $moveLogEntry->getPerformerIdentity()->getName() => '' ],
			[ $moveLogEntry->getPerformerIdentity()->getId() => true ],
			[],
			new ClientHintsBatchFormatterResults( [ 0 => [ 1 => 0 ] ], [ 0 => 'Test Client Hints data' ] ),
			[
				'actionText' => LogFormatter::newFromEntry( $moveLogEntry )->getActionText(),
				'clientHints' => 'Test Client Hints data',
			],
			SCHEMA_COMPAT_NEW,
			true,
		);
	}

	public static function provideFormatRowLogNotFromCuChangesWhenReadingNewWithLogParameters() {
		return [
			'Legacy log parameters' => [
				[
					'4::target' => 'Testing',
					'5::noredir' => '0'
				],
				LogPage::makeParamBlob( [
					'4::target' => 'Testing',
					'5::noredir' => '0'
				] ),
			],
			'Normal log parameters' => [
				[
					'4::target' => 'Testing',
					'5::noredir' => '0'
				],
				LogEntryBase::makeParamBlob( [
					'4::target' => 'Testing',
					'5::noredir' => '0'
				] ),
			]
		];
	}

	public static function provideFormatRow() {
		// @todo test the rest of the template parameters.
		return [
			'Test user agent on log when reading old' => [
				// $row as an array
				[ 'agent' => 'Testing', 'actiontext' => 'Test' ],
				// The $object->flagCache
				[ '127.0.0.1' => '' ],
				// The $object->usernameVisibility
				[ 0 => true ],
				// The $object->formattedRevisionComments
				[],
				// The $object->formattedClientHintsData
				null,
				// The expected template parameters
				[ 'userAgent' => 'Testing', 'actionText' => 'Test' ],
				// Event table migration stage
				SCHEMA_COMPAT_OLD,
				// Whether Client Hints are enabled
				false
			],
			'Test user agent on log from cu_changes when reading new' => [
				[ 'agent' => 'Testing', 'actiontext' => 'Test' ],
				[ '127.0.0.1' => '' ],
				[ 0 => true ],
				[],
				null,
				[ 'userAgent' => 'Testing', 'actionText' => 'Test' ],
				SCHEMA_COMPAT_NEW,
				false
			],
			'Test non-existent user has appropriate CSS class when reading old' => [
				[ 'user' => 0, 'user_text' => 'Non existent user 1234' ],
				[ 'Non existent user 1234' => '' ],
				[ 0 => true ],
				[],
				null,
				[ 'userLinkClass' => 'mw-checkuser-nonexistent-user' ],
				SCHEMA_COMPAT_OLD,
				false
			],
			'Testing using a user that is hidden who made an edit and reading new' => [
				[ 'user' => 10, 'user_text' => 'User1234', 'type' => RC_EDIT ],
				[],
				[ 0 => false ],
				[ 0 => 'Test' ],
				null,
				[ 'comment' => 'Test' ],
				SCHEMA_COMPAT_NEW,
				false
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
