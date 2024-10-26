<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use CentralAuthSessionProvider;
use Exception;
use LogicException;
use MediaWiki\Api\ApiMain;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Site\SiteLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;

/**
 * Perform multiple API requests to external wikis. Much of this class is copied from ForeignWikiRequest
 * in the Echo extension.
 */
class CheckUserApiRequestAggregator {
	private HttpRequestFactory $httpRequestFactory;
	private CentralIdLookup $centralIdLookup;
	private ExtensionRegistry $extensionRegistry;
	private SiteLookup $siteLookup;
	private LoggerInterface $logger;
	private User $user;
	private array $params;
	private array $wikis;
	private int $authenticate;

	public const AUTHENTICATE_NONE = 0;
	public const AUTHENTICATE_CENTRAL_AUTH = 1;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param CentralIdLookup $centralIdLookup
	 * @param ExtensionRegistry $extensionRegistry
	 * @param SiteLookup $siteLookup
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		CentralIdLookup $centralIdLookup,
		ExtensionRegistry $extensionRegistry,
		SiteLookup $siteLookup,
		LoggerInterface $logger
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->centralIdLookup = $centralIdLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->siteLookup = $siteLookup;
		$this->logger = $logger;
	}

	/**
	 * Execute the request.
	 *
	 * @internal For use by GlobalContributionsPager.
	 * @param User $user
	 * @param array $params API request parameters
	 * @param string[] $wikis Wikis to send the request to
	 * @param WebRequest $originalRequest Original request data to be sent with these requests
	 * @param int $authenticate Authentication level needed - one of the self::AUTHENTICATE_* constants
	 * @return array[] [ wiki => result ] for all the wikis that returned results
	 */
	public function execute(
		User $user,
		array $params,
		array $wikis,
		WebRequest $originalRequest,
		int $authenticate = self::AUTHENTICATE_NONE
	) {
		$this->user = $user;
		$this->params = $params;
		$this->wikis = $wikis;
		$this->authenticate = $authenticate;

		if ( count( $this->wikis ) === 0 ) {
			return [];
		}

		if ( $this->authenticate === self::AUTHENTICATE_CENTRAL_AUTH && !$this->canUseCentralAuth() ) {
			throw new LogicException(
				"CentralAuth authentication is needed but not available"
			);
		}

		$reqs = $this->getRequestParams( $originalRequest );
		return $this->doRequests( $reqs );
	}

	/**
	 * @return int
	 */
	private function getCentralId() {
		return $this->centralIdLookup->centralIdFromLocalUser( $this->user, CentralIdLookup::AUDIENCE_RAW );
	}

	/**
	 * Check whether the user has a central ID via the CentralAuth extension
	 *
	 * Protected function for mocking in tests.
	 *
	 * @return bool
	 */
	protected function canUseCentralAuth() {
		return $this->extensionRegistry->isLoaded( 'CentralAuth' ) &&
			SessionManager::getGlobalSession()->getProvider() instanceof CentralAuthSessionProvider &&
			$this->getCentralId() !== 0;
	}

	/**
	 * Returns CentralAuth token, or null on failure.
	 *
	 * Protected function for mocking in tests.
	 *
	 * @return string|null
	 */
	protected function getCentralAuthToken() {
		$context = new RequestContext;
		$context->setRequest( new FauxRequest( [ 'action' => 'centralauthtoken' ] ) );
		$context->setUser( $this->user );

		$api = new ApiMain( $context );

		try {
			$api->execute();

			return $api->getResult()->getResultData( [ 'centralauthtoken', 'centralauthtoken' ] );
		} catch ( Exception $ex ) {
			$this->logger->error(
				'Exception when fetching CentralAuth token: wiki: {wiki}, userName: {userName}, ' .
					'userId: {userId}, centralId: {centralId}, exception: {exception}',
				[
					'wiki' => WikiMap::getCurrentWikiId(),
					'userName' => $this->user->getName(),
					'userId' => $this->user->getId(),
					'centralId' => $this->getCentralId(),
					'exception' => $ex,
				]
			);

			return null;
		}
	}

	/**
	 * @param WebRequest $originalRequest Original request data to be sent with these requests
	 * @return array[] Array of request parameters to pass to doRequests(), keyed by wiki name
	 */
	private function getRequestParams( WebRequest $originalRequest ) {
		$urls = [];
		foreach ( $this->wikis as $wiki ) {
			$site = $this->siteLookup->getSite( $wiki );
			if ( $site instanceof MediaWikiSite ) {
				$urls[$wiki] = $site->getFileUrl( 'api.php' );
			} else {
				$this->logger->error(
					'Site {wiki} was not recognized.',
					[
						'wiki' => $wiki,
					]
				);
			}
		}

		if ( !$urls ) {
			return [];
		}

		$params = [
			'format' => 'json',
			'formatversion' => '2',
			'errorformat' => 'bc',
		];

		if ( $this->authenticate === self::AUTHENTICATE_CENTRAL_AUTH ) {
			$params['centralauthtoken'] = $this->getCentralAuthToken();
		}

		$this->params = $params + $this->params;

		$reqs = [];
		foreach ( $urls as $wiki => $url ) {
			$reqs[$wiki] = [
				'method' => 'GET',
				'url' => $url,
				'query' => $this->params
			];

			$reqs[$wiki]['headers'] = [
				'X-Forwarded-For' => $originalRequest->getIP(),
				'User-Agent' => (
					$originalRequest->getHeader( 'User-Agent' )
					. ' (via CheckUserApiRequestAggregator MediaWiki/' . MW_VERSION . ')'
				),
			];
		}

		return $reqs;
	}

	/**
	 * @param array $reqs API request params
	 * @return array[]
	 * @throws Exception
	 */
	private function doRequests( array $reqs ) {
		if ( count( $reqs ) === 0 ) {
			return [];
		}

		$http = $this->httpRequestFactory->createMultiClient();
		$responses = $http->runMulti( $reqs );

		$results = [];
		foreach ( $responses as $wiki => $response ) {
			$statusCode = $response['response']['code'];

			if ( $statusCode >= 200 && $statusCode <= 299 ) {
				$parsed = json_decode( $response['response']['body'], true );
				if ( $parsed ) {
					$results[$wiki] = $parsed;
				}
			}

			if ( !isset( $results[$wiki] ) ) {
				$this->logger->error(
					'Failed to fetch API response from {wiki}. Error: {error}',
					[
						'wiki' => $wiki,
						'error' => $response['response']['error'] ?? 'unknown',
						'statusCode' => $statusCode,
						'response' => $response['response'],
						'request' => $reqs[$wiki],
					]
				);
			}
		}

		return $results;
	}
}
