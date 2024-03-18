<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use CannotCreateActorException;
use DatabaseLogEntry;
use Language;
use LogEntryBase;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Services\CheckUserInsert
 */
class CheckUserInsertTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use TempUserTestTrait;

	private function setUpObject(): CheckUserInsert {
		return MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
	}

	/** @dataProvider provideInsertIntoCuChangesTable */
	public function testInsertIntoCuChangesTable(
		array $row, array $fields, array $expectedRow, $checkUserInsert = null
	) {
		ConvertibleTimestamp::setFakeTime( '20240506070809' );
		$checkUserInsert ??= $this->setUpObject();
		$checkUserInsert->insertIntoCuChangesTable( $row, __METHOD__, $this->getTestUser()->getUserIdentity() );
		$this->assertSelect(
			'cu_changes',
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	public static function provideInsertIntoCuChangesTable() {
		return [
			'Default values on empty row' => [
				[],
				[
					'cuc_ip', 'cuc_ip_hex', 'cuc_xff', 'cuc_xff_hex', 'cuc_page_id',
					'cuc_namespace', 'cuc_minor', 'cuc_title', 'cuc_actiontext',
					'cuc_this_oldid', 'cuc_last_oldid', 'cuc_type', 'cuc_agent',
					'cuc_timestamp'
				],
				[ '127.0.0.1', '7F000001', '', null, 0, NS_MAIN, 0, '', '', 0, 0, RC_LOG, '', '20240506070809' ]
			],
		];
	}

	/** @dataProvider provideInsertIntoCuPrivateEventTable */
	public function testInsertIntoCuPrivateEventTable(
		array $row, array $fields, array $expectedRow, $checkUserInsert = null
	) {
		ConvertibleTimestamp::setFakeTime( '20240506070809' );
		$checkUserInsert ??= $this->setUpObject();
		$checkUserInsert->insertIntoCuPrivateEventTable(
			$row, __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$this->assertSelect(
			'cu_private_event',
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	public static function provideInsertIntoCuPrivateEventTable() {
		return [
			'Default values on empty row' => [
				[],
				[
					'cupe_ip', 'cupe_ip_hex', 'cupe_xff', 'cupe_xff_hex', 'cupe_page',
					'cupe_namespace', 'cupe_log_type', 'cupe_log_action',
					'cupe_title', 'cupe_params', 'cupe_agent', 'cupe_timestamp'
				],
				[
					'127.0.0.1', '7F000001', '', null, 0, NS_MAIN, 'checkuser-private-event',
					'', '', LogEntryBase::makeParamBlob( [] ), '', '20240506070809'
				]
			]
		];
	}

	/** @dataProvider provideInsertIntoCuLogEventTable */
	public function testInsertIntoCuLogEventTable( array $fields, array $expectedRow, $checkUserInsert = null ) {
		ConvertibleTimestamp::setFakeTime( '20240506070809' );
		$logId = $this->newLogEntry();
		// Delete any entries that were created by ::newLogEntry.
		$this->truncateTables( [
			'cu_log_event',
		] );
		$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );

		$checkUserInsert ??= $this->setUpObject();
		$checkUserInsert->insertIntoCuLogEventTable(
			$logEntry, __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$this->assertSelect(
			'cu_log_event',
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	public static function provideInsertIntoCuLogEventTable() {
		return [
			'Default values' => [
				[ 'cule_ip', 'cule_ip_hex', 'cule_xff', 'cule_xff_hex', 'cule_agent', 'cule_timestamp' ],
				[ '127.0.0.1', '7F000001', '', null, '', '20240506070809' ],
			],
		];
	}

	/** @dataProvider provideFieldsThatAreTruncated */
	public function testTruncationForInsertMethods( $table, string $field ) {
		// Define a mock ContentLanguage service that mocks ::truncateForDatabase
		// so that if the method changes implementation this test will not fail and/or
		// the wiki running the test isn't in English.
		$mockContentLanguage = $this->createMock( Language::class );
		$mockContentLanguage->method( 'truncateForDatabase' )
			->willReturnCallback(
				static function ( $text, $length ) {
					return substr( $text, 0, $length - 3 ) . '...';
				}
			);
		$objectUnderTest = new CheckUserInsert(
			$this->getServiceContainer()->getActorStore(),
			$this->getServiceContainer()->get( 'CheckUserUtilityService' ),
			$this->getServiceContainer()->getCommentStore(),
			$this->getServiceContainer()->getHookContainer(),
			$this->getServiceContainer()->getConnectionProvider(),
			$mockContentLanguage,
			$this->getServiceContainer()->getTempUserConfig()
		);
		if ( $table === 'cu_changes' ) {
			$this->testInsertIntoCuChangesTable(
				[ $field => str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH + 9 ) ],
				[ $field ],
				[ str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH - 3 ) . '...' ],
				$objectUnderTest
			);
		} elseif ( $table === 'cu_private_event' ) {
			$this->testInsertIntoCuPrivateEventTable(
				[ $field => str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH + 9 ) ],
				[ $field ],
				[ str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH - 3 ) . '...' ],
				$objectUnderTest
			);
		} elseif ( $table === 'cu_log_event' ) {
			$this->setTemporaryHook(
				'CheckUserInsertLogEventRow',
				static function ( &$ip, &$xff, &$row ) use ( $field ) {
					$row[$field] = str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH + 9 );
				}
			);
			$this->testInsertIntoCuLogEventTable(
				[ $field ],
				[ str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH - 3 ) . '...' ],
				$objectUnderTest
			);
		}
	}

	public static function provideFieldsThatAreTruncated() {
		return [
			'cu_changes action text column' => [ 'cu_changes', 'cuc_actiontext' ],
			'cu_changes XFF column' => [ 'cu_changes', 'cuc_xff' ],
			'cu_private_event XFF column' => [ 'cu_private_event', 'cupe_xff' ],
			'cu_log_event XFF column' => [ 'cu_log_event', 'cule_xff' ],
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertChangesRow
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertPrivateEventRow
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertLogEventRow
	 * @dataProvider provideInsertMethodsHookModification
	 */
	public function testInsertMethodsHookModification( string $test_xff, string $xff_hex, $table ) {
		// Get the column prefix, hook name and common test method name for the given table.
		$prefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table];
		if ( $table === 'cu_changes' ) {
			$hook = 'CheckUserInsertChangesRow';
		} elseif ( $table === 'cu_private_event' ) {
			$hook = 'CheckUserInsertPrivateEventRow';
		} elseif ( $table === 'cu_log_event' ) {
			$hook = 'CheckUserInsertLogEventRow';
		} else {
			$this->fail( 'Unexpected table.' );
		}
		// Set a temporary hook to modify the XFF, IP and user agent fields.
		$this->setTemporaryHook(
			$hook,
			static function ( &$ip, &$xff, &$row ) use ( $test_xff, $prefix ) {
				$xff = $test_xff;
				$ip = '1.2.3.4';
				$row[$prefix . 'agent'] = 'TestAgent';
			}
		);
		// Call the common test method.
		$fields = [ $prefix . 'xff', $prefix . 'xff_hex', $prefix . 'ip', $prefix . 'ip_hex', $prefix . 'agent' ];
		$expectedValues = [ $test_xff, $xff_hex, '1.2.3.4', '01020304', 'TestAgent' ];
		if ( $table === 'cu_changes' ) {
			$this->testInsertIntoCuChangesTable( [], $fields, $expectedValues );
		} elseif ( $table === 'cu_private_event' ) {
			$this->testInsertIntoCuPrivateEventTable( [], $fields, $expectedValues );
		} elseif ( $table === 'cu_log_event' ) {
			$this->testInsertIntoCuLogEventTable( $fields, $expectedValues );
		}
	}

	public static function provideInsertMethodsHookModification() {
		foreach ( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] as $table ) {
			yield from [
				"Empty XFF for $table" => [ '', '', $table ],
				"XFF not empty for $table" => [ '1.2.3.4, 5.6.7.8', '01020304', $table ],
				"Invalid XFF for $table" => [ 'Invalid XFF', '', $table ],
			];
		}
	}

	/** @dataProvider provideCheckUserResultTables */
	public function testActorColumnInInsertMethods( $table, $user = null, $expectedActorId = 0 ) {
		$user ??= $this->getTestUser()->getUserIdentity();
		// 0 is used to indicate that no specific actor ID was provided for the test, and so this
		// method should acquire one. 0 is used as it is not a valid actor ID (while null can be the value
		// of cupe_actor in specific circumstances).
		if ( $expectedActorId === 0 ) {
			$expectedActorId = $this->getServiceContainer()->getActorStore()
				->acquireActorId( $user, $this->getDb() );
		}
		if ( $table === 'cu_changes' ) {
			$this->setUpObject()->insertIntoCuChangesTable( [], __METHOD__, $user );
		} elseif ( $table === 'cu_private_event' ) {
			$this->setUpObject()->insertIntoCuPrivateEventTable( [], __METHOD__, $user );
		} elseif ( $table === 'cu_log_event' ) {
			$logId = $this->newLogEntry();
			// Delete any entries that were created by ::newLogEntry.
			$this->truncateTables( [
				'cu_log_event',
			] );
			$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );
			$this->setUpObject()->insertIntoCuLogEventTable( $logEntry, __METHOD__, $user );
		} else {
			$this->fail( 'Unexpected table.' );
		}
		$this->assertSelect(
			$table,
			[ CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table] . 'actor' ],
			'',
			[ [ $expectedActorId ] ]
		);
	}

	public static function provideCheckUserResultTables() {
		return [
			'cu_changes' => [ 'cu_changes' ],
			'cu_log_event' => [ 'cu_log_event' ],
		];
	}

	/** @dataProvider provideCheckUserResultTablesWhichHaveANotNullActorColumn */
	public function testActorColumnForIPAddressWithExistingActorId( $table ) {
		// Tests that if an IP address already has an actor ID when temporary accounts are enabled the actor ID is
		// used instead of using NULL or throwing an exception.
		$ip = UserIdentityValue::newAnonymous( '1.2.3.4' );
		// Acquire the pre-existing actor ID for the IP address.
		$this->disableAutoCreateTempUser();
		$this->getServiceContainer()->getActorStore()->acquireActorId( $ip, $this->getDb() );
		// Enable temporary accounts and then perform the insert.
		$this->enableAutoCreateTempUser();
		$this->testActorColumnInInsertMethods(
			$table,
			$ip,
			$this->getServiceContainer()->getActorStore()->findActorId( $ip, $this->getDb() )
		);
	}

	public static function provideCheckUserResultTablesWhichHaveANotNullActorColumn() {
		return [
			'cu_changes' => [ 'cu_changes' ],
			'cu_log_event' => [ 'cu_log_event' ],
		];
	}

	public function testActorColumnForIPAddressForCuPrivateEventInsert() {
		// Tests that the value of cupe_actor is NULL when temporary accounts are enabled
		// and the performer is an IP address.
		$ip = UserIdentityValue::newAnonymous( '1.2.3.4' );
		// Enable temporary accounts and then perform the insert.
		$this->enableAutoCreateTempUser();
		$this->testActorColumnInInsertMethods( 'cu_private_event', $ip, null );
	}

	/** @dataProvider provideCheckUserResultTablesWhichHaveANotNullActorColumn */
	public function testActorColumnForIPAddressWithoutExistingActorId( $table ) {
		// Tests that if an IP address doesn't have an actor ID and we are not inserting to cu_private_event,
		// then we get a CannotCreateActorException.
		$this->expectException( CannotCreateActorException::class );
		$ip = UserIdentityValue::newAnonymous( '1.2.3.4' );
		// Enable temporary accounts and then perform the insert.
		$this->enableAutoCreateTempUser();
		$this->testActorColumnInInsertMethods( $table, $ip );
	}

	public function testInsertIntoCuLogEventTableLogId() {
		$logId = $this->newLogEntry();
		// Delete any entries that were created by ::newLogEntry.
		$this->truncateTables( [
			'cu_log_event',
		] );
		$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );

		$this->setUpObject()->insertIntoCuLogEventTable(
			$logEntry, __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$this->assertSelect(
			'cu_log_event',
			[ 'cule_log_id' ],
			'',
			[ [ $logId ] ]
		);
	}
}
