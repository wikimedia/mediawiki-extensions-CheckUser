<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use MediaWiki\CheckUser\Hooks;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RecentChange;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use MockAuthorityTrait;
	use TempUserTestTrait;

	/**
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromClass( Hooks::class );
	}

	public function testProvideUpdateCheckUserData() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = self::getDefaultRecentChangeAttribs();
		$testUser = new UserIdentityValue( 1337, 'YeaaaahTests' );
		$actorId = $this->getServiceContainer()->getActorStore()->acquireActorId(
			$testUser,
			$this->getDb()
		);
		$testCases = [
			'registered user' => [
				array_merge( $attribs, [
					'rc_type' => RC_EDIT,
					'rc_user' => $testUser->getId(),
					'rc_user_text' => $testUser->getName(),
				] ),
				'cu_changes',
				[ 'cuc_actor', 'cuc_type' ],
				[ $actorId, RC_EDIT ],
			],
			'Log for special title with no log ID' => [
				array_merge( $attribs, [
					'rc_namespace' => NS_SPECIAL,
					'rc_title' => 'Log',
					'rc_type' => RC_LOG,
					'rc_log_type' => ''
				] ),
				'cu_private_event',
				[ 'cupe_title', 'cupe_timestamp', 'cupe_namespace' ],
				[ 'Log', $this->getDb()->timestamp( $attribs['rc_timestamp'] ), NS_SPECIAL ],
			]
		];
		foreach ( $testCases as $values ) {
			$this->onRecentChangeSave(
				$values[0],
				$values[1],
				$values[2],
				$values[3]
			);
			$this->truncateTables( [
				'cu_changes',
				'cu_private_event',
				'cu_log_event',
				'recentchanges'
			] );
		}
	}

	private function onRecentChangeSave(
		array $rcAttribs,
		string $table,
		array $fields,
		array $expectedRow
	): void {
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		( new Hooks() )->onRecentChange_save( $rc );
		foreach ( $fields as $index => $field ) {
			if ( in_array( $field, [ 'cuc_timestamp', 'cule_timestamp', 'cupe_timestamp' ] ) ) {
				$expectedRow[$index] = $this->getDb()->timestamp( $expectedRow[$index] );
			}
		}
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( $table )
			->assertRowValue( $expectedRow );
	}

	/**
	 * @dataProvider providePruneIPDataData
	 * @covers \MediaWiki\CheckUser\Jobs\PruneCheckUserDataJob
	 */
	public function testPruneIPDataData( int $currentTime, int $maxCUDataAge, array $timestamps, int $afterCount ) {
		$this->setMwGlobals( [
			'wgCUDMaxAge' => $maxCUDataAge,
			'wgCheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_NEW
		] );
		$logEntryCutoff = $this->getDb()->timestamp( $currentTime - $maxCUDataAge );
		foreach ( $timestamps as $timestamp ) {
			ConvertibleTimestamp::setFakeTime( $timestamp );
			$expectedRow = [];
			// Insertion into cu_changes
			$this->commonTestsUpdateCheckUserData( self::getDefaultRecentChangeAttribs(), [], $expectedRow );
			// Insertion into cu_private_event
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_log_type' => '' ] ),
				[],
				$expectedRow
			);
			// Insertion into cu_log_event
			$logId = $this->newLogEntry();
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_logid' => $logId ] ),
				[],
				$expectedRow
			);
		}
		$this->assertRowCount( count( $timestamps ), 'cu_changes', 'cuc_id',
			'cu_changes was not set up correctly for the test.' );
		$this->assertRowCount( count( $timestamps ), 'cu_private_event', 'cupe_id',
			'cu_private_event was not set up correctly for the test.' );
		$this->assertRowCount( count( $timestamps ), 'cu_log_event', 'cule_id',
			'cu_log_event was not set up correctly for the test.' );
		ConvertibleTimestamp::setFakeTime( $currentTime );
		$object = TestingAccessWrapper::newFromObject( ( new Hooks() ) );
		$object->pruneIPData();
		MediaWikiServices::getInstance()->getJobRunner()->run( [ 'type' => 'checkuserPruneCheckUserDataJob' ] );
		// Check that all the old entries are gone
		$this->assertRowCount( 0, 'cu_changes', 'cuc_id',
			'cu_changes has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cuc_timestamp', '<', $logEntryCutoff ) ] );
		$this->assertRowCount( 0, 'cu_private_event', 'cupe_id',
			'cu_private_event has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cupe_timestamp ', '<', $logEntryCutoff ) ] );
		$this->assertRowCount( 0, 'cu_log_event', 'cule_id',
			'cu_log_event has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cule_timestamp', '<', $logEntryCutoff ) ] );
		// Assert that no still in date entries were removed
		$this->assertRowCount( $afterCount, 'cu_changes', 'cuc_id',
			'cu_changes is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_private_event', 'cupe_id',
			'cu_private_event is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_log_event', 'cule_id',
			'cu_log_event is missing rows that were not stale after calling pruneIPData.' );
	}

	public static function providePruneIPDataData() {
		$currentTime = time();
		$defaultMaxAge = 7776000;
		return [
			'No entries to prune' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - 2,
					$currentTime - $defaultMaxAge + 100,
					$currentTime,
					$currentTime + 10
				],
				4
			],
			'Two entries to prune with two to be left' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - $defaultMaxAge - 20000,
					$currentTime - $defaultMaxAge - 100,
					$currentTime,
					$currentTime + 10
				],
				2
			],
			'Four entries to prune with no left' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - $defaultMaxAge - 20000,
					$currentTime - $defaultMaxAge - 100,
					$currentTime - $defaultMaxAge - 1,
					$currentTime - $defaultMaxAge - 100000
				],
				0
			]
		];
	}
}
