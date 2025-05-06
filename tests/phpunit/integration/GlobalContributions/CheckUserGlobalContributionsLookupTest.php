<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Jobs\UpdateUserCentralIndexJob;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup
 * @group CheckUser
 * @group Database
 */
class CheckUserGlobalContributionsLookupTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;

	private static User $tempUser1;
	private static User $tempUser2;
	private static User $tempUser3;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->enableAutoCreateTempUser();

		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	public function addDBDataOnce() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->enableAutoCreateTempUser();

		// The users must be created now because the actor table will
		// be altered when the edits are made, and added to the list
		// of tables that can't be altered again in $dbDataOnceTables.
		self::$tempUser1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-01', new FauxRequest() )->getUser();
		self::$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-02', new FauxRequest() )->getUser();
		self::$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-03', new FauxRequest() )->getUser();

		$page = $this->getNonexistingTestPage();

		// Make edits from temp accounts 1 and 2 from the same IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		$this->editPage(
			'Test page', 'Test Content 1', 'test', NS_MAIN, self::$tempUser1
		);
		$this->editPage(
			'Test page', 'Test Content 2', 'test', NS_MAIN, self::$tempUser2
		);

		// From a new IP, make an edit from temp account 2
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 3', 'test', NS_MAIN, self::$tempUser2
		);

		// Make an edit from temp account 3 and the registered user on a new IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.3' );
		$this->editPage(
			'Test page', 'Test Content 4', 'test', NS_MAIN, self::$tempUser3
		);

		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => UpdateUserCentralIndexJob::TYPE ] );
	}

	/** @dataProvider provideTestGetGlobalContributionCount */
	public function testGetGlobalContributionCount( $targetProvider, $expectedCount ) {
		$services = $this->getServiceContainer();
		$lookup = new CheckUserGlobalContributionsLookup(
			$services->getConnectionProvider(),
			$services->get( 'ExtensionRegistry' ),
			$services->get( 'CentralIdLookup' ),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getMainConfig(),
			$services->getRevisionStore()
		);
		$authority = RequestContext::getMain()->getAuthority();

		$this->assertSame(
			$expectedCount,
			$lookup->getGlobalContributionsCount( $targetProvider(), $authority )
		);
	}

	public static function provideTestGetGlobalContributionCount() {
		return [
			'IP used by 2 temp accounts' => [
				'target' => static fn () => '127.0.0.1', 'expectedCount' => 2
			],
			'temp account that edited from an IP used by another temp account' => [
				'target' => static fn () => self::$tempUser1->getName(), 'expectedCount' => 1
			],
			'temp account that edited from 2 IPs' => [
				'target' => static fn () => self::$tempUser2->getName(), 'expectedCount' => 2
			],
			'temp account that edited from an IP used by another registered account' => [
				'target' => static fn () => self::$tempUser3->getName(), 'expectedCount' => 1
			],
		];
	}
}
