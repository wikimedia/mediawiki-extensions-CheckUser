<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use Config;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Rest\Validator\UnsupportedContentTypeBodyValidator;
use MediaWiki\Revision\RevisionStore;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handler for POST requests to /checkuser/v0/useragent-clienthints/{revision}
 *
 * Intended to be called by the ext.checkUser.clientHints ResourceLoader module,
 * in response to 'postEdit' mw.hook events.
 */
class UserAgentClientHintsHandler extends SimpleHandler {

	private Config $config;
	private RevisionStore $revisionStore;

	public function __construct( Config $config, RevisionStore $revisionStore ) {
		$this->config = $config;
		$this->revisionStore = $revisionStore;
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
		$revisionId = $this->getValidatedParams()['revision'];
		$revision = $this->revisionStore->getRevisionById( $revisionId );
		if ( !$revision ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $revisionId ] ), 404 );
		}
		if ( !$revision->getUser() || !$revision->getUser()->equals( $user ) ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-revision-user-mismatch',
					[ $user->getId(), $revisionId ]
				),
				401
			);
		}
		// We probably don't need to return anything about the success/failure of storing the data (T258105).
		// Just return "true" for all cases for now.
		return true;
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
			'revision' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

}
