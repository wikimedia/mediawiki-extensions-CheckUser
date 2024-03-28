<?php

namespace MediaWiki\CheckUser\Api\CheckUser;

use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

abstract class ApiQueryCheckUserAbstractResponse implements CheckUserQueryInterface {

	protected ApiQueryCheckUser $module;
	/** @var string The target of the check */
	protected string $target;
	/** @var string The reason provided for the check. This wrapped in the checkuser-reason-api message. */
	protected string $reason;
	/** @var int The maximum number of results to return */
	protected int $limit;
	/**
	 * @var bool|null Null if the target is a username, true if the target is an IP,
	 *   false if the target is an XFF IP.
	 */
	protected ?bool $xff;
	/** @var string The cut-off timestamp in a format acceptable to the database */
	protected string $timeCutoff;

	/** @var bool Whether to read from the new event tables */
	protected bool $eventTableReadNew;

	protected IReadableDatabase $dbr;
	protected Config $config;
	protected CheckUserLogService $checkUserLogService;
	protected CheckUserLookupUtils $checkUserLookupUtils;

	/**
	 * @param ApiQueryCheckUser $module
	 * @param IConnectionProvider $dbProvider
	 * @param Config $config
	 * @param MessageLocalizer $messageLocalizer
	 * @param CheckUserLogService $checkUserLogService
	 * @param UserNameUtils $userNameUtils
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 *
	 * @internal Use CheckUserApiResponseFactory::newFromRequest() instead
	 */
	public function __construct(
		ApiQueryCheckUser $module,
		IConnectionProvider $dbProvider,
		Config $config,
		MessageLocalizer $messageLocalizer,
		CheckUserLogService $checkUserLogService,
		UserNameUtils $userNameUtils,
		CheckUserLookupUtils $checkUserLookupUtils
	) {
		$this->dbr = $dbProvider->getReplicaDatabase();
		$this->config = $config;
		$this->checkUserLogService = $checkUserLogService;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$requestParams = $module->extractRequestParams();

		// Validate that a non-empty reason was provided if the force summary configuration is enabled.
		$reason = trim( $requestParams['reason'] );
		if ( $this->config->get( 'CheckUserForceSummary' ) && $reason === '' ) {
			$module->dieWithError( 'apierror-checkuser-missingsummary', 'missingdata' );
		}

		// Wrap the reason in the checkuser-reason-api message, which adds the indication that the check was made using
		// the CheckUser API.
		$reason = $messageLocalizer->msg( 'checkuser-reason-api', $reason )->inContentLanguage()->escaped();

		// Parse the timecond parameter and validate that it produces a valid timestamp.
		$timeCutoff = strtotime( $requestParams['timecond'], ConvertibleTimestamp::time() );
		if ( !$timeCutoff || $timeCutoff < 0 || $timeCutoff > ConvertibleTimestamp::time() ) {
			$module->dieWithError( 'apierror-checkuser-timelimit', 'invalidtime' );
		}

		// Normalise the target parameter.
		$target = $requestParams['target'] ?? '';
		if ( IPUtils::isValid( $target ) ) {
			$target = IPUtils::sanitizeIP( $target ) ?? '';
		} elseif ( IPUtils::isValidRange( $target ) ) {
			$target = IPUtils::sanitizeRange( $target );
		} else {
			// Convert the username to a canonical form. Don't try to validate that the user exists as some response
			// classes may only expect a username (and as such we need to leave the validation to the classes which
			// extend this).
			$target = $userNameUtils->getCanonical( $target );
			if ( $target === false ) {
				$target = '';
			}
		}

		if ( IPUtils::isIPAddress( $target ) ) {
			// If the xff parameter was provided, then the target is an XFF IP. Otherwise, the target is an IP.
			$this->xff = isset( $requestParams['xff'] );
		} else {
			// If the target is not an IP, then the XFF parameter is not applicable (and therefore is null).
			$this->xff = null;
		}

		$this->module = $module;
		$this->target = $target;
		$this->reason = $reason;
		$this->timeCutoff = $this->dbr->timestamp( $timeCutoff );
		$this->limit = $requestParams['limit'];
		$this->eventTableReadNew = boolval(
			$this->config->get( 'CheckUserEventTablesMigrationStage' ) & SCHEMA_COMPAT_READ_NEW
		);
	}

	/**
	 * Generate the response array for the given request.
	 *
	 * @return array
	 */
	abstract public function getResponseData(): array;

	/**
	 * Return the type of request that this response is for, which is the value of the
	 * 'curequest' parameter provided to the API.
	 *
	 * @return string
	 */
	abstract public function getRequestType(): string;
}
