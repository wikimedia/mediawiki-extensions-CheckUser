<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsRelatedCasesLookup;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsRelatedCasesLookup
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsRelatedCasesSummary
 * @group Database
 * @group CheckUser
 */
class SuggestedInvestigationsRelatedCasesLookupTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	// Two related cases: identical user set {u1, u2} and a shared signal (name+value).
	private static int $relatedCaseA;
	private static int $relatedCaseB;

	// Two cases with the identical user set {u3, u4} but disjoint signals (different name),
	// so they are NOT related.
	private static int $sameUsersDisjointSignalsA;
	private static int $sameUsersDisjointSignalsB;

	// A subset/superset pair sharing a signal but with differing user sets, so they are NOT related.
	private static int $subsetCase;
	private static int $supersetCase;

	// A case with no related cases.
	private static int $loneCase;

	// Two related cases with the identical user set {u9, u10} that share *two* signals
	// (name+value), used to confirm the related-case count is not inflated by extra shared signals.
	private static int $multiSignalCaseA;
	private static int $multiSignalCaseB;

	// Two related cases: identical user set {u11, u12} and a shared signal (same name, different value).
	private static int $sameUsersDifferentSignalValueA;
	private static int $sameUsersDifferentSignalValueB;

	// Maps each seeded case ID to its list of users, for building the lookup input.
	private static array $usersByCaseId = [];

	private SuggestedInvestigationsRelatedCasesLookup $lookup;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
		$this->lookup = $this->getServiceContainer()
			->get( 'CheckUserSuggestedInvestigationsRelatedCasesLookup' );
	}

	public function testRelatedCasesAreSymmetricAndStatusAgnostic(): void {
		$result = $this->lookup->getCasesRelatedToCases( [
			self::$relatedCaseA => self::$usersByCaseId[self::$relatedCaseA],
			self::$relatedCaseB => self::$usersByCaseId[self::$relatedCaseB],
		] );

		// Each case lists the other as related, in both directions. Case B was set to
		// Resolved in seeding, confirming relatedness spans case statuses.
		$this->assertEqualsCanonicalizing(
			[ self::$relatedCaseB ],
			$result[self::$relatedCaseA]->getRelatedCaseIds()
		);
		$this->assertEqualsCanonicalizing(
			[ self::$relatedCaseA ],
			$result[self::$relatedCaseB]->getRelatedCaseIds()
		);
	}

	public function testRelatedCaseFoundWhenOnlyOneSideIsInInput(): void {
		// Only case A is passed in; the related case B is discovered from the database.
		$result = $this->lookup->getCasesRelatedToCases( [
			self::$relatedCaseA => self::$usersByCaseId[self::$relatedCaseA],
		] );

		$this->assertEqualsCanonicalizing(
			[ self::$relatedCaseB ],
			$result[self::$relatedCaseA]->getRelatedCaseIds()
		);
	}

	public function testSameUsersWithDisjointSignalsAreNotRelated(): void {
		// Same user set but the shared signal has different names, so the signal
		// intersection is empty.
		$result = $this->lookup->getCasesRelatedToCases( [
			self::$sameUsersDisjointSignalsA => self::$usersByCaseId[self::$sameUsersDisjointSignalsA],
			self::$sameUsersDisjointSignalsB => self::$usersByCaseId[self::$sameUsersDisjointSignalsB],
		] );

		$this->assertSame( [], $result[self::$sameUsersDisjointSignalsA]->getRelatedCaseIds() );
		$this->assertSame( [], $result[self::$sameUsersDisjointSignalsB]->getRelatedCaseIds() );
	}

	public function testSignalsWithDifferentValuesAreRelated(): void {
		// Same user set but the shared signal name has different values, so the signal
		// intersection (compared by name) is not empty.
		$result = $this->lookup->getCasesRelatedToCases( [
			self::$sameUsersDifferentSignalValueA => self::$usersByCaseId[self::$sameUsersDifferentSignalValueA],
			self::$sameUsersDifferentSignalValueB => self::$usersByCaseId[self::$sameUsersDifferentSignalValueB],
		] );

		$this->assertSame(
			[ self::$sameUsersDifferentSignalValueB ],
			$result[self::$sameUsersDifferentSignalValueA]->getRelatedCaseIds()
		);
		$this->assertSame(
			[ self::$sameUsersDifferentSignalValueA ],
			$result[self::$sameUsersDifferentSignalValueB]->getRelatedCaseIds()
		);
	}

	public function testSharedSignalWithDifferentUserSetsAreNotRelated(): void {
		// The two cases share a signal value but one user set is a superset of the other,
		// so they are not related.
		$result = $this->lookup->getCasesRelatedToCases( [
			self::$subsetCase => self::$usersByCaseId[self::$subsetCase],
			self::$supersetCase => self::$usersByCaseId[self::$supersetCase],
		] );

		$this->assertSame( [], $result[self::$subsetCase]->getRelatedCaseIds() );
		$this->assertSame( [], $result[self::$supersetCase]->getRelatedCaseIds() );
	}

	public function testEmptyInputReturnsEmptyArray(): void {
		$this->assertSame( [], $this->lookup->getCasesRelatedToCases( [] ) );
	}

	public function testCaseWithNoRelatedCasesIsPresentWithEmptySummary(): void {
		$result = $this->lookup->getCasesRelatedToCases( [
			self::$loneCase => self::$usersByCaseId[self::$loneCase],
		] );

		$this->assertSame( [], $result[self::$loneCase]->getRelatedCaseIds() );
	}

	public function testRelatedCaseSharingMultipleSignalsIsCountedOnce(): void {
		// The two cases share two signals (name+value) and have the identical user set.
		// They must still be reported as a single related case, not one per shared signal.
		$result = $this->lookup->getCasesRelatedToCases( [
			self::$multiSignalCaseA => self::$usersByCaseId[self::$multiSignalCaseA],
			self::$multiSignalCaseB => self::$usersByCaseId[self::$multiSignalCaseB],
		] );

		$this->assertSame(
			[ self::$multiSignalCaseB ],
			$result[self::$multiSignalCaseA]->getRelatedCaseIds()
		);
		$this->assertSame(
			[ self::$multiSignalCaseA ],
			$result[self::$multiSignalCaseB]->getRelatedCaseIds()
		);
	}

	public function addDBDataOnce(): void {
		$this->enableSuggestedInvestigations();

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$user1 = UserIdentityValue::newRegistered( 1, 'Related test user 1' );
		$user2 = UserIdentityValue::newRegistered( 2, 'Related test user 2' );
		$user3 = UserIdentityValue::newRegistered( 3, 'Related test user 3' );
		$user4 = UserIdentityValue::newRegistered( 4, 'Related test user 4' );
		$user5 = UserIdentityValue::newRegistered( 5, 'Related test user 5' );
		$user6 = UserIdentityValue::newRegistered( 6, 'Related test user 6' );
		$user7 = UserIdentityValue::newRegistered( 7, 'Related test user 7' );
		$user8 = UserIdentityValue::newRegistered( 8, 'Related test user 8' );
		$user9 = UserIdentityValue::newRegistered( 9, 'Related test user 9' );
		$user10 = UserIdentityValue::newRegistered( 10, 'Related test user 10' );
		$user11 = UserIdentityValue::newRegistered( 11, 'Related test user 11' );
		$user12 = UserIdentityValue::newRegistered( 12, 'Related test user 12' );

		// Related pair: identical user set {u1, u2}, sharing signal "sig1|data1".
		self::$relatedCaseA = $caseManager->createCase(
			[ $user1, $user2 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig1', 'data1', false ) ]
		);
		self::$usersByCaseId[self::$relatedCaseA] = [ $user1, $user2 ];

		self::$relatedCaseB = $caseManager->createCase(
			[ $user1, $user2 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig1', 'data1', false ) ]
		);
		self::$usersByCaseId[self::$relatedCaseB] = [ $user1, $user2 ];
		// Status should not affect relatedness; close one of the pair.
		$caseManager->setCaseStatus( self::$relatedCaseB, CaseStatus::Resolved, 'Test reason' );

		// Same user set {u3, u4} but the shared signal name has different values.
		self::$sameUsersDisjointSignalsA = $caseManager->createCase(
			[ $user3, $user4 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig2a', 'data2a', false ) ]
		);
		self::$usersByCaseId[self::$sameUsersDisjointSignalsA] = [ $user3, $user4 ];

		self::$sameUsersDisjointSignalsB = $caseManager->createCase(
			[ $user3, $user4 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig2b', 'data2b', false ) ]
		);
		self::$usersByCaseId[self::$sameUsersDisjointSignalsB] = [ $user3, $user4 ];

		// Shared signal but differing user sets: {u5, u6} vs {u5, u6, u7}.
		self::$subsetCase = $caseManager->createCase(
			[ $user5, $user6 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig3', 'data3', false ) ]
		);
		self::$usersByCaseId[self::$subsetCase] = [ $user5, $user6 ];

		self::$supersetCase = $caseManager->createCase(
			[ $user5, $user6, $user7 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig3', 'data3', false ) ]
		);
		self::$usersByCaseId[self::$supersetCase] = [ $user5, $user6, $user7 ];

		// A case sharing neither users nor signals with any other case.
		self::$loneCase = $caseManager->createCase(
			[ $user8 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig4', 'data4', false ) ]
		);
		self::$usersByCaseId[self::$loneCase] = [ $user8 ];

		// Related pair with identical user set {u9, u10} sharing two signals (name+value),
		// so the self-join yields two matching signal rows per case pair.
		$multiSignal1 = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig5', 'data5', false );
		$multiSignal2 = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig6', 'data6', false );

		self::$multiSignalCaseA = $caseManager->createCase( [ $user9, $user10 ], [ $multiSignal1 ] );
		$caseManager->updateCase( self::$multiSignalCaseA, [], [ $multiSignal2 ] );
		self::$usersByCaseId[self::$multiSignalCaseA] = [ $user9, $user10 ];

		self::$multiSignalCaseB = $caseManager->createCase( [ $user9, $user10 ], [ $multiSignal1 ] );
		$caseManager->updateCase( self::$multiSignalCaseB, [], [ $multiSignal2 ] );
		self::$usersByCaseId[self::$multiSignalCaseB] = [ $user9, $user10 ];

		// Related pair: user set {u11, u12}, sharing signal "sig7|data1", "sig7|data2".
		self::$sameUsersDifferentSignalValueA = $caseManager->createCase(
			[ $user11, $user12 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig7', 'data1', false ) ]
		);
		self::$usersByCaseId[self::$sameUsersDifferentSignalValueA] = [ $user11, $user12 ];

		self::$sameUsersDifferentSignalValueB = $caseManager->createCase(
			[ $user11, $user12 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sig7', 'data2', false ) ]
		);
		self::$usersByCaseId[self::$sameUsersDifferentSignalValueB] = [ $user11, $user12 ];
	}
}
