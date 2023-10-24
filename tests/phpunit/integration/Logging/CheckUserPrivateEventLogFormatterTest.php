<?php

namespace MediaWiki\CheckUser\Test\Integration\Logging;

use LogFormatterTestCase;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use RequestContext;

/**
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Logging\CheckUserPrivateEventLogFormatter
 */
class CheckUserPrivateEventLogFormatterTest extends LogFormatterTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[ 'ipblocks' ]
		);
	}

	use MockAuthorityTrait;

	public static function provideLogDatabaseRows(): array {
		$wikiName = MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::Sitename );
		return [
			'Successful login' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'login-success',
					'user_text' => 'Sysop',
					'params' => [
						'4::target' => 'UTSysop',
					],
				],
				'extra' => [
					'text' => "Successfully logged in to $wikiName as UTSysop",
					'api' => [
						'target' => 'UTSysop',
					],
				],
			],
			'Failed login' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'login-failure',
					'user_text' => 'Sysop',
					'params' => [
						'4::target' => 'UTSysop',
					],
				],
				'extra' => [
					'text' => "Failed to log in to $wikiName as UTSysop",
					'api' => [
						'target' => 'UTSysop',
					],
				],
			],
			'Failed login with correct password' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'login-failure-with-good-password',
					'user_text' => 'Sysop',
					'params' => [
						'4::target' => 'UTSysop',
					],
				],
				'extra' => [
					'text' => "Failed to log in to $wikiName as UTSysop but had the correct password",
					'api' => [
						'target' => 'UTSysop',
					],
				],
			],
			'User logout' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'user-logout',
					'user_text' => 'Sysop',
					'params' => [],
				],
				'extra' => [
					'text' => 'Successfully logged out using the API or Special:UserLogout',
					'api' => [],
				],
			],
			'Password reset email sent' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'password-reset-email-sent',
					'user_text' => 'Sysop',
					'params' => [
						'4::receiver' => 'UTSysop'
					],
				],
				'extra' => [
					'text' => 'sent a password reset email for user UTSysop',
					'api' => [
						'receiver' => 'UTSysop',
					],
				],
			],
			'Email sent' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'email-sent',
					'user_text' => 'Sysop',
					'params' => [
						'4::hash' => '1234567890abcdef'
					],
				],
				'extra' => [
					'text' => 'sent an email to user "1234567890abcdef"',
					'api' => [
						'hash' => '1234567890abcdef',
					],
				],
			],
			'User autocreated' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'autocreate-account',
					'user_text' => 'Sysop',
					'params' => [],
				],
				'extra' => [
					'text' => 'was automatically created',
					'api' => [],
				],
			],
			'User created' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'create-account',
					'user_text' => 'Sysop',
					'params' => [],
				],
				'extra' => [
					'text' => 'was created',
					'api' => [],
				],
			],
			'Migrated log event from cu_changes with plaintext actiontext' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'migrated-cu_changes-log-event',
					'user_text' => 'Sysop',
					'params' => [
						'4::actiontext' => 'Test plaintext action text [[test]]'
					],
				],
				'extra' => [
					// The testcase removes the HTML from the actual actiontext
					// as the message is parsed.
					'text' => 'Test plaintext action text test',
					'api' => [
						// Link is still present for the API, as API responses don't parse wikitext.
						'actiontext' => 'Test plaintext action text [[test]]'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider provideLogDatabaseRows
	 */
	public function testLogDatabaseRows( $row, $extra ) {
		$this->doTestLogFormatter( $row, $extra, [ 'checkuser' ] );
	}

	public function testLogDatabaseRowsForHiddenUserAndAuthorityHasSuppressGroup() {
		$testUser = $this->getMutableTestUser()->getUser();
		$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$testUser,
			$this->mockRegisteredUltimateAuthority(),
			'infinity',
			'block to hide the test user',
			[ 'isHideUser' => true ]
		)->placeBlock();
		$testUser->invalidateCache();
		$wikiName = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::Sitename );
		$this->doTestLogFormatter(
			[
				'type' => 'checkuser-private-event',
				'action' => 'login-success',
				'user_text' => $testUser->getName(),
				'params' => [
					'4::target' => $testUser->getName(),
				],
			],
			[
				'text' => "Successfully logged in to $wikiName as {$testUser->getName()}",
				'api' => [
					'target' => $testUser->getName(),
				],
			],
			[ 'checkuser', 'suppress' ]
		);
	}

	public function testLogDatabaseRowsForHiddenUser() {
		$testUser = $this->getMutableTestUser()->getUser();
		$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$testUser,
			$this->mockRegisteredUltimateAuthority(),
			'infinity',
			'block to hide the test user',
			[ 'isHideUser' => true ]
		)->placeBlock();
		$testUser->invalidateCache();
		$wikiName = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::Sitename );
		$usernameRemovedMessageText = RequestContext::getMain()->msg( 'rev-deleted-user' )->text();
		$this->doTestLogFormatter(
			[
				'type' => 'checkuser-private-event',
				'action' => 'login-success',
				'user_text' => $testUser->getName(),
				'params' => [
					'4::target' => $testUser->getName(),
				],
			],
			[
				'text' => "Successfully logged in to $wikiName as $usernameRemovedMessageText",
				'api' => [
					'target' => $usernameRemovedMessageText,
				],
			],
			[ 'checkuser' ]
		);
	}
}
