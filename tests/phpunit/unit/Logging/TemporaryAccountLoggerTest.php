<?php

namespace MediaWiki\CheckUser\Tests\Unit\Logging;

use Generator;
use ManualLogEntry;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\Expression;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLogger
 */
class TemporaryAccountLoggerTest extends MediaWikiUnitTestCase {
	public static function provideLogAccess(): Generator {
		yield [
			'logMethod' => 'logAccessEnabled',
			'changeType' => TemporaryAccountLogger::ACTION_ACCESS_ENABLED
		];
		yield [
			'logMethod' => 'logAccessDisabled',
			'changeType' => TemporaryAccountLogger::ACTION_ACCESS_DISABLED
		];
	}

	/**
	 * @dataProvider provideLogAccess
	 */
	public function testLogAccess( $logMethod, $changeType ) {
		$name = 'Foo';
		$performer = new UserIdentityValue( 1, $name );
		$expectedTarget = Title::makeTitle( NS_USER, $name );

		$expectedParams = [ '4::changeType' => $changeType ];

		$database = $this->createMock( IDatabase::class );

		$logger = $this->getMockBuilder( TemporaryAccountLogger::class )
			->setConstructorArgs( [
				$this->createMock( ActorStore::class ),
				new NullLogger(),
				$database,
				24 * 60 * 60,
			] )
			->onlyMethods( [ 'createManualLogEntry' ] )
			->getMock();

		$logEntry = $this->createMock( ManualLogEntry::class );
		$logEntry->expects( $this->once() )
			->method( 'setPerformer' )
			->with( $performer );

		$logEntry->expects( $this->once() )
			->method( 'setTarget' )
			->with( $expectedTarget );

		$logEntry->expects( $this->once() )
			->method( 'setParameters' )
			->with( $expectedParams );

		$logEntry->expects( $this->once() )
			->method( 'insert' )
			->with( $database );

		$logger->expects( $this->once() )
			->method( 'createManualLogEntry' )
			->with( TemporaryAccountLogger::ACTION_CHANGE_ACCESS )
			->willReturn( $logEntry );

		$logger->$logMethod( $performer );
	}

	public static function provideLogViewDebounced(): Generator {
		yield [
			'isDebounced' => true,
		];
		yield [
			'isDebounced' => false,
		];
	}

	/**
	 * @dataProvider provideLogViewDebounced
	 */
	public function testLogViewDebounced(
		bool $isDebounced
	): void {
		$performer = new UserIdentityValue( 1, 'Foo' );
		$actorId = 2;
		$target = '*Unregistered 1';
		$timestamp = 42;

		$expectedTarget = Title::makeTitle( NS_USER, $target );

		$database = $this->createMock( IDatabase::class );

		$queryBuilder = new SelectQueryBuilder( $database );

		$database->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		// We don't need to stub IDatabase::timestamp() since it is wrapped in
		// a call to IDatabase::expr().
		$timestampExpr = $this->createMock( Expression::class );
		$timestampExpr->method( 'toSql' )->willReturn( "log_timestamp > $timestamp" );
		$database->method( 'expr' )
			->willReturn( $timestampExpr );

		$map = [
				[
					[ 'logging' ],
					[ '*' ],
					[
						"log_type" => "checkuser-temporary-account",
						"log_action" => TemporaryAccountLogger::ACTION_VIEW_IPS,
						"log_actor" => 2,
						"log_namespace" => 2,
						"log_title" => $target,
						$timestampExpr,
					],
					'MediaWiki\\CheckUser\\Logging\\TemporaryAccountLogger::debouncedLog',
					[],
					[],
					(int)$isDebounced,
				],
				[
					[ 'logging' ],
					[ '*' ],
					[
						"log_type" => "checkuser-temporary-account",
						"log_action" => TemporaryAccountLogger::ACTION_VIEW_IPS,
						"log_actor" => 2,
						"log_namespace" => 2,
						"log_title" => $target,
						$timestampExpr,
					],
					'MediaWiki\\CheckUser\\Logging\\TemporaryAccountLogger::debouncedLog',
					[],
					[],
					(int)$isDebounced,
				]
		];

		$database->method( 'selectRow' )
			->willReturnMap( $map );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorId' )
			->willReturnMap(
				[ [ $performer, $database, $actorId ], ]
			);

		$logger = $this->getMockBuilder( TemporaryAccountLogger::class )
			->setConstructorArgs( [
				$actorStore,
				new NullLogger(),
				$database,
				24 * 60 * 60,
			] )
			->onlyMethods( [ 'createManualLogEntry' ] )
			->getMock();

		if ( $isDebounced ) {
			$logger->expects( $this->never() )
				->method( 'createManualLogEntry' );
		} else {
			$logEntry = $this->createMock( ManualLogEntry::class );
			$logEntry->expects( $this->once() )
				->method( 'setPerformer' )
				->with( $performer );

			$logEntry->expects( $this->once() )
				->method( 'setTarget' )
				->with( $expectedTarget );

			$logEntry->expects( $this->once() )
				->method( 'insert' )
				->with( $database );

			$logger->expects( $this->once() )
				->method( 'createManualLogEntry' )
				->with( TemporaryAccountLogger::ACTION_VIEW_IPS )
				->willReturn( $logEntry );
		}

		$logger->logViewIPs(
			$performer,
			$target,
			(int)wfTimestamp()
		);
	}

	/**
	 * @dataProvider provideLogViewDebounced
	 */
	public function testLogFromExternal( bool $isDebounced ) {
		$name = 'Foo';
		$performer = new UserIdentityValue( 1, $name );
		$expectedTarget = Title::makeTitle( NS_USER, $name );

		$database = $this->createMock( IDatabase::class );

		$logger = $this->getMockBuilder( TemporaryAccountLogger::class )
			->setConstructorArgs( [
				$this->createMock( ActorStore::class ),
				new NullLogger(),
				$database,
				24 * 60 * 60,
			] )
			->onlyMethods( [ 'createManualLogEntry' ] )
			->getMock();

		$logEntry = $this->createMock( ManualLogEntry::class );
		$logEntry->expects( $this->once() )
			->method( 'setPerformer' )
			->with( $performer );

		$logEntry->expects( $this->once() )
			->method( 'setTarget' )
			->with( $expectedTarget );

		$logEntry->expects( $this->once() )
			->method( 'insert' )
			->with( $database );

		$logger->expects( $this->once() )
			->method( 'createManualLogEntry' )
			->with( 'test' )
			->willReturn( $logEntry );

		$logger->logFromExternal( $performer, 'Foo', 'test', [], $isDebounced, 0 );
	}
}
