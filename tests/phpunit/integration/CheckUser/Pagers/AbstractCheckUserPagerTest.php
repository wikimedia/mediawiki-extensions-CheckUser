<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\CheckUser\Pagers;

use CentralAuthTestUser;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Extension\CheckUser\Services\TokenManager;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Html\FormOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\HtmlAssertionHelperTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for AbstractCheckUserPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\Pagers\AbstractCheckUserPager
 */
class AbstractCheckUserPagerTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;
	use HtmlAssertionHelperTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( 'CheckUserCIDRLimit', [
			'IPv4' => 16,
			'IPv6' => 19,
		] );
	}

	protected function setUpObjectArguments( $params = [] ) {
		$opts = new FormOptions();
		$opts->add( 'reason', $params['reason'] ?? '' );
		$opts->add( 'period', $params['period'] ?? 0 );
		$opts->add( 'limit', $params['limit'] ?? 0 );
		$opts->add( 'dir', $params['dir'] ?? '' );
		$opts->add( 'offset', $params['offset'] ?? '' );
		$opts->add( 'wpHideTemporaryAccounts', $params['HideTemporaryAccounts'] ?? false );
		$services = $this->getServiceContainer();
		return [
			$opts,
			UserIdentityValue::newAnonymous( $params['target'] ?? '1.2.3.4' ),
			'userips',
			$services->getService( 'CheckUserTokenQueryManager' ),
			$services->getUserGroupManager(),
			$services->getCentralIdLookup(),
			$services->getConnectionProvider(),
			$services->getSpecialPageFactory(),
			$services->getUserIdentityLookup(),
			$services->getService( 'CheckUserLogService' ),
			$services->getUserFactory(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getUserOptionsLookup(),
			$services->getDatabaseBlockStore(),
			$services->getTempUserConfig(),
			$services->getExtensionRegistry(),
			$services->getTitleFormatter(),
		];
	}

	/**
	 * @param array $params
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject( $params = [] ) {
		RequestContext::getMain()->setUser( $this->getTestUser( 'checkuser' )->getUser() );
		RequestContext::getMain()->setLanguage( 'qqx' );
		$object = new DeAbstractedCheckUserPager(
			...$this->setUpObjectArguments( $params )
		);
		return TestingAccessWrapper::newFromObject( $object );
	}

	public function testSetPeriodConditionCalledInConstructor() {
		# Tests that the ::setPeriodCondition is called by the constructor.
		# Unit tests exist for ::setPeriodCondition.
		ConvertibleTimestamp::setFakeTime( '1653077137' );
		$object = $this->setUpObject( [ 'period' => 7 ] );
		$object->setPeriodCondition();
		$this->assertArrayEquals(
			[ $object->mDb->timestamp( '20220513000000' ), '' ],
			$object->getRangeOffsets(),
			false,
			false,
			'::setPeriodCondition may not have been called by the constructor. This method needs ' .
			'to be called by the constructor.'
		);
	}

	/** @dataProvider provideUserWasBlocked */
	public function testUserWasBlocked( $block ) {
		$testUser = $this->getTestUser()->getUser();
		if ( $block ) {
			$userAuthority = $this->mockRegisteredUltimateAuthority();
			$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
				$testUser,
				$userAuthority,
				'1 second'
			)->placeBlock();
		}
		$object = $this->setUpObject();
		$this->assertSame(
			$block,
			$object->userWasBlocked( $testUser->getName() )
		);
	}

	public static function provideUserWasBlocked() {
		return [
			'User was previously blocked' => [ true ],
			'User never previously blocked' => [ false ],
		];
	}

	public function testGetEmptyBodyNoCheckLast() {
		$object = $this->setUpObject();
		$object->target = UserIdentityValue::newRegistered( 1, 'test' );
		$object->xfor = false;
		$this->assertSame(
			wfMessage( 'checkuser-nomatch' )->parseAsBlock() . "\n",
			$object->getEmptyBody(),
			'The checkuser-nomatch message should have been returned.'
		);
	}

	public function testUserBlockFlagsForLocallyBlockedSingleIP(): void {
		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$block = $blockStore->newUnsaved( [
			'address' => '1.2.3.4',
			'expiry' => $this->getDb()->getInfinity(),
		] );
		$block->setBlocker( $this->getTestSysop()->getUser() );
		$blockStore->insertBlock( $block );

		$actualBlockFlags = $this->setUpObject()->userBlockFlags(
			'1.2.3.4',
			UserIdentityValue::newAnonymous( '1.2.3.4' )
		);

		$this->verifyBlockFlag(
			$actualBlockFlags,
			$this->getServiceContainer()->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				'(checkuser-blocked)',
				[],
				[ 'type' => 'block', 'page' => 'User:1.2.3.4' ]
			)
		);
	}

	private function verifyBlockFlag( array $blockFlags, string $expectedBlockFlag ): void {
		$this->assertCount(
			1,
			$blockFlags,
			'Only expected one block flag to exist'
		);

		$blockFlagNode = $this->assertSelectorMatchesOneElementInNode(
			DOMUtils::parseHTML( $blockFlags[0] ),
			'.mw-changeslist-links'
		);
		$this->assertSame(
			$expectedBlockFlag,
			DOMCompat::getInnerHTML( $blockFlagNode ),
			'The block flag was not as expected'
		);
	}

	public function testUserBlockFlagsForLocallyBlockedIPRange(): void {
		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$block = $blockStore->newUnsaved( [
			'address' => '1.2.3.5/20',
			'expiry' => $this->getDb()->getInfinity(),
		] );
		$block->setBlocker( $this->getTestSysop()->getUser() );
		$blockStore->insertBlock( $block );

		$actualBlockFlags = $this->setUpObject()->userBlockFlags(
			'1.2.3.4',
			UserIdentityValue::newAnonymous( '1.2.3.4' )
		);
		$this->verifyBlockFlag(
			$actualBlockFlags,
			$this->getServiceContainer()->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				'(checkuser-blocked)',
				[],
				[ 'type' => 'block', 'page' => 'User:1.2.0.0/20' ]
			) . ' - 1.2.0.0/20',
		);
	}

	public function testUserBlockFlagsForAutoblockedIP(): void {
		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$block = $blockStore->newUnsaved( [
			'targetUser' => $this->getTestUser()->getUserIdentity(),
			'expiry' => '1 hour',
			'enableAutoblock' => true,
		] );
		$block->setBlocker( $this->getTestSysop()->getUser() );
		$blockStore->insertBlock( $block );

		$autoBlockId = $blockStore->doAutoblock( $block, '1.2.3.4' );
		$this->assertNotFalse( $autoBlockId );

		$actualBlockFlags = $this->setUpObject()->userBlockFlags(
			'1.2.3.4',
			UserIdentityValue::newAnonymous( '1.2.3.4' )
		);
		$this->verifyBlockFlag(
			$actualBlockFlags,
			$this->getServiceContainer()->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'BlockList' ),
				'(checkuser-blocked)',
				[],
				[ 'wpTarget' => "#$autoBlockId" ]
			)
		);
	}

	public function testUserBlockFlagsForLocallyBlockedUser(): void {
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );

		$testUser = $this->getTestUser()->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser( $testUser, $this->getTestSysop()->getAuthority(), '1 hour' )
			->placeBlock();
		$this->assertStatusGood( $blockStatus );

		$actualBlockFlags = $this->setUpObject()->userBlockFlags( '', $testUser );
		$this->verifyBlockFlag(
			$actualBlockFlags,
			$this->getServiceContainer()->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				'(checkuser-blocked)',
				[],
				[ 'type' => 'block', 'page' => $testUser->getUserPage()->getPrefixedText() ]
			)
		);
	}

	public function testUserBlockFlagsForGloballyBlockedIP() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$object = $this->setUpObject();
		$ip = '1.2.3.4';
		$user = UserIdentityValue::newAnonymous( $ip );
		// Globally block the IP
		GlobalBlockingServices::wrap( $this->getServiceContainer() )->getGlobalBlockManager()->block(
			$ip,
			'test',
			'1 week',
			$this->getTestUser( [ 'steward' ] )->getUserIdentity()
		);
		$this->assertSame(
			[ '<strong>(' . wfMessage( 'checkuser-gblocked' )->escaped() . ')</strong>' ],
			$object->userBlockFlags( $ip, $user ),
			'The checkuser-gblocked flag should have been returned as the IP is globally blocked.'
		);
	}

	public function testUserBlockFlagsForGloballyBlockedUser() {
		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$object = $this->setUpObject();
		$ip = '1.2.3.4';
		$user = $this->getMutableTestUser()->getUserIdentity();
		// Globally block the test user
		GlobalBlockingServices::wrap( $this->getServiceContainer() )->getGlobalBlockManager()->block(
			$user->getName(),
			'test',
			'1 week',
			$this->getTestUser( [ 'steward' ] )->getUserIdentity()
		);
		$this->assertContains(
			'<strong>(' . wfMessage( 'checkuser-gblocked' )->escaped() . ')</strong>',
			$object->userBlockFlags( $ip, $user ),
			'The checkuser-gblocked flag should have been returned as the IP is globally blocked.'
		);
	}

	public function testUserBlockFlagsTorExitNode() {
		$this->markTestSkippedIfExtensionNotLoaded( 'TorBlock' );
		$object = $this->setUpObject();
		// TEST-NET-1
		$ip = '192.0.2.111';
		$user = UserIdentityValue::newAnonymous( $ip );
		$this->assertSame(
			[ '<strong>(' . wfMessage( 'checkuser-torexitnode' )->escaped() . ')</strong>' ],
			$object->userBlockFlags( $ip, $user ),
			'The checkuser-torexitnode message should have been returned; the IP was not detected as an exit node'
		);
	}

	public function testUserBlockFlagsForPreviouslyBlockedUser(): void {
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );

		$testUser = $this->getTestUser()->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$testUser,
				$this->getTestSysop()->getAuthority(),
				'1 hour'
			)
			->placeBlock();
		$this->assertStatusGood( $blockStatus );

		$this->getServiceContainer()->getDatabaseBlockStore()->deleteBlock( $blockStatus->getValue() );

		$actualBlockFlags = $this->setUpObject()->userBlockFlags( '', $testUser );
		$this->verifyBlockFlag(
			$actualBlockFlags,
			$this->getServiceContainer()->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				'(checkuser-wasblocked)',
				[],
				[ 'type' => 'block', 'page' => $testUser->getUserPage()->getPrefixedText() ]
			)
		);
	}

	public function testUserBlockFlagsForUserWithNoCentralAccount(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$actualBlockFlags = $this->setUpObject()->userBlockFlags( '', $this->getTestUser()->getUserIdentity() );
		$this->verifyBlockFlag( $actualBlockFlags, '(checkuser-localonly)' );
	}

	public function testUserBlockFlagsForUserWhichIsLocked(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$this->assertStatusGood( $centralAuthUser->adminLock() );

		$actualBlockFlags = $this->setUpObject()->userBlockFlags( '', $this->getTestUser()->getUserIdentity() );
		$this->verifyBlockFlag( $actualBlockFlags, '(checkuser-locked)' );
	}

	public function testUserBlockFlagsForUserWithUserGroups(): void {
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$actualBlockFlags = $this->setUpObject()->userBlockFlags(
			'',
			$this->getTestUser( [ 'suppress', 'sysop' ] )->getUserIdentity()
		);
		$this->verifyBlockFlag(
			$actualBlockFlags,
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )->commaList( [
				UserGroupMembership::getLinkHTML( 'suppress', RequestContext::getMain() ),
				UserGroupMembership::getLinkHTML( 'sysop', RequestContext::getMain() ),
			] )
		);
	}

	/** @dataProvider provideTestFormOptionsLimitValue */
	public function testFormOptionsLimitValue( $formSubmittedLimit, $maximumLimit, $expectedLimit ) {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', $maximumLimit );
		$object = $this->setUpObject( [ 'limit' => $formSubmittedLimit ] );
		$this->assertSame(
			$expectedLimit,
			$object->mLimit,
			'The limit used for running the check was not the expected value given the user defined and maximum limit.'
		);
	}

	public static function provideTestFormOptionsLimitValue() {
		return [
			'Empty limit' => [ 0, 5000, 5000 ],
			'Limit under maximum limit' => [ 200, 5000, 200 ],
			'Limit over maximum limit' => [ 500, 200, 200 ],
		];
	}

	/** @dataProvider provideGetCheckUserHelperFieldset */
	public function testGetCheckUserHelperFieldset(
		$collapseByDefaultConfigValue,
		$userPreferenceValue,
		$shouldBeByDefaultCollapsed,
		$resultRowCount
	) {
		$this->overrideConfigValue( 'CheckUserCollapseCheckUserHelperByDefault', $collapseByDefaultConfigValue );
		$object = $this->setUpObject();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			RequestContext::getMain()->getUser(),
			'checkuser-helper-table-collapse-by-default',
			$userPreferenceValue
		);
		$userOptionsManager->saveOptions( RequestContext::getMain()->getUser() );
		$object->mResult = $this->createMock( IResultWrapper::class );
		$object->mResult->method( 'numRows' )->willReturn( $resultRowCount );
		$fieldset = TestingAccessWrapper::newFromObject( $object->getCheckUserHelperFieldset() );
		$this->assertSame(
			'mw-checkuser-helper-fieldset',
			$fieldset->outerClass,
			'CheckUser fieldset should have surrounding class.'
		);
		$this->assertSame(
			wfMessage( 'checkuser-helper-label' )->text(),
			$fieldset->mWrapperLegend,
			'Wrapper legend text is incorrect.'
		);
		$this->assertFalse(
			$fieldset->mShowSubmit,
			'The fieldset should not show a submit button.'
		);
		$this->assertTrue(
			$fieldset->mCollapsible,
			'The fieldset should be collapsable.'
		);
		$this->assertSame(
			$shouldBeByDefaultCollapsed,
			$fieldset->mCollapsed,
			'The default collapsed state for the fieldset is not correct.'
		);
	}

	public static function provideGetCheckUserHelperFieldset() {
		return [
			'wgCheckUserCollapseCheckUserHelperByDefault set to true' => [
				// The value of wgCheckUserCollapseCheckUserHelperByDefault
				true,
				// The value for the user's "checkuser-helper-table-collapse-by-default" preference
				Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT,
				// Whether the summary table should be collapsed by default
				true,
				// The number of results in the mResult object
				1,
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to false' => [
				false, Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, false, 2,
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to an integer less than the row count' => [
				3, Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, true, 5,
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to an integer greater than the row count' => [
				10, Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, false, 3,
			],
			'User preference set to always' => [
				false, Preferences::CHECKUSER_HELPER_ALWAYS_COLLAPSE_BY_DEFAULT, true, 1,
			],
			'User preference set to never' => [
				true, Preferences::CHECKUSER_HELPER_NEVER_COLLAPSE_BY_DEFAULT, false, 2,
			],
			'User preference set to integer less than row count' => [ false, 3, true, 5 ],
			'User preference set to integer more than row count' => [ true, 10, false, 3 ],
		];
	}

	/** @dataProvider provideGetCheckUserResultsFilterFieldset */
	public function testGetCheckUserResultsFilterFieldset( $temporaryAccountsKnown, $target, $expectedFilterFields ) {
		// Needed for HTMLForm to work in the code we are testing
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'CheckUser' ) );

		$this->disableAutoCreateTempUser( [ 'known' => $temporaryAccountsKnown ] );

		$object = $this->setUpObject( [
			'dir' => 'prev', 'limit' => 123, 'period' => 0, 'offset' => '20250504050405', 'reason' => 'testing',
			'target' => $target,
		] );

		$this->setUserLang( 'qqx' );
		$fieldset = $object->getCheckUserResultsFilterFieldset();

		if ( count( $expectedFilterFields ) === 0 ) {
			$this->assertSame( '', $fieldset );
			return;
		}

		$fieldsetDocument = DOMUtils::parseHTML( $fieldset );

		foreach ( $expectedFilterFields as $fieldName ) {
			$filterField = DOMCompat::querySelector( $fieldsetDocument, 'input[name=' . $fieldName . ']' );
			$this->assertNotNull( $filterField );
		}

		/** @var TokenManager $tokenManager */
		$tokenManager = $this->getServiceContainer()->get( 'CheckUserTokenManager' );

		$tokenField = DOMCompat::querySelector( $fieldsetDocument, 'input[name=token]' );
		$actualToken = $tokenField->getAttribute( 'value' );
		$this->assertArrayEquals(
			[
				'period' => 0, 'limit' => 123, 'reason' => 'testing', 'offset' => '20250504050405',
				'dir' => 'prev', 'user' => $target,
			],
			$tokenManager->decode( RequestContext::getMain()->getRequest()->getSession(), $actualToken ),
			false,
			true,
			'CheckUser JWT token for paging returned unexpected data'
		);

		$editTokenField = DOMCompat::querySelector( $fieldsetDocument, 'input[name=wpEditToken]' );
		$this->assertNotNull( $editTokenField );

		$collapsedFieldset = DOMCompat::querySelector( $fieldsetDocument, '.mw-collapsible.mw-collapsed' );
		$this->assertNotNull( $collapsedFieldset, 'Filters form should be collapsible and initially collapsed' );
	}

	public static function provideGetCheckUserResultsFilterFieldset(): array {
		return [
			'Temporary accounts are not known' => [
				'temporaryAccountsKnown' => false, 'target' => '1.2.3.4', 'expectedFilterFields' => [],
			],
			'Temporary accounts are known, but target is not an IP or IP range' => [
				'temporaryAccountsKnown' => true, 'target' => 'Abc', 'expectedFilterFields' => [],
			],
			'Temporary accounts are known and target is an IP' => [
				'temporaryAccountsKnown' => true, 'target' => '1.2.3.4',
				'expectedFilterFields' => [ 'wpHideTemporaryAccounts' ],
			],
		];
	}
}
