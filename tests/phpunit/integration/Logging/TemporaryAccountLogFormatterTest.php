<?php

namespace MediaWiki\CheckUser\Test\Integration\Logging;

use LogFormatterTestCase;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;

/**
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLogFormatter
 */
class TemporaryAccountLogFormatterTest extends LogFormatterTestCase {
	public function provideLogDatabaseRows(): array {
		return [
			'Enable access' => [
				'row' => [
					'type' => 'checkuser-temporary-account',
					'action' => TemporaryAccountLogger::ACTION_CHANGE_ACCESS,
					'user_text' => 'Sysop',
					'params' => [
						'4::changeType' => TemporaryAccountLogger::ACTION_ACCESS_ENABLED,
					],
				],
				'extra' => [
					'text' => 'Sysop enabled their own access to view IP addresses of temporary accounts',
					'api' => [
						'changeType' => TemporaryAccountLogger::ACTION_ACCESS_ENABLED,
					],
				],
			],
			'Disable access' => [
				'row' => [
					'type' => 'checkuser-temporary-account',
					'action' => TemporaryAccountLogger::ACTION_CHANGE_ACCESS,
					'user_text' => 'Sysop',
					'params' => [
						'4::changeType' => TemporaryAccountLogger::ACTION_ACCESS_DISABLED,
					],
				],
				'extra' => [
					'text' => 'Sysop disabled their own access to view IP addresses of temporary accounts',
					'api' => [
						'changeType' => TemporaryAccountLogger::ACTION_ACCESS_DISABLED,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provideLogDatabaseRows
	 */
	public function testLogDatabaseRows( $row, $extra ): void {
		$this->setGroupPermissions( 'sysop', 'checkuser-temporary-account-log', true );
		$this->doTestLogFormatter( $row, $extra, 'sysop' );
	}
}
