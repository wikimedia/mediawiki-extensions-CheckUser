<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Rest\Handler\Helper\RestAuthorizeTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\User\ActorStore;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @internal Intended only for use on Special:Block for the Suggested Investigations message
 */
class UserSummaryHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;
	use RestAuthorizeTrait;

	public function __construct(
		private readonly Config $config,
		private readonly ActorStore $actorStore,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookup,
	) {
	}

	/** @inheritDoc */
	public function validate( Validator $restValidator ): void {
		parent::validate( $restValidator );
		$this->validateToken();
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition();
	}

	/**
	 * @throws LocalizedHttpException
	 * @throws HttpException
	 */
	public function run(): Response {
		// We cannot easily conditionally enable REST API routes (unlike Special pages), so we should instead
		// return a 404. If it becomes possible to conditionally enable REST API routes based on config, that
		// method should be used instead.
		if ( !$this->config->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'checkuser-suggestedinvestigations-not-enabled' ),
				404
			);
		}
		$this->authorizeActionOrThrow( $this->getAuthority(), 'checkuser-suggested-investigations' );

		$targetUser = $this->actorStore->getUserIdentityByName( $this->getValidatedParams()['name'] );
		if ( !$targetUser ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'apierror-invaliduser' )
					->plaintextParams( $this->getValidatedParams()['name'] ),
				404
			);
		}

		// Return number of open cases involving the user
		$relatedCases = $this->caseLookup->getOpenCaseIdsForUser( $targetUser->getId() );

		// From those cases, derive all associated accounts which is
		// defined as any other account on the same case as the target user.
		$relatedUserIds = [];
		foreach ( $relatedCases as $caseId ) {
			$relatedUserIdsInCase = $this->caseLookup->getUserIdsInCase( $caseId );
			foreach ( $relatedUserIdsInCase as $relatedUserId ) {
				$relatedUserIds[ $relatedUserId ] = true;
			}
		}
		$relatedUserIds = array_keys( $relatedUserIds );

		// Don't count the target user as a related account
		$relatedUserIds = array_diff( $relatedUserIds, [ $targetUser->getId() ] );

		return $this->getResponseFactory()->createJson( [
			'relatedUserIdsCount' => count( $relatedUserIds ),
			'relatedCasesCount' => count( $relatedCases ),
		] );
	}

	/** @inheritDoc */
	public function getParamSettings(): array {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
