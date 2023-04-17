<?php

namespace MediaWiki\CheckUser\Test\Unit\Logging;

use Generator;
use ManualLogEntry;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Title;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLogger
 */
class TemporaryAccountLoggerTest extends MediaWikiUnitTestCase {
	public function provideLogAccess(): Generator {
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
}
