<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use Config;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Rest\Validator\UnsupportedContentTypeBodyValidator;
use MediaWiki\Revision\RevisionStore;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
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

	private Config $config;
	private RevisionStore $revisionStore;
	private UserAgentClientHintsManager $userAgentClientHintsManager;

	/**
	 * @param Config $config
	 * @param RevisionStore $revisionStore
	 * @param UserAgentClientHintsManager $userAgentClientHintsManager
	 */
	public function __construct(
		Config $config, RevisionStore $revisionStore, UserAgentClientHintsManager $userAgentClientHintsManager
	) {
		$this->config = $config;
		$this->revisionStore = $revisionStore;
		$this->userAgentClientHintsManager = $userAgentClientHintsManager;
	}

	public function run() {
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			// Pretend the route doesn't exist if the feature flag is off.
			throw new LocalizedHttpException(
				new MessageValue( 'rest-no-match' ), 404
			);
		}
		$user = $this->getAuthority()->getUser();
		$data = $this->getValidatedBody();
		$clientHints = ClientHintsData::newFromJsApi( $data );
		$type = $this->getValidatedParams()['type'];
		$identifier = $this->getValidatedParams()['id'];
		$associatedEntryTimestamp = "";
		if ( $type === 'revision' ) {
			$revision = $this->revisionStore->getRevisionById( $identifier );
			if ( !$revision ) {
				throw new LocalizedHttpException(
					new MessageValue( 'rest-nonexistent-revision', [ $identifier ] ), 404 );
			}
			if ( !$revision->getUser() || !$revision->getUser()->equals( $user ) ) {
				throw new LocalizedHttpException(
					new MessageValue(
						'checkuser-api-useragent-clienthints-revision-user-mismatch',
						[ $user->getId(), $identifier ]
					),
					401
				);
			}
			$associatedEntryTimestamp = $revision->getTimestamp();
		}
		// Check that the API was not called too long after the edit
		$cutoff = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - $this->config->get( 'CheckUserClientHintsRestApiMaxTimeLag' )
		);
		if ( $associatedEntryTimestamp < $cutoff ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-called-too-late',
					[ $type, $identifier ]
				),
				403
			);
		}

		$status = $this->userAgentClientHintsManager->insertClientHintValues( $clientHints, $identifier, $type );
		if ( !$status->isGood() ) {
			$error = $status->getErrors()[0];
			// A client hints mapping entry already exists.
			throw new LocalizedHttpException(
				new MessageValue( $error['message'], $error['params'][0] ),
				400
			);
		}

		$response = $this->getResponseFactory()->createJson( [
			"value" => $this->getResponseFactory()->formatMessage(
				new MessageValue( 'checkuser-api-useragent-clienthints-explanation' )
			)
		] );
		return $response;
	}

	/** @inheritDoc */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			return new UnsupportedContentTypeBodyValidator( $contentType );
		}
		$clientHintsHeadersJsApi = array_filter( array_values(
			$this->config->get( 'CheckUserClientHintsHeaders' )
		) );

		// These are always sent by the browser, so mark as required.
		$lowEntropyClientHints = [
			'brands' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'mobile' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'bool',
			],
			'platform' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];

		$highEntropyClientHints = [
			'architecture' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => in_array( 'architecture', $clientHintsHeadersJsApi )
			],
			'bitness' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => in_array( 'bitness', $clientHintsHeadersJsApi )
			],
			'fullVersionList' => [
				ParamValidator::PARAM_REQUIRED => in_array( 'fullVersionList', $clientHintsHeadersJsApi )
			],
			'model' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => in_array( 'model', $clientHintsHeadersJsApi )
			],
			'platformVersion' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => in_array( 'platformVersion', $clientHintsHeadersJsApi )
			]
		];
		$expectedJsonStructure = array_merge( $lowEntropyClientHints, $highEntropyClientHints );
		return new JsonBodyValidator( $expectedJsonStructure );
	}

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
			],
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

}
