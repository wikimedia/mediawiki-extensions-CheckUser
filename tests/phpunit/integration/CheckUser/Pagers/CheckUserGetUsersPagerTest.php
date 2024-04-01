<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers\Mocks\MockTemplateParser;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserGetUsersPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager
 */
class CheckUserGetUsersPagerTest extends CheckUserPagerTestBase {
	use CheckUserClientHintsCommonTraitTest;

	protected function setUp(): void {
		parent::setUp();

		$this->checkSubtype = SpecialCheckUser::SUBTYPE_GET_USERS;
		$this->defaultUserIdentity = UserIdentityValue::newAnonymous( '127.0.0.1' );
		$this->defaultCheckType = 'ipusers';
	}

	/** @dataProvider provideFormatUserRow */
	public function testFormatUserRow(
		$userSets, $userText, $clientHintsLookupResults, $displayClientHints, $expectedTemplateParams
	) {
		$objectUnderTest = $this->setUpObject();
		$objectUnderTest->templateParser = new MockTemplateParser();
		$objectUnderTest->userSets = $userSets;
		$objectUnderTest->clientHintsLookupResults = $clientHintsLookupResults;
		$objectUnderTest->displayClientHints = $displayClientHints;
		$objectUnderTest->formatUserRow( $userText );
		$this->assertNotNull(
			$objectUnderTest->templateParser->lastCalledWith,
			'The template parser was not called by ::formatUserRow.'
		);
		$this->assertSame(
			'GetUsersLine',
			$objectUnderTest->templateParser->lastCalledWith[0],
			'::formatUserRow did not call the correct mustache file.'
		);
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$objectUnderTest->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
		);
	}

	public function testFormatUserRowWithClientHintsEnabled() {
		$smallestFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 1600
		);
		$largestFakeTimestamp = ConvertibleTimestamp::now();
		/** @var UserAgentClientHintsFormatter $clientHintsFormatter */
		$clientHintsFormatter = $this->getServiceContainer()->get( 'UserAgentClientHintsFormatter' );
		$exampleClientHintsDataObject = self::getExampleClientHintsDataObjectFromJsApi();
		$formattedExampleClientHintsDataObject = $clientHintsFormatter
			->formatClientHintsDataObject( $exampleClientHintsDataObject );
		$this->testFormatUserRow(
			[
				'first' => [ '127.0.0.1' => $smallestFakeTimestamp ],
				'last' => [ '127.0.0.1' => $largestFakeTimestamp ],
				'edits' => [ '127.0.0.1' => 123 ],
				'ids' => [ '127.0.0.1' => 0 ],
				'infosets' => [ '127.0.0.1' => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
				'agentsets' => [ '127.0.0.1' => [ 'Testing user agent', 'Testing useragent2' ] ],
				'clienthints' => [
					'127.0.0.1' => new ClientHintsReferenceIds( [
						UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
						UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 123, 2 ],
					] ),
				],
			],
			'127.0.0.1',
			new ClientHintsLookupResults(
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [
						1 => 0
					],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [
						123 => 0
					]
				],
				[
					0 => $exampleClientHintsDataObject,
				]
			),
			true,
			[
				'userText' => '127.0.0.1',
				'editCount' => 123,
				'agentsList' => [ 'Testing useragent2', 'Testing user agent' ],
				'clientHintsList' => [ $formattedExampleClientHintsDataObject ]
			]
		);
	}

	public static function provideFormatUserRow() {
		// @todo Test more template parameters.
		$smallestFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 1600
		);
		$largestFakeTimestamp = ConvertibleTimestamp::now();
		return [
			'Row for IP address' => [
				// $object->userSets
				[
					'first' => [ '127.0.0.1' => $smallestFakeTimestamp ],
					'last' => [ '127.0.0.1' => $largestFakeTimestamp ],
					'edits' => [ '127.0.0.1' => 123 ],
					'ids' => [ '127.0.0.1' => 0 ],
					'infosets' => [ '127.0.0.1' => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
					'agentsets' => [ '127.0.0.1' => [ 'Testing user agent', 'Testing useragent2' ] ],
					'clienthints' => [ '127.0.0.1' => new ClientHintsReferenceIds( [
						UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
					] ) ],
				],
				// $user_text parameter
				'127.0.0.1',
				// $object->clientHintsLookupResults
				new ClientHintsLookupResults( [], [] ),
				// Should Client Hints be displayed.
				true,
				// Expected template parameters.
				[
					'userText' => '127.0.0.1',
					'editCount' => 123,
					'agentsList' => [ 'Testing useragent2', 'Testing user agent' ]
				]
			],
		];
	}

	/** @dataProvider provideFormatUserRowWithUsernameHidden */
	public function testFormatUserRowWithUsernameHidden( $authorityCanSeeUser ) {
		// Get a test user and then block it with 'hideuser' enabled.
		$hiddenUser = $this->getMutableTestUser()->getUser();
		$blockingUser = $this->getTestUser( [ 'sysop', 'suppress' ] )->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser, $blockingUser, 'infinity',
				'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );

		$smallestFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 1600
		);
		$largestFakeTimestamp = ConvertibleTimestamp::now();
		$objectUnderTest = $this->setUpObject();

		// Set the user who is viewing the row in the results.
		$viewUserGroups = [ 'checkuser' ];
		if ( $authorityCanSeeUser ) {
			$viewUserGroups[] = 'suppress';
		}
		RequestContext::getMain()->setUser( $this->getTestUser( $viewUserGroups )->getUser() );

		$objectUnderTest->templateParser = new MockTemplateParser();
		$objectUnderTest->userSets = [
			'first' => [ $hiddenUser->getName() => $smallestFakeTimestamp ],
			'last' => [ $hiddenUser->getName() => $largestFakeTimestamp ],
			'edits' => [ $hiddenUser->getName() => 123 ],
			'ids' => [ $hiddenUser->getName() => $hiddenUser->getId() ],
			'infosets' => [ $hiddenUser->getName() => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
			'agentsets' => [ $hiddenUser->getName() => [ 'Testing user agent', 'Testing useragent2' ] ],
			'clienthints' => [ $hiddenUser->getName() => new ClientHintsReferenceIds( [
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
			] ) ],
		];
		$objectUnderTest->clientHintsLookupResults = new ClientHintsLookupResults( [], [] );
		$objectUnderTest->displayClientHints = true;
		$objectUnderTest->formatUserRow( $hiddenUser->getName() );
		$this->assertNotNull(
			$objectUnderTest->templateParser->lastCalledWith,
			'The template parser was not called by ::formatUserRow.'
		);
		$this->assertSame(
			'GetUsersLine',
			$objectUnderTest->templateParser->lastCalledWith[0],
			'::formatUserRow did not call the correct mustache file.'
		);
		$expectedTemplateParams = [
			'userText' => $authorityCanSeeUser ? $hiddenUser->getName() : '',
			'editCount' => 123,
			'agentsList' => [ 'Testing useragent2', 'Testing user agent' ],
			'clientHintsList' => []
		];
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$objectUnderTest->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
		);
	}

	public static function provideFormatUserRowWithUsernameHidden() {
		return [
			'Authority can see hidden user' => [ true ],
			'Authority cannot see hidden user' => [ false ],
		];
	}

	/** @dataProvider provideFormatUserRowCanPerformBlocks */
	public function testFormatUserRowCanPerformBlocks( $canPerformBlocks ) {
		$objectUnderTest = $this->setUpObject();
		$objectUnderTest->canPerformBlocks = $canPerformBlocks;
		$timestamp = ConvertibleTimestamp::now();
		$objectUnderTest->templateParser = new MockTemplateParser();
		$objectUnderTest->userSets = [
			'first' => [ '127.0.0.1' => $timestamp ],
			'last' => [ '127.0.0.1' => $timestamp ],
			'edits' => [ '127.0.0.1' => 1 ],
			'ids' => [ '127.0.0.1' => 0 ],
			'infosets' => [ '127.0.0.1' => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
			'agentsets' => [ '127.0.0.1' => [ 'Testing user agent' ] ],
			'clienthints' => [],
		];
		$objectUnderTest->displayClientHints = false;
		$expectedTemplateParams = [
			'canPerformBlocks' => $canPerformBlocks,
			'userText' => '127.0.0.1',
			'editCount' => 1,
			'agentsList' => [ 'Testing user agent' ]
		];
		$objectUnderTest->formatUserRow( '127.0.0.1' );
		$this->assertNotNull(
			$objectUnderTest->templateParser->lastCalledWith,
			'The template parser was not called by ::formatUserRow.'
		);
		$this->assertSame(
			'GetUsersLine',
			$objectUnderTest->templateParser->lastCalledWith[0],
			'::formatUserRow did not call the correct mustache file.'
		);
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$objectUnderTest->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
		);
	}

	public static function provideFormatUserRowCanPerformBlocks() {
		return [
			'Can perform blocks' => [ true ],
			'Cannot perform blocks' => [ false ],
		];
	}

	/** @dataProvider provideGetQueryInfo */
	public function testGetQueryInfo( $xfor, $table, $expectedQueryInfo ) {
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 16, 'IPv6' => 19 ] );
		$target = UserIdentityValue::newAnonymous( '127.0.0.1' );
		// Add the IExpression for the IP target as a string to the expected query info for comparison.
		$expectedQueryInfo['conds'][] = $this->getServiceContainer()->get( 'CheckUserLookupUtils' )
			->getIPTargetExpr( $target, $xfor, $table )
			->toSql( $this->getDb() );
		$this->commonTestGetQueryInfo( $target, $xfor, $table, $expectedQueryInfo );
	}

	public static function provideGetQueryInfo() {
		return [
			'cu_changes table' => [
				// The xfor property of the object (false for normal IP address or true for XFF IP)
				false,
				// The $table argument to ::getQueryInfo
				'cu_changes',
				// The expected query info returned by ::getQueryInfo (we are only interested in testing the query info
				// added by ::getQueryInfo and not the info added by the table specific methods).
				[
					'tables' => [ 'cu_changes' ],
					'conds' => [ 'cuc_only_for_read_old' => 0 ],
					'options' => [ 'USE INDEX' => [ 'cu_changes' => 'cuc_ip_hex_time' ] ],
					// Verify that fields and join_conds set as arrays, but we are not testing their values.
					'fields' => [], 'join_conds' => [],
				]
			],
			'cu_log_event table' => [
				false, 'cu_log_event',
				[
					'tables' => [ 'cu_log_event' ],
					'conds' => [],
					'options' => [ 'USE INDEX' => [ 'cu_log_event' => 'cule_ip_hex_time' ] ],
					'fields' => [], 'join_conds' => [],
				]
			],
			'cu_private_event table' => [
				false, 'cu_private_event',
				[
					'tables' => [ 'cu_private_event' ],
					'conds' => [],
					'options' => [ 'USE INDEX' => [ 'cu_private_event' => 'cupe_ip_hex_time' ] ],
					'fields' => [], 'join_conds' => [],
				]
			],
			'cu_changes table with XFF IP' => [
				true, 'cu_changes',
				[
					'tables' => [ 'cu_changes' ],
					'conds' => [ 'cuc_only_for_read_old' => 0 ],
					'options' => [ 'USE INDEX' => [ 'cu_changes' => 'cuc_xff_hex_time' ] ],
					'fields' => [], 'join_conds' => [],
				]
			],
		];
	}

	/** @inheritDoc */
	protected function getDefaultRowFieldValues(): array {
		return [
			'timestamp' => ConvertibleTimestamp::now(),
			'ip' => '127.0.0.1',
			'agent' => '',
			'xff' => '',
			'user' => 0,
			'user_text' => '127.0.0.1',
		];
	}
}
