<?php
namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\CheckUser\Services\AccountCreationDetailsLookup
 * @group Database
 */
class AccountCreationDetailsLookupTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;

	private function getCheckUserPrivateEventsHandler() {
		return new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$this->getServiceContainer()->get( 'UserAgentClientHintsManager' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider()
		);
	}

	private function getObjectUnderTest(): AccountCreationDetailsLookup {
		return new AccountCreationDetailsLookup(
			new NullLogger(),
			new ServiceOptions(
				AccountCreationDetailsLookup::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			)
		);
	}

	/** @dataProvider provideUserAgentTableMigrationStageValues */
	public function testGetAccountCreationIPAndUserAgentForPrivateLog(
		int $userAgentTableMigrationStage
	) {
		$this->overrideConfigValue( 'CheckUserUserAgentTableMigrationStage', $userAgentTableMigrationStage );

		// Force the account creation event to be logged to the private table
		// instead of the public one
		$this->overrideConfigValue( MainConfigNames::NewUserLog, false );

		$user = $this->getTestUser()->getUser();

		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$privateEventHandler = $this->getCheckUserPrivateEventsHandler();
		$privateEventHandler->onLocalUserCreated( $user, false );

		$this->assertArrayEquals(
			[ 'ip' => '127.0.0.1', 'agent' => 'Fake User Agent' ],
			$this->getObjectUnderTest()->getAccountCreationIPAndUserAgent(
				$user->getName(), $this->getDb()
			),
			false, true,
			'IP and User Agent returned is not as expected'
		);
	}

	public static function provideUserAgentTableMigrationStageValues(): array {
		return [
			'User Agent table migration stage set to read old' => [
				SCHEMA_COMPAT_READ_OLD | SCHEMA_COMPAT_WRITE_BOTH,
			],
			'User Agent table migration stage set to read new' => [
				SCHEMA_COMPAT_READ_NEW | SCHEMA_COMPAT_WRITE_BOTH,
			],
		];
	}

	public function testGetAccountCreationIPAndUserAgentForPublicLogAndTemporaryAccount() {
		$this->overrideConfigValue( MainConfigNames::NewUserLog, true );

		$this->enableAutoCreateTempUser();
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$user = $this->getServiceContainer()->getTempUserCreator()
			->create( null, RequestContext::getMain()->getRequest() )->getUser();
		$this->disableAutoCreateTempUser();

		$this->assertArrayEquals(
			[ 'ip' => '127.0.0.1', 'agent' => 'Fake User Agent' ],
			$this->getObjectUnderTest()->getAccountCreationIPAndUserAgent(
				$user->getName(), $this->getDb()
			),
			false, true,
			'IP and User Agent returned is not as expected'
		);
	}

	/** @dataProvider provideUserAgentTableMigrationStageValues */
	public function testGetAccountCreationIPAndUserAgentForPublicLog(
		int $userAgentTableMigrationStage
	) {
		$this->overrideConfigValue( 'CheckUserUserAgentTableMigrationStage', $userAgentTableMigrationStage );

		$user = $this->getTestUser()->getUser();

		// Create a newusers log that is sent to Special:RecentChanges which should cause an insert to
		// cu_log_event for this log entry
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$logEntry = new ManualLogEntry( 'newusers', 'create' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( $user->getUserPage() );
		$logEntry->setParameters( [
			'4::userid' => $user->getId(),
		] );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
		DeferredUpdates::doUpdates();

		$this->assertArrayEquals(
			[ 'ip' => '127.0.0.1', 'agent' => 'Fake User Agent' ],
			$this->getObjectUnderTest()->getAccountCreationIPAndUserAgent(
				$user->getName(), $this->getDb()
			),
			false, true,
			'IP and User Agent returned is not as expected'
		);
	}

	public function testGetAccountCreationIPAndUserAgentWhenNoLogFound() {
		$this->assertNull(
			$this->getObjectUnderTest()->getAccountCreationIPAndUserAgent(
				$this->getTestUser()->getUserIdentity()->getName(), $this->getDb()
			),
			'If no CheckUser result table has an entry for the account creation' .
				', then null should be returned'
		);
	}

	/** @dataProvider provideUserAgentTableMigrationStageValues */
	public function testGetAccountCreationIPAndUserAgentWhenLogIdProvided(
		int $userAgentTableMigrationStage
	) {
		$this->overrideConfigValue( 'CheckUserUserAgentTableMigrationStage', $userAgentTableMigrationStage );

		$createdUser = $this->getTestUser()->getUser();
		$performer = $this->getTestSysop()->getUserIdentity();

		// Create a newusers log that is sent to Special:RecentChanges which should cause an insert to
		// cu_log_event for this log entry
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$logEntry = new ManualLogEntry( 'newusers', 'create2' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $createdUser->getUserPage() );
		$logEntry->setParameters( [
			'4::userid' => $createdUser->getId(),
		] );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
		DeferredUpdates::doUpdates();

		// Provide the log ID in the call to the method under test,
		// which will cause a different code path to be tested
		$this->assertArrayEquals(
			[ 'ip' => '127.0.0.1', 'agent' => 'Fake User Agent' ],
			$this->getObjectUnderTest()->getAccountCreationIPAndUserAgent(
				$performer->getName(), $this->getDb(), $logid
			),
			false, true,
			'IP and User Agent returned is not as expected'
		);
	}
}
