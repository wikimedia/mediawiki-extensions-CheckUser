<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use InvalidArgumentException;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup
 * @group CheckUser
 * @group Database
 */
class CheckUserTemporaryAccountsByIPLookupTest extends MediaWikiIntegrationTestCase {
	use CheckUserTempUserTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableAutoCreateTempUser();
	}

	public function addDBDataOnce() {
		$this->enableAutoCreateTempUser();

		// Create some temp accounts and edits on different IPs:
		// This temp account edits from 2 IPv4 IPs
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		$tempUser1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-01', new FauxRequest() )->getUser();
		$this->editPage(
			'Test page', 'Test Content 1A', 'test', NS_MAIN, $tempUser1
		);
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 1B', 'test', NS_MAIN, $tempUser1
		);

		// This temp account is created from $tempUser1's second edit IP and edits
		// from there and also from an IPv6 IP
		$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-02', new FauxRequest() )->getUser();
		$this->editPage(
			'Test page', 'Test Content 2A', 'test', NS_MAIN, $tempUser2
		);
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:1' );
		$this->editPage(
			'Test page', 'Test Content 2B', 'test', NS_MAIN, $tempUser2
		);

		// Finally, This temp account edits from a different IPv6 IP
		// but in the same 64 range as the second temp user as well and
		// repeatedly from an IPv6 IP on a different range
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:2' );
		$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-03', new FauxRequest() )->getUser();
		$this->editPage(
			'Test page', 'Test Content 3A', 'test', NS_MAIN, $tempUser3
		);
		RequestContext::getMain()->getRequest()->setIP( '2:2:2:2:2:2:2:2' );
		$this->editPage(
			'Test page', 'Test Content 3B', 'test', NS_MAIN, $tempUser3
		);
		$this->editPage(
			'Test page', 'Test Content 3C', 'test', NS_MAIN, $tempUser3
		);
	}

	/**
	 * @dataProvider provideTestExecutegetTempAccountsFromIPAddress
	 */
	public function testExecutegetTempAccountsFromIPAddress( $ip, $limit, $expectedCount, $expectedAccounts ) {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$accounts = $checkUserTemporaryAccountsByIPLookup->getTempAccountsFromIPAddress( $ip, $limit );

		// Assert count of results
		$this->assertCount( $expectedCount, $accounts );

		// Assert accounts were returned as expected
		$this->assertArrayEquals( $expectedAccounts, $accounts );
	}

	public static function provideTestExecutegetTempAccountsFromIPAddress() {
		return [
			'Base case - Single IP, single account' => [
				'ip' => '127.0.0.1',
				'limit' => null,
				'expectedRowCount' => 1,
				'expectedAccounts' => [ '~check-user-test-01' ],
			],
			'Mutiple accounts found - Single IP, multiple accounts' => [
				'ip' => '127.0.0.2',
				'limit' => null,
				'expectedCount' => 2,
				'expectedAccounts' => [ '~check-user-test-01', '~check-user-test-02' ],
			],
			'No results' => [
				'ip' => '127.0.0.64',
				'limit' => null,
				'expectedCount' => 0,
				'expectedAccounts' => [],
			],
			'Range search - IPv6 range, multiple accounts' => [
				'ip' => '1:1:1:1:1:1:1:64/64',
				'limit' => null,
				'expectedCount' => 2,
				'expectedAccounts' => [ '~check-user-test-02', '~check-user-test-03' ],
			],
			'Accounts returned are unique - IPv6 range, single account, multiple edits' => [
				'ip' => '2:2:2:2:2:2:2:2/64',
				'limit' => null,
				'expectedCount' => 1,
				'expectedAccounts' => [ '~check-user-test-03' ],
			],
			'Limit parameter is respected' => [
				'ip' => '127.0.0.1',
				'limit' => 0,
				'expectedRowCount' => 1,
				'expectedAccounts' => [ '~check-user-test-01' ],
			],
		];
	}

	public function testInvalidArgumentgetTempAccountsFromIPAddress() {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$this->expectException( InvalidArgumentException::class );

		// Assert usernames are not allowed, existing or not
		$checkUserTemporaryAccountsByIPLookup->getTempAccountsFromIPAddress( 'User 1' );
	}

	public function getObjectUnderTest() {
		/** @var CheckUserTemporaryAccountsByIPLookup $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountsByIPLookup' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		return $objectUnderTest;
	}
}
