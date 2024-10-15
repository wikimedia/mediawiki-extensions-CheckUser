<?php
namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\CheckUser\Services\AccountCreationDetailsLookup
 * @group Database
 */
class AccountCreationDetailsLookupTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	private function getCheckUserPrivateEventsHandler() {
		return new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode()
		);
	}

	public function testGetIPAndUserAgentFromDBForPrivateLog() {
		// Force the account creation event to be logged to the private table
		// instead of the public one
		$this->overrideConfigValue( MainConfigNames::NewUserLog, false );

		$user = $this->getTestUser()->getUser();

		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$privateEventHandler = $this->getCheckUserPrivateEventsHandler();
		$privateEventHandler->onLocalUserCreated( $user, false );

		$lookup = new AccountCreationDetailsLookup( new NullLogger() );

		$results = $lookup->getIPAndUserAgentFromDB( $user->getName(), $this->getDb() );
		$this->assertSame( 1, $results->numRows(), "Should have found one row and didn't" );
		foreach ( $results as $row ) {
			$this->assertEquals( '7F000001', $row->cupe_ip_hex, 'Bad ip hex value' );
			$this->assertSame( 'Fake User Agent', $row->cupe_agent, 'Bad user agent string' );
		}
	}

	public function testGetIPAndUserAgentFromDBForPublicLogAndTemporaryAccount() {
		$this->enableAutoCreateTempUser( [ 'genPattern' => '~check-user-test-$1' ] );
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$user = $this->getServiceContainer()->getTempUserCreator()
			->create( null, RequestContext::getMain()->getRequest() )->getUser();
		$this->disableAutoCreateTempUser( [ 'known' => true, 'matchPattern' => '~check-user-test-$1' ] );

		$lookup = new AccountCreationDetailsLookup( new NullLogger() );

		$results = $lookup->getIPAndUserAgentFromDB( $user->getName(), $this->getDb() );
		$this->assertSame( 1, $results->numRows(), "Should have found one row and didn't" );
		foreach ( $results as $row ) {
			$this->assertEquals( '7F000001', $row->cupe_ip_hex, 'Bad ip hex value' );
			$this->assertSame( 'Fake User Agent', $row->cupe_agent, 'Bad user agent string' );
		}
	}
}
