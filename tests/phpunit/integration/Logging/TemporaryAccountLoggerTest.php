<?php

namespace MediaWiki\CheckUser\Tests\Integration\Logging;

use JobSpecification;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLogger
 * @covers \MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob
 * @group CheckUser
 * @group Database
 */
class TemporaryAccountLoggerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideLogViewTemporaryAccountsOnIP */
	public function testLogViewTemporaryAccountsOnIP( $targetIP ) {
		ConvertibleTimestamp::setFakeTime( '20240405060709' );
		$performer = $this->getTestSysop()->getUser();
		// Call the method under test using the LogTemporaryAccountAccessJob to do this for us.
		$this->getServiceContainer()->getJobQueueGroup()->push(
			new JobSpecification(
				'checkuserLogTemporaryAccountAccess',
				[
					'performer' => $performer->getName(), 'target' => $targetIP, 'timestamp' => (int)wfTimestamp(),
					'type' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP,
				]
			)
		);
		$this->getServiceContainer()->getJobRunner()->run( [ 'type' => 'checkuserLogTemporaryAccountAccess' ] );
		// Verify that a log exists with the correct title, type and performer.
		$this->assertSame(
			1,
			$this->getDb()->newSelectQueryBuilder()
				->from( 'logging' )
				->where( [
					'log_type' => TemporaryAccountLogger::LOG_TYPE,
					'log_action' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP,
					'log_actor' => $performer->getActorId(),
					'log_namespace' => NS_USER,
					'log_title' => IPUtils::prettifyIP( $targetIP ),
					'log_timestamp' => $this->getDb()->timestamp( '20240405060709' ),
				] )
				->fetchRowCount(),
			'The expected log was not written to the database.'
		);
		// Call the method under test again (directly this time to avoid testing extra code again) with the same
		// parameters and verify that no new log is created, because it is debounced.
		ConvertibleTimestamp::setFakeTime( '20240405060711' );
		/** @var TemporaryAccountLogger $logger */
		$logger = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' )->getLogger();
		$logger->logViewTemporaryAccountsOnIP( $performer, $targetIP, (int)wfTimestamp() );
		$this->assertSame(
			0,
			$this->getDb()->newSelectQueryBuilder()
				->from( 'logging' )
				->where( [ 'log_timestamp' => $this->getDb()->timestamp( '20240405060711' ) ] )
				->fetchRowCount(),
			'The expected log was not written to the database.'
		);
	}

	public static function provideLogViewTemporaryAccountsOnIP() {
		return [
			'Viewed temporary accounts on single IP' => [ '1.2.3.4' ],
			'Viewed temporary accounts on IP range' => [ '1.2.3.0/24' ],
		];
	}
}
