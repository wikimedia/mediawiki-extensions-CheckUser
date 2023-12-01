<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\Tests\TemplateParserMockTest;
use Wikimedia\IPUtils;

/**
 * Test class for CheckUserGetIPsPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager
 */
class CheckUserGetIPsPagerTest extends CheckUserPagerTestBase {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_changes',
				'cu_log_event',
				'cu_private_event',
				'cu_log',
			]
		);

		$this->checkSubtype = SpecialCheckUser::SUBTYPE_GET_IPS;
		$this->defaultUserIdentity = $this->getTestUser()->getUserIdentity();
		$this->defaultCheckType = 'userips';
	}

	/**
	 * Tests that the template parameters provided to the GetIPsLine.mustache match
	 * the expected values. Does not test the mustache file which includes some
	 * conditional logic, HTML and whitespace.
	 *
	 * @dataProvider provideFormatRow
	 */
	public function testFormatRow( $row, $expectedTemplateParams ) {
		$object = $this->setUpObject();
		$object->templateParser = new TemplateParserMockTest();
		$row = array_merge( $this->getDefaultRowFieldValues(), $row );
		$object->formatRow( (object)$row );
		$this->assertNotNull(
			$object->templateParser->lastCalledWith,
			'The template parser was not called by formatRow.'
		);
		$this->assertSame(
			'GetIPsLine',
			$object->templateParser->lastCalledWith[0],
			'formatRow did not call the correct mustache file.'
		);
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$object->templateParser->lastCalledWith[1],
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

	public static function provideFormatRow() {
		// @todo test the rest of the template parameters.
		return [
			'Test edit count' => [
				[ 'count' => 555 ],
				[ 'editCount' => 555 ]
			],
		];
	}

	/** @inheritDoc */
	public function getDefaultRowFieldValues(): array {
		return [
			'ip' => '127.0.0.1',
			'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
			'count' => 1,
			'first' => $this->db->timestamp(),
			'last' => $this->db->timestamp(),
		];
	}
}
