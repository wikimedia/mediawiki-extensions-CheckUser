<?php

namespace MediaWiki\CheckUser\HookHandler;

use LogEntryBase;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\Hook\AuthManagerLoginAuthenticateAuditHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\EncryptedData;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\EmailUserHook;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\Hook\User__mailPasswordInternalHook;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRigorOptions;
use Psr\Log\LoggerInterface;
use TypeError;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * Hooks into several hook handlers to create private checkuser events when certain actions occur.
 */
class CheckUserPrivateEventsHandler implements
	EmailUserHook,
	AuthManagerLoginAuthenticateAuditHook,
	LocalUserCreatedHook,
	UserLogoutCompleteHook,
	User__mailPasswordInternalHook
{

	private CheckUserInsert $checkUserInsert;
	private Config $config;
	private UserIdentityLookup $userIdentityLookup;
	private UserFactory $userFactory;
	private ReadOnlyMode $readOnlyMode;
	private UserAgentClientHintsManager $userAgentClientHintsManager;
	private LoggerInterface $logger;

	public function __construct(
		CheckUserInsert $checkUserInsert,
		Config $config,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		ReadOnlyMode $readOnlyMode,
		UserAgentClientHintsManager $userAgentClientHintsManager
	) {
		$this->checkUserInsert = $checkUserInsert;
		$this->config = $config;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userFactory = $userFactory;
		$this->readOnlyMode = $readOnlyMode;
		$this->userAgentClientHintsManager = $userAgentClientHintsManager;
		$this->logger = LoggerFactory::getInstance( 'CheckUser' );
	}

	/**
	 * Hook function to store registration and autocreation data
	 * Saves user data into the cu_changes table
	 *
	 * @param User $user
	 * @param bool $autocreated
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		// Don't add a private event if there will be an associated event in Special:RecentChanges,
		// otherwise this will be a duplicate.
		// The duplication would occur if the user was autocreated, $wgNewUserLog is true,
		// and the 'newusers' log is not restricted.
		$logRestrictions = $this->config->get( MainConfigNames::LogRestrictions );
		if (
			!$autocreated &&
			$this->config->get( MainConfigNames::NewUserLog ) &&
			!( array_key_exists( 'newusers', $logRestrictions ) && $logRestrictions['newusers'] !== '*' )
		) {
			return;
		}

		$this->checkUserInsert->insertIntoCuPrivateEventTable(
			[
				'cupe_namespace'  => NS_USER,
				'cupe_title'      => $user->getName(),
				// The following messages are generated here:
				// * logentry-checkuser-private-event-autocreate-account
				// * logentry-checkuser-private-event-create-account
				'cupe_log_action' => $autocreated ? 'autocreate-account' : 'create-account'
			],
			__METHOD__,
			$user
		);
	}

	/**
	 * Create a private checkuser event when a temporary password has been generated and emailed for a user.
	 *
	 * This does not indicate the password was successfully reset as
	 * all that is needed to trigger this is the username and email for
	 * an account.
	 *
	 * @inheritDoc
	 */
	public function onUser__mailPasswordInternal( $user, $ip, $account ): void {
		$insertedId = $this->checkUserInsert->insertIntoCuPrivateEventTable(
			[
				'cupe_namespace'  => NS_USER,
				'cupe_log_action' => 'password-reset-email-sent',
				'cupe_title'      => $account->getName(),
				'cupe_params'     => LogEntryBase::makeParamBlob( [ '4::receiver' => $account->getName() ] )
			],
			__METHOD__,
			$user
		);

		if ( $this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			RequestContext::getMain()->getOutput()->addJsConfigVars(
				'wgCheckUserClientHintsPrivateEventId', $insertedId
			);
		}
	}

	/**
	 * Creates a private checkuser event when an email is sent. This also stores:
	 * * A hash of the recipient of the email
	 * * If $wgCUPublicKey is valid, the "private" column will contain the recipient of the email
	 *   in an encrypted form.
	 *
	 * Uses a deferred update to save the event, because emails can be sent from code paths
	 * that don't open master connections.
	 *
	 * The private event is not stored if:
	 * * The wiki is in read only mode
	 * * The sender and recipient of the email are the same
	 * * No $wgSecretKey is specified.
	 *
	 * @inheritDoc
	 */
	public function onEmailUser( &$to, &$from, &$subject, &$text, &$error ) {
		if ( !$this->config->get( 'SecretKey' ) || $from->name === $to->name ) {
			return;
		}

		if ( $this->readOnlyMode->isReadOnly() ) {
			return;
		}

		$userFrom = $this->userIdentityLookup->getUserIdentityByName( $from->name );
		$userTo = $this->userFactory->newFromName( $to->name );

		if ( !$userFrom || !$userTo ) {
			return;
		}

		$hash = md5( $userTo->getEmail() . $userTo->getId() . $this->config->get( 'SecretKey' ) );

		// Define the title as the userpage of the user who sent the email. The user
		// who receives the email is private information, so cannot be used.
		$cuPrivateRow = [
			'cupe_namespace' => NS_USER,
			'cupe_title' => $userFrom->getName(),
			'cupe_log_action' => 'email-sent',
			'cupe_params' => LogEntryBase::makeParamBlob( [ '4::hash' => $hash ] ),
		];
		if ( trim( $this->config->get( 'CUPublicKey' ) ) !== '' ) {
			$privateData = $userTo->getEmail() . ":" . $userTo->getId();
			$encryptedData = new EncryptedData( $privateData, $this->config->get( 'CUPublicKey' ) );
			$cuPrivateRow['cupe_private'] = serialize( $encryptedData );
		}
		$insertedId = $this->checkUserInsert->insertIntoCuPrivateEventTable( $cuPrivateRow, __METHOD__, $userFrom );

		if ( $this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			RequestContext::getMain()->getOutput()->addJsConfigVars(
				'wgCheckUserClientHintsPrivateEventId', $insertedId
			);
		}
	}

	/**
	 * Creates a private checkuser event on failed and successful login attempts.
	 *
	 * No data is stored if $wgCheckUserLogLogins is false. Successful bot logins are not stored
	 * if $wgCheckUserLogSuccessfulBotLogins is false.
	 *
	 * @inheritDoc
	 */
	public function onAuthManagerLoginAuthenticateAudit( $ret, $user, $username, $extraData ) {
		if ( !$this->config->get( 'CheckUserLogLogins' ) ) {
			return;
		}

		if ( !$user && $username !== null ) {
			$user = $this->userFactory->newFromName( $username, UserRigorOptions::RIGOR_USABLE );
		}

		if ( !$user ) {
			return;
		}

		if (
			$this->config->get( 'CheckUserLogSuccessfulBotLogins' ) !== true &&
			$ret->status === AuthenticationResponse::PASS &&
			$user->isBot()
		) {
			return;
		}

		if ( $ret->status === AuthenticationResponse::FAIL ) {
			// The login attempt failed so use the IP as the performer.
			$logAction = 'login-failure';
			$performer = UserIdentityValue::newAnonymous( RequestContext::getMain()->getRequest()->getIP() );

			if (
				$ret->failReasons &&
				ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) &&
				in_array( CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD, $ret->failReasons )
			) {
				// If the password was correct, then say so in the shown message.
				$logAction = 'login-failure-with-good-password';

				if (
					in_array( CentralAuthUser::AUTHENTICATE_LOCKED, $ret->failReasons ) &&
					array_diff(
						$ret->failReasons,
						[ CentralAuthUser::AUTHENTICATE_LOCKED, CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD ]
					) === [] &&
					$user->isRegistered()
				) {
					// If
					//  * The user is locked
					//  * The password is correct
					//  * The user exists locally on this wiki
					//  * Nothing else caused the request to fail
					// then we can assume that if the account was not locked this login attempt
					// would have been successful. Therefore, mark the user as the performer
					// to indicate this information to the CheckUser and so it shows up when
					// checking the locked account.
					$performer = $user;
				}
			}
		} elseif ( $ret->status === AuthenticationResponse::PASS ) {
			$logAction = 'login-success';
			$performer = $user;
		} else {
			// Abstain, Redirect, etc.
			return;
		}

		$insertedId = $this->checkUserInsert->insertIntoCuPrivateEventTable(
			[
				'cupe_namespace'  => NS_USER,
				'cupe_title'      => $user->getName(),
				'cupe_log_action' => $logAction,
				'cupe_params'     => LogEntryBase::makeParamBlob( [ '4::target' => $user->getName() ] ),
			],
			__METHOD__,
			$performer
		);

		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			return;
		}

		$context = RequestContext::getMain();
		if ( $ret->status === AuthenticationResponse::FAIL ) {
			// If the login attempt was not successful, then ask for client hints data via the API on the next
			// page load as we can collect it easily as the Special:UserLogin page is loaded to show the error.
			$context->getOutput()->addJsConfigVars(
				'wgCheckUserClientHintsPrivateEventId', $insertedId
			);
		} else {
			// If the login attempt was a success, then we cannot use the API to collect the data due to redirects
			// that are performed as part of the login process. Instead, we should settle with the data sent to us
			// via the headers.
			$this->storeClientHintsDataFromHeaders( $insertedId, $context->getRequest() );
		}
	}

	/**
	 * Stores Client Hints data from the HTTP headers in the given $request and associate it with the
	 * given $privateEventId.
	 *
	 * @param int $privateEventId
	 * @param WebRequest $request
	 * @return void
	 */
	private function storeClientHintsDataFromHeaders( int $privateEventId, WebRequest $request ) {
		try {
			$clientHintsData = ClientHintsData::newFromRequestHeaders( $request );
			$this->userAgentClientHintsManager->insertClientHintValues(
				$clientHintsData, $privateEventId, 'privatelog'
			);
		} catch ( TypeError $e ) {
			$clientHintsHeaders = [];
			foreach ( array_keys( ClientHintsData::HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME ) as $header ) {
				$headerValue = $request->getHeader( $header );
				if ( $headerValue !== false ) {
					$clientHintsHeaders[$header] = $headerValue;
				}
			}
			$this->logger->warning(
				'Invalid data present in Client Hints headers when storing Client Hints data for private event ID ' .
				'{eventid}. Not storing this data. Client Hints headers: {headers}',
				[ $privateEventId, $clientHintsHeaders ]
			);
		}
	}

	/**
	 * Creates a private checkuser event when a user logs out.
	 *
	 * @inheritDoc
	 */
	public function onUserLogoutComplete( $user, &$inject_html, $oldName ) {
		if ( !$this->config->get( 'CheckUserLogLogins' ) ) {
			// Treat the log logins config as also applying to logging logouts.
			return;
		}

		$performer = $this->userIdentityLookup->getUserIdentityByName( $oldName );
		if ( $performer === null ) {
			return;
		}

		$this->checkUserInsert->insertIntoCuPrivateEventTable(
			[
				'cupe_namespace'  => NS_USER,
				'cupe_title'      => $oldName,
				// The following messages are generated here:
				// * logentry-checkuser-private-event-user-logout
				'cupe_log_action' => 'user-logout',
			],
			__METHOD__,
			$performer
		);
	}
}
