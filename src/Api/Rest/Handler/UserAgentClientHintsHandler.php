<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsTrigger;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use TypeError;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Handler for POST requests to /checkuser/v0/useragent-clienthints/{type}/{id}
 *
 * Intended to be called by the ext.checkUser.clientHints ResourceLoader module,
 * in response to 'postEdit' mw.hook events.
 *
 * Eventually we can also use this endpoint for mapping data to CheckUser log events
 * in cu_log_event and cu_private_event.
 */
class UserAgentClientHintsHandler extends SimpleHandler {
	use TokenAwareHandlerTrait;

	public function __construct(
		private readonly Config $config,
		private readonly RevisionStore $revisionStore,
		private readonly UserAgentClientHintsManager $userAgentClientHintsManager,
		private readonly IConnectionProvider $dbProvider,
		private readonly ActorStore $actorStore,
		private readonly SuggestedInvestigationsTrigger $suggestedInvestigationsTrigger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function validate( Validator $restValidator ): void {
		parent::validate( $restValidator );
		// Allow anonymous token needs to be true as logged out users can make requests to
		// this endpoint via the ext.checkUser.clientHints ResourceLoader module.
		$this->validateToken( true );
	}

	/** @return Response */
	public function run() {
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			// Pretend the route doesn't exist if the feature flag is off.
			throw new LocalizedHttpException(
				new MessageValue( 'rest-no-match' ),
				404
			);
		}
		$data = $this->getValidatedBody();
		// $data should be an array, but can be null when validation
		// failed and/or when the content type was form data.
		if ( !is_array( $data ) ) {
			// Taken from Validator::validateBody
			[ $contentType ] = explode( ';', $this->getRequest()->getHeaderLine( 'Content-Type' ), 2 );
			$contentType = strtolower( trim( $contentType ) );
			if ( $contentType !== 'application/json' ) {
				// Same exception as used in UnsupportedContentTypeBodyValidator
				throw new LocalizedHttpException(
					new MessageValue( 'rest-unsupported-content-type', [ $contentType ] ),
					415
				);
			} else {
				// Should be caught by JsonBodyValidator::validateBody, but if this
				// point is reached a non-array still indicates a problem with the
				// data submitted by the client and thus a 400 error is appropriate.
				throw new LocalizedHttpException( new MessageValue( 'rest-bad-json-body' ), 400 );
			}
		}
		try {
			// ::newFromJsApi with the $data may raise a TypeError as the $data
			// does not have its type validated (T305973).
			$clientHints = ClientHintsData::newFromJsApi( $data );
		} catch ( TypeError ) {
			throw new LocalizedHttpException( new MessageValue( 'rest-bad-json-body' ), 400 );
		}
		$type = $this->getValidatedParams()['type'];
		$identifier = $this->getValidatedParams()['id'];
		if ( $type === 'revision' ) {
			$this->performValidationForRevision( $identifier );
		} elseif ( $type === 'privatelog' ) {
			$this->performValidationForPrivateLog( $identifier );
		} else {
			// If the type is not supported, pretend the route doesn't exist.
			throw new LocalizedHttpException(
				new MessageValue( 'rest-no-match' ),
				404
			);
		}
		$status = $this->userAgentClientHintsManager->insertClientHintValues( $clientHints, $identifier, $type );
		if ( !$status->isGood() ) {
			throw new LocalizedHttpException( MessageValue::newFromSpecifier( $status->getMessages()[0] ), 400 );
		}

		// Trigger SuggestedInvestigations if the user is registered
		$user = $this->getAuthority()->getUser();
		if ( $user->isRegistered() ) {
			$data = [
				'clientHints' => $clientHints->jsonSerialize(),
			];
			if ( $type === 'revision' ) {
				$data['revId'] = $identifier;
			} else {
				$data['cuPrivateLogId'] = $identifier;
			}
			$this->suggestedInvestigationsTrigger->matchSignalsAgainstUserInJob(
				$user,
				SuggestedInvestigationsSignalMatchService::EVENT_CLIENT_HINTS_SAVED,
				$data
			);
		}

		return $this->getResponseFactory()->createJson( [
			'value' => $this->getResponseFactory()->formatMessage(
				new MessageValue( 'checkuser-api-useragent-clienthints-explanation' )
			),
		] );
	}

	/**
	 * Check whether Client Hints data can be stored for the given revision ID.
	 * This method checks that the revision with this ID exists, was not made
	 * over wgCheckUserClientHintsRestApiMaxTimeLag seconds ago, and that the
	 * user making the request made the edit with this ID.
	 *
	 * @param int $revisionId The revision ID
	 * @return void
	 * @throws LocalizedHttpException If the checks fail, this exception will be raised.
	 */
	private function performValidationForRevision( int $revisionId ) {
		// Check the revision exists.
		$revision = $this->revisionStore->getRevisionById( $revisionId );
		if ( !$revision ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $revisionId ] ),
				404
			);
		}
		$this->performTimestampValidation( $revision->getTimestamp(), 'revision', $revisionId );
		// Check the performer of the action is the same as the user submitting this REST API request
		$user = $this->getAuthority()->getUser();
		if (
			!$revision->getUser( RevisionRecord::RAW ) ||
			!$revision->getUser( RevisionRecord::RAW )->equals( $user )
		) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-revision-user-mismatch',
					[ $user->getId(), $revisionId ]
				),
				401
			);
		}
	}

	/**
	 * Check whether Client Hints data can be stored for the private log event ID.
	 * This method checks that the revision with this private log event ID exists,
	 * was not made over wgCheckUserClientHintsRestApiMaxTimeLag seconds ago, and
	 * that the user making the request performed the private log.
	 *
	 * @param int $privateLogId The private log ID
	 * @return void
	 * @throws LocalizedHttpException If the checks fail, this exception will be raised.
	 */
	private function performValidationForPrivateLog( int $privateLogId ) {
		// Fetch details about the private event with ID $privateLogId
		$dbr = $this->dbProvider->getReplicaDatabase();
		$privateEventRow = $dbr->newSelectQueryBuilder()
			->select( [ 'cupe_timestamp', 'cupe_actor', 'cupe_ip_hex' ] )
			->from( 'cu_private_event' )
			->where( [ 'cupe_id' => $privateLogId ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $privateEventRow === false ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-nonexistent-id',
					[ 'privatelog', $privateLogId ]
				),
				404
			);
		}
		$this->performTimestampValidation( $privateEventRow->cupe_timestamp, 'privatelog', $privateLogId );
		// Check the performer of the action is the same as the user submitting this REST API request
		if ( $privateEventRow->cupe_actor === null && $privateEventRow->cupe_ip_hex !== null ) {
			// Use the IP as the user_text if the actor ID is NULL and the IP is not NULL (T353953).
			$performingUser = new UserIdentityValue(
				0,
				IPUtils::formatHex( $privateEventRow->cupe_ip_hex )
			);
		} else {
			$performingUser = $this->actorStore->getActorById( (int)$privateEventRow->cupe_actor, $dbr );
		}
		$user = $this->getAuthority()->getUser();
		if ( !$performingUser->equals( $user ) ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-revision-user-mismatch',
					[ $user->getId(), $privateLogId ]
				),
				401
			);
		}
	}

	/**
	 * Validate that the API was not called over wgCheckUserClientHintsRestApiMaxTimeLag
	 * seconds ago.
	 *
	 * @param ?string $associatedEntryTimestamp The timestamp associated with the $identifier in any format
	 *   accepted by {@link ConvertibleTimestamp}. If null, the validation will always fail.
	 * @param string $type The type of the $identifier (e.g. revision)
	 * @param int $identifier The ID of the entry of type $type
	 * @return void
	 * @throws LocalizedHttpException If the validation fails, this exception will be raised.
	 */
	private function performTimestampValidation(
		?string $associatedEntryTimestamp,
		string $type,
		int $identifier
	): void {
		// Check that the API was not called too long after the edit
		$cutoff = ConvertibleTimestamp::time() - $this->config->get( 'CheckUserClientHintsRestApiMaxTimeLag' );
		if (
			$associatedEntryTimestamp === null ||
			ConvertibleTimestamp::convert( TS_UNIX, $associatedEntryTimestamp ) < $cutoff
		) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-called-too-late',
					[ $type, $identifier ]
				),
				403
			);
		}
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return true;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => UserAgentClientHintsManager::SUPPORTED_TYPES,
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-type' ),
				self::PARAM_EXAMPLE => 'revision',
			],
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-id' ),
				self::PARAM_EXAMPLE => 12345,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		$settings = $this->getTokenParamDefinition();
		$settings['token'][self::PARAM_DESCRIPTION] = new MessageValue( 'checkuser-rest-request-property-desc-token' );
		$settings['token'][self::PARAM_EXAMPLE] = '+\\';

		return [
			'brands' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-brands' ),
				self::PARAM_EXAMPLE => [
					[ 'brand' => 'Not/A)Brand', 'version' => '99' ],
					[ 'brand' => 'Chromium', 'version' => '100' ],
					[ 'brand' => 'Google Chrome', 'version' => '100' ],
				],
			],
			'mobile' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'boolean',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-mobile' ),
				self::PARAM_EXAMPLE => false,
			],
			'platform' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-platform' ),
				self::PARAM_EXAMPLE => 'Windows',
			],
			'architecture' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-architecture' ),
				self::PARAM_EXAMPLE => 'x86',
			],
			'bitness' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-bitness' ),
				self::PARAM_EXAMPLE => '64',
			],
			'fullVersionList' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-fullversionlist' ),
				self::PARAM_EXAMPLE => [
					[ 'brand' => 'Not/A)Brand', 'version' => '99.0.0.0' ],
					[ 'brand' => 'Chromium', 'version' => '100.0.4896.75' ],
					[ 'brand' => 'Google Chrome', 'version' => '100.0.4896.75' ],
				],
			],
			'model' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-model' ),
				self::PARAM_EXAMPLE => 'OnePlus 9',
			],
			'platformVersion' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-platformversion' ),
				self::PARAM_EXAMPLE => '10.0.0',
			],
			// While this is deprecated and not requested by the JS code, some clients still send this value so we
			// need to define it as an acceptable parameter (T350316) to prevent the valid request from otherwise
			// failing.
			'uaFullVersion' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-clienthints-uafullversion' ),
				self::PARAM_EXAMPLE => '100.0.4896.75',
			],
		] + $settings;
	}

	/** @inheritDoc */
	public function getRequestBodyDescription(): MessageValue {
		return new MessageValue( 'checkuser-rest-request-desc-clienthints' );
	}

	/** @inheritDoc */
	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'x-i18n-description' => 'checkuser-rest-response-desc-clienthints',
			'properties' => [
				'value' => [
					'type' => 'string',
					'x-i18n-description' => 'checkuser-rest-property-desc-clienthints-value',
				],
			],
			'required' => [ 'value' ],
			'example' => [
				'value' => 'stored',
			],
		];
	}
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	UserAgentClientHintsHandler::class,
	'MediaWiki\\CheckUser\\Api\\Rest\\Handler\\UserAgentClientHintsHandler'
);
// @codeCoverageIgnoreEnd
