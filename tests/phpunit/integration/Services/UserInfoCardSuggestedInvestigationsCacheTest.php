<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardSuggestedInvestigationsCache;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use TestUser;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\UserInfoCardSuggestedInvestigationsCache
 * @group CheckUser
 * @group Database
 */
class UserInfoCardSuggestedInvestigationsCacheTest extends MediaWikiIntegrationTestCase {

	use SuggestedInvestigationsTestTrait;

	private WANObjectCache $wanCache;

	protected function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		$this->wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	private function newService(): UserInfoCardSuggestedInvestigationsCache {
		return new UserInfoCardSuggestedInvestigationsCache(
			$this->wanCache,
			$this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$this->getServiceContainer()->getUserIdentityLookup(),
			StatsFactory::newNull()
		);
	}

	private function getCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-open-si-case', $username );
	}

	/**
	 * Put the given user in a new case, and return the ID of that case.
	 */
	private function createCaseFor( UserIdentity $user, string $signalValue ): int {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'test', $signalValue, false );

		return $caseManager->createCase( [ $user ], [ $signal ] );
	}

	private function setCaseStatus( int $caseId, CaseStatus $status ): void {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseManager->setCaseStatus( $caseId, $status, 'test' );
	}

	public function testUserInOpenCase(): void {
		$user = $this->getTestUser()->getUser();
		$this->createCaseFor( $user, 'open' );

		$this->assertTrue( $this->newService()->hasOpenCase( $user->getName() ) );
		$this->assertSame( 1, $this->wanCache->get( $this->getCacheKey( $user->getName() ) ) );
	}

	public function testUserWithoutCase(): void {
		$user = $this->getTestUser()->getUser();

		$this->assertFalse( $this->newService()->hasOpenCase( $user->getName() ) );
		$this->assertSame( 0, $this->wanCache->get( $this->getCacheKey( $user->getName() ) ) );
	}

	/** @dataProvider provideClosedStatuses */
	public function testUserOnlyInClosedCase( CaseStatus $status ): void {
		$user = $this->getTestUser()->getUser();
		$this->setCaseStatus( $this->createCaseFor( $user, 'closed' ), $status );

		$this->assertFalse( $this->newService()->hasOpenCase( $user->getName() ) );
	}

	public static function provideClosedStatuses(): array {
		return [
			'resolved' => [ CaseStatus::Resolved ],
			'invalid' => [ CaseStatus::Invalid ],
		];
	}

	public function testUserInBothOpenAndClosedCases(): void {
		$user = $this->getTestUser()->getUser();
		$this->setCaseStatus( $this->createCaseFor( $user, 'closed' ), CaseStatus::Resolved );
		$this->createCaseFor( $user, 'open' );

		$this->assertTrue( $this->newService()->hasOpenCase( $user->getName() ) );
	}

	public function testReturnsOnlyUsersWithOpenCases(): void {
		$withCase = $this->getMutableTestUser()->getUser();
		$withoutCase = $this->getMutableTestUser()->getUser();
		$this->createCaseFor( $withCase, 'open' );

		$this->assertSame(
			[ $withCase->getName() ],
			$this->newService()->getUsersWithOpenCases(
				[ $withoutCase->getName(), $withCase->getName() ]
			)
		);
	}

	public function testReturnsStringsForNumericUsernames(): void {
		$numericUser = ( new TestUser( '12345' ) )->getUser();
		$this->createCaseFor( $numericUser, 'numeric' );

		$this->assertSame( [ '12345' ], $this->newService()->getUsersWithOpenCases( [ '12345' ] ) );
	}

	public function testIgnoresUnknownUsers(): void {
		$this->assertSame(
			[],
			$this->newService()->getUsersWithOpenCases( [ 'This user does not exist' ] )
		);
	}

	public function testReturnsEmptyArrayForNoUsernames(): void {
		$this->assertSame( [], $this->newService()->getUsersWithOpenCases( [] ) );
	}

	public function testDoesNotQueryWhenSuggestedInvestigationsAreDisabled(): void {
		$user = $this->getTestUser()->getUser();
		$this->createCaseFor( $user, 'open' );
		$this->disableSuggestedInvestigations();

		// The lookup service throws when the feature is off, so a query here would fail the test.
		$this->assertFalse( $this->newService()->hasOpenCase( $user->getName() ) );
		$this->assertFalse( $this->wanCache->get( $this->getCacheKey( $user->getName() ) ) );
	}

	public function testUsesTheCacheOnSecondCall(): void {
		$user = $this->getTestUser()->getUser();
		$caseId = $this->createCaseFor( $user, 'open' );
		$service = $this->newService();

		$this->assertTrue( $service->hasOpenCase( $user->getName() ) );

		// The status is only refreshed when the entry expires, so a change made now is not seen.
		$this->setCaseStatus( $caseId, CaseStatus::Resolved );
		$this->assertTrue( $service->hasOpenCase( $user->getName() ) );
	}
}
