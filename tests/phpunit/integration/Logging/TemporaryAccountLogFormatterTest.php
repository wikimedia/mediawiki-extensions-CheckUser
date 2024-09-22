<?php

namespace MediaWiki\CheckUser\Tests\Integration\Logging;

use LogFormatterTestCase;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLogFormatter
 */
class TemporaryAccountLogFormatterTest extends LogFormatterTestCase {
	public static function provideLogDatabaseRows(): array {
		return [
			'Enable access' => [
				'row' => [
					'type' => 'checkuser-temporary-account',
					'action' => TemporaryAccountLogger::ACTION_CHANGE_ACCESS,
					'user_text' => 'Sysop', 'title' => 'Sysop', 'namespace' => NS_USER,
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
					'user_text' => 'Sysop', 'title' => 'Sysop', 'namespace' => NS_USER,
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
			'View IPs' => [
				'row' => [
					'type' => 'checkuser-temporary-account',
					'action' => TemporaryAccountLogger::ACTION_VIEW_IPS,
					'user_text' => 'Sysop', 'title' => '~2024-01', 'namespace' => NS_USER,
					'params' => [],
				],
				'extra' => [
					'text' => 'Sysop viewed IP addresses for ~2024-01',
					'api' => [],
				],
			],
			'View temporary accounts on a IP' => [
				'row' => [
					'type' => 'checkuser-temporary-account',
					'action' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP,
					'user_text' => 'Sysop', 'title' => '1.2.3.4', 'namespace' => NS_USER,
					'params' => [],
				],
				'extra' => [
					'text' => 'Sysop viewed temporary accounts on 1.2.3.4',
					'api' => [],
				],
			],
			'View temporary accounts on a IP range' => [
				'row' => [
					'type' => 'checkuser-temporary-account',
					'action' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP,
					'user_text' => 'Sysop', 'title' => '1.2.3.0/24', 'namespace' => NS_USER,
					'params' => [],
				],
				'extra' => [
					'text' => 'Sysop viewed temporary accounts on 1.2.3.0/24',
					'api' => [],
				],
			],
			'AbuseFilter external log - view protected variables' => [
				'row' => [
					'type' => 'checkuser-temporary-account',
					'action' => 'af-' . ProtectedVarsAccessLogger::ACTION_VIEW_PROTECTED_VARIABLE_VALUE,
					'user_text' => 'Sysop', 'title' => '1.2.3.0/24', 'namespace' => NS_USER,
					'params' => [],
				],
				'extra' => [
					'text' => 'Sysop viewed protected variables associated with 1.2.3.0/24',
					'api' => [],
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
