<?php

namespace MediaWiki\CheckUser\Tests\Integration\IPContributions;

use ErrorPageError;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentity;
use PermissionsError;
use SpecialPageTestBase;
use UserBlockedError;

/**
 * @covers \MediaWiki\CheckUser\IPContributions\SpecialIPContributions
 * @group CheckUser
 * @group Database
 */
class SpecialIPContributionsTest extends SpecialPageTestBase {

	use TempUserTestTrait;

	private static UserIdentity $disallowedUser;
	private static UserIdentity $checkuser;
	private static UserIdentity $sysop;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'IPContributions' );
	}

	protected function setup(): void {
		$this->enableAutoCreateTempUser();
		parent::setup();
	}

	/**
	 * Add a temporary user and a fully registered user who contributed
	 * from the same IP address. This is important to ensure we don't
	 * leak that the fully registered user edited from that IP.
	 *
	 * Also add contributions from multiple temp users and from multiple
	 * IPs, to test ranges.
	 */
	public function addDBDataOnce() {
		$this->enableAutoCreateTempUser();

		// The users must be created now because the actor table will
		// be altered when the edits are made, and added to the list
		// of tables that can't be altered again in $dbDataOnceTables.
		self::$disallowedUser = static::getTestUser()->getUser();
		self::$checkuser = static::getTestUser( [ 'checkuser' ] )->getUser();
		self::$sysop = static::getTestSysop()->getUser();

		$temp1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~2024-01', new FauxRequest() )->getUser();
		$temp2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~2024-02', new FauxRequest() )->getUser();

		// Named user and 2 temp users edit from the first IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		$this->editPage(
			'Test page', 'Test Content 1', 'test', NS_MAIN, self::$sysop
		);
		$this->editPage(
			'Test page', 'Test Content 2', 'test', NS_MAIN, $temp1
		);
		$this->editPage(
			'Test page', 'Test Content 3', 'test', NS_MAIN, $temp2
		);

		// Temp user edits again from a different IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 4', 'test', NS_MAIN, $temp1
		);
	}

	/**
	 * @dataProvider provideTargets
	 * @covers \MediaWiki\CheckUser\IPContributions\IPContributionsPager::getRevisionQuery
	 */
	public function testExecuteTarget( $target, $expectedCount ) {
		[ $html ] = $this->executeSpecialPage(
			$target,
			null,
			null,
			self::$checkuser
		);

		// Target field should be populated
		$this->assertStringContainsString( $target, $html );

		if ( $expectedCount > 0 ) {
			$this->assertStringContainsString( 'mw-pager-body', $html );
			// Use occurrences of data attribute in to determine how many rows,
			// to test pager.
			$this->assertSame( $expectedCount, substr_count( $html, 'data-mw-revid' ) );
		} else {
			$this->assertStringNotContainsString( 'mw-pager-body', $html );
		}
	}

	public function provideTargets() {
		return [
			'Empty target' => [ '', 0 ],
			'Valid IP' => [ '127.0.0.1', 2 ],
			'Valid range' => [ '127.0.0.1/24', 3 ],
			'Temp user' => [ '~2024-1', 0 ],
			'Named user' => [ 'FullyRegistered', 0 ],
			'Nonexistent user' => [ 'Nonexistent', 0 ],
		];
	}

	public function testExecuteErrorPreference() {
		$this->expectException( ErrorPageError::class );

		$this->executeSpecialPage(
			'',
			null,
			null,
			self::$sysop
		);
	}

	public function testExecuteErrorPermission() {
		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage(
			'',
			null,
			null,
			self::$disallowedUser
		);
	}

	public function testExecuteErrorBlock() {
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$checkuser->getName(),
				self::$sysop,
				'infinity'
			)
			->placeBlock();
		$this->expectException( UserBlockedError::class );

		$this->executeSpecialPage(
			'',
			null,
			null,
			self::$checkuser
		);
	}
}
