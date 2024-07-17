<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Rest\LocalizedHttpException;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handle temporary account name validation for endpoints that take an account name as a parameter
 */
abstract class AbstractTemporaryAccountNameHandler extends AbstractTemporaryAccountHandler {
	/**
	 * @inheritDoc
	 */
	public function getResults( $identifier ): array {
		if ( !$this->userNameUtils->isTemp( $identifier ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-invalid-user', [ $identifier ] ),
				404
			);
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$actorId = $this->actorStore->findActorIdByName( $identifier, $dbr );
		$userIdentity = $this->actorStore->getUserIdentityByName( $identifier );
		if ( $actorId === null || $userIdentity === null ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-user', [ $identifier ] ),
				404
			);
		}

		$blockOnTempAccount = $this->blockManager->getBlock( $userIdentity, null );
		if (
			$blockOnTempAccount &&
			$blockOnTempAccount->getHideName() &&
			!$this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'viewsuppressed' )
		) {
			if ( $this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'hideuser' ) ) {
				// The user knows that this user exists, because they have the 'hideuser' right. Instead of pretending
				// the user does not exist, we instead should inform the user that they don't have the
				// permission to view this information.
				throw new LocalizedHttpException(
					new MessageValue( 'checkuser-rest-access-denied' ),
					403
				);
			} else {
				// Pretend the username does not exist if the temporary account is hidden and the user does not have the
				// rights to see suppressed information or blocks with 'hideuser' set.
				throw new LocalizedHttpException(
					new MessageValue( 'rest-nonexistent-user', [ $identifier ] ),
					404
				);
			}
		}

		return $this->getData( $actorId, $dbr );
	}

	/**
	 * @inheritDoc
	 */
	protected function getLogType(): string {
		return TemporaryAccountLogger::ACTION_VIEW_IPS;
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
