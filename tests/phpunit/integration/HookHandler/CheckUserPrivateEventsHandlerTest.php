<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use LogEntryBase;
use MailAddress;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\CheckUser\EncryptedData;
use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler
 * @group Database
 * @group CheckUser
 */
class CheckUserPrivateEventsHandlerTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use TempUserTestTrait;

	private function getObjectUnderTest(): CheckUserPrivateEventsHandler {
		return new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode()
		);
	}

	/**
	 * Re-define the CheckUserInsert service to expect no calls to any of its methods.
	 * This is done to assert that no inserts to the database occur instead of having
	 * to assert a row count of zero.
	 *
	 * @return void
	 */
	private function expectNoCheckUserInsertCalls() {
		$this->setService( 'CheckUserInsert', function () {
			return $this->createNoOpMock( CheckUserInsert::class );
		} );
	}

	public function testUserLogoutComplete() {
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		$testUser = $this->getTestUser()->getUserIdentity();
		$html = '';
		$this->getObjectUnderTest()->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			$testUser->getName()
		);
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'Should have logged the event to cu_private_event'
		);
	}

	public function testUserLogoutCompleteInvalidUser() {
		$this->expectNoCheckUserInsertCalls();
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		$html = '';
		$this->getObjectUnderTest()->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			'Nonexisting test user1234567'
		);
	}

	private function doTestOnAuthManagerLoginAuthenticateAudit(
		AuthenticationResponse $authResp, User $userObj,
		string $userName, bool $isAnonPerformer, string $expectedLogAction
	): void {
		if ( $isAnonPerformer ) {
			$this->disableAutoCreateTempUser();
		}
		$this->getObjectUnderTest()->onAuthManagerLoginAuthenticateAudit( $authResp, $userObj, $userName, [] );
		$expectedValues = [ NS_USER, $userName ];
		if ( $isAnonPerformer ) {
			$expectedValues[] = 0;
			$expectedValues[] = RequestContext::getMain()->getRequest()->getIP();
		} else {
			$expectedValues[] = $userObj->getId();
			$expectedValues[] = $userName;
		}
		$expectedValues[] = LogEntryBase::makeParamBlob( [ '4::target' => $userName ] );
		$expectedValues[] = $expectedLogAction;
		$this->newSelectQueryBuilder()
			->select( [ 'cupe_namespace', 'cupe_title', 'actor_user', 'actor_name', 'cupe_params', 'cupe_log_action' ] )
			->from( 'cu_private_event' )
			->join( 'actor', null, 'actor_id=cupe_actor' )
			->assertRowValue( $expectedValues );
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAudit */
	public function testOnAuthManagerLoginAuthenticateAudit(
		string $authStatus, string $expectedLogAction, bool $isAnonPerformer, array $userGroups
	) {
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserLogSuccessfulBotLogins' => true,
		] );
		$userObj = $this->getTestUser( $userGroups )->getUser();
		$userName = $userObj->getName();
		$authResp = $this->getMockAuthenticationResponseForStatus( $authStatus, $userName );

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, $isAnonPerformer, $expectedLogAction
		);
	}

	public static function provideOnAuthManagerLoginAuthenticateAudit() {
		return [
			'successful login' => [ AuthenticationResponse::PASS, 'login-success', false, [] ],
			'failed login' => [ AuthenticationResponse::FAIL, 'login-failure', true, [] ],
			'successful bot login' => [ AuthenticationResponse::PASS, 'login-success', false, [ 'bot' ] ],
		];
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled */
	public function testOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled(
		array $authFailReasons, bool $existingUser, string $expectedLogAction, bool $isAnonPerformer
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$authResp = AuthenticationResponse::newFail(
			$this->createMock( Message::class ),
			$authFailReasons
		);

		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserLogSuccessfulBotLogins' => true,
		] );
		$userObj = $existingUser
			? $this->getTestUser()->getUser()
			: $this->getServiceContainer()->getUserFactory()->newFromName( wfRandomString() );
		$userName = $userObj->getName();

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, $isAnonPerformer, $expectedLogAction
		);
	}

	public static function provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled() {
		return [
			'failed login with correct password' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD, but cannot be referenced
				//  directly due to T321864
				[ "good password" ],
				true,
				'login-failure-with-good-password',
				true,
			],
			'failed login with the correct password but locked and no local account' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
				//  respectively but cannot be referenced directly due to T321864
				[ "good password", "locked" ],
				false,
				'login-failure-with-good-password',
				true,
			],
			'failed login with correct password but locked' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
				//  respectively but cannot be referenced directly due to T321864
				[ "good password", "locked" ],
				true,
				'login-failure-with-good-password',
				false,
			],
		];
	}

	private function getMockAuthenticationResponseForStatus( $status, $user = 'test' ) {
		$req = $this->getMockForAbstractClass( AuthenticationRequest::class );
		switch ( $status ) {
			case AuthenticationResponse::PASS:
				return AuthenticationResponse::newPass( $user );
			case AuthenticationResponse::FAIL:
				return AuthenticationResponse::newFail( $this->createMock( Message::class ) );
			case AuthenticationResponse::ABSTAIN:
				return AuthenticationResponse::newAbstain();
			case AuthenticationResponse::REDIRECT:
				return AuthenticationResponse::newRedirect( [ $req ], '' );
			case AuthenticationResponse::RESTART:
				return AuthenticationResponse::newRestart( $this->createMock( Message::class ) );
			case AuthenticationResponse::UI:
				return AuthenticationResponse::newUI( [ $req ], $this->createMock( Message::class ) );
			default:
				$this->fail( 'No AuthenticationResponse mock was defined for the status ' . $status );
		}
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAuditNoSave */
	public function testOnAuthManagerLoginAuthenticateAuditNoSave(
		string $status, bool $validUser, array $userGroups, bool $logLogins, bool $logBots
	) {
		$this->expectNoCheckUserInsertCalls();
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => $logLogins,
			'CheckUserLogSuccessfulBotLogins' => $logBots,
		] );
		if ( $validUser ) {
			$userObj = $this->getTestUser( $userGroups )->getUser();
			$userName = $userObj->getName();
		} else {
			$userObj = null;
			$userName = '';
		}
		$ret = $this->getMockAuthenticationResponseForStatus( $status, $userName );
		$this->getObjectUnderTest()->onAuthManagerLoginAuthenticateAudit( $ret, $userObj, $userName, [] );
	}

	public static function provideOnAuthManagerLoginAuthenticateAuditNoSave() {
		return [
			'invalid user' => [ AuthenticationResponse::PASS, false, [], true, true ],
			'Abstain authentication response' => [ AuthenticationResponse::ABSTAIN, true, [], true, true ],
			'Redirect authentication response' => [ AuthenticationResponse::REDIRECT, true, [], true, true ],
			'UI authentication response' => [ AuthenticationResponse::UI, true, [], true, true ],
			'Restart authentication response' => [ AuthenticationResponse::RESTART, true, [], true, true ],
			'LogLogins set to false' => [ AuthenticationResponse::PASS, true, [], false, true ],
			'Successful authentication for bot account with wgCheckUserLogSuccessfulBotLogins set to false' => [
				AuthenticationResponse::PASS, true, [ 'bot' ], true, false,
			],
		];
	}

	/** @dataProvider provideOnEmailUserInvalidUsernames */
	public function testOnEmailUserForInvalidUsername( $toUsername, $fromUsername ) {
		$this->expectNoCheckUserInsertCalls();
		// Call the method under test
		$to = new MailAddress( 'test@test.com', $toUsername );
		$from = new MailAddress( 'testing@test.com', $fromUsername );
		$subject = 'Test';
		$text = 'Test';
		$error = false;
		$this->getObjectUnderTest()->onEmailUser( $to, $from, $subject, $text, $error );
		// Run DeferredUpdates as the private event is created in a DeferredUpdate.
		DeferredUpdates::doUpdates();
	}

	public static function provideOnEmailUserInvalidUsernames() {
		return [
			'Invalid from username' => [ 'ValidToUsername', 'Template:InvalidFromUsername#test' ],
			'Invalid to username' => [ 'Template:InvalidToUsername#test', 'ValidToUsername' ],
		];
	}

	private function commonOnEmailUser( MailAddress $to, MailAddress $from, array $cuPrivateWhere ) {
		// Call the method under test with the provided arguments and some mock arguments that are unused.
		$subject = 'Test subject';
		$text = 'Test text';
		$error = false;
		$this->getObjectUnderTest()->onEmailUser( $to, $from, $subject, $text, $error );
		// Run DeferredUpdates as the private event is created in a DeferredUpdate.
		DeferredUpdates::doUpdates();
		// Assert that the row was inserted with the correct data.
		$this->assertRowCount(
			1, 'cu_private_event', '*',
			'A row was not inserted with the correct data',
			array_merge( $cuPrivateWhere, [ 'cupe_namespace' => NS_USER ] )
		);
	}

	public function testOnEmailUserFrom() {
		// Verify that the user who sent the email is marked as the performer and their userpage is the title
		// associated with the event.
		$userTo = $this->getTestUser()->getUserIdentity();
		$userFrom = $this->getTestSysop()->getUser();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[ 'cupe_actor' => $userFrom->getActorId(), 'cupe_title' => $userFrom->getName() ]
		);
	}

	/** @covers \MediaWiki\CheckUser\EncryptedData */
	public function testOnEmailWithCUPublicKeyDefined() {
		// Generate a private/public key-pair to use in the test. This is needed to allow checking that the encrypted
		// data that is stored in the database can be decrypted and the decrypted data is correct.
		$privateKey = openssl_pkey_new( [
			'digest_alg' => 'rc4', 'private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA,
		] );
		$this->overrideConfigValue( 'CUPublicKey', openssl_pkey_get_details( $privateKey )['key'] );
		// Run the method under test.
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[]
		);
		// Load the EncryptedData object from the database.
		$encryptedData = unserialize(
			$this->newSelectQueryBuilder()
				->select( 'cupe_private' )
				->from( 'cu_private_event' )
				->caller( __METHOD__ )
				->fetchField()
		);
		$this->assertInstanceOf( EncryptedData::class, $encryptedData );
		// Check that the plaintext data remains the same after an encryption and decryption cycle.
		// This also checks that the plaintext data being encrypted by the method under test is as expected.
		$this->assertSame(
			$userTo->getEmail() . ':' . $userTo->getId(),
			$encryptedData->getPlaintext( $privateKey ),
			'The encrypted data for a user email event could not be decrypted or was incorrect.'
		);
	}

	public function testOnEmailUserLogParams() {
		// Verify that the log params for the email event contains a hash.
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[
				$this->getDb()->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
					$this->getDb()->anyString(),
					'4::hash',
					$this->getDb()->anyString()
				) )
			]
		);
	}

	public function testOnUser__mailPasswordInternal() {
		$performer = $this->getTestUser()->getUser();
		$account = $this->getTestSysop()->getUser();
		$this->getObjectUnderTest()->onUser__mailPasswordInternal( $performer, 'IGNORED', $account );
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'The row was not inserted or was inserted with the wrong data',
			[
				'cupe_actor' => $performer->getActorId(),
				'cupe_namespace' => NS_USER,
				'cupe_title' => $account->getName(),
				$this->getDb()->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
					$this->getDb()->anyString(),
					'"4::receiver"',
					$this->getDb()->anyString(),
					$account->getName(),
					$this->getDb()->anyString()
				) )
			]
		);
	}

	/** @dataProvider provideOnLocalUserCreated */
	public function testOnLocalUserCreated( bool $autocreated ) {
		// Set wgNewUserLog to false to ensure that the private event is added when $autocreated is false.
		// The behaviour when wgNewUserLog is true is tested elsewhere.
		$this->overrideConfigValue( MainConfigNames::NewUserLog, false );
		$user = $this->getTestUser()->getUser();
		$this->getObjectUnderTest()->onLocalUserCreated( $user, $autocreated );
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'The row was not inserted or was inserted with the wrong data',
			[
				'cupe_actor'  => $user->getActorId(),
				'cupe_namespace' => NS_USER,
				'cupe_title' => $user->getName(),
				'cupe_log_action' => $autocreated ? 'autocreate-account' : 'create-account'
			]
		);
	}

	public static function provideOnLocalUserCreated() {
		return [
			'New user was autocreated' => [ true ],
			'New user was not autocreated' => [ false ]
		];
	}

	public function testOnLocalUserCreatedWhenNewUsersLogRestricted() {
		// Set wgNewUserLog to true but restrict the newusers log to users with the 'suppressionlog' right
		$this->overrideConfigValue( MainConfigNames::NewUserLog, true );
		$this->overrideConfigValue( MainConfigNames::LogRestrictions, [ 'newusers' => 'suppressionlog' ] );
		$user = $this->getTestUser()->getUser();
		$this->getObjectUnderTest()->onLocalUserCreated( $user, false );
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'The row was not inserted or was inserted with the wrong data',
			[
				'cupe_actor'  => $user->getActorId(),
				'cupe_namespace' => NS_USER,
				'cupe_title' => $user->getName(),
				'cupe_log_action' => 'create-account'
			]
		);
	}
}
