<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentity;

class SuggestedInvestigationsTrigger {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsRequestHeaders',
	];

	public function __construct(
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly ServiceOptions $options,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Matches signals against the provided event in a job
	 *
	 * @param UserIdentity $userIdentity
	 * @param string $eventType One of the `SuggestedInvestigationsSignalMatchService::EVENT_*` constants
	 * @param array $extraData An array of extra data to include with the event. This method automatically adds
	 *     a list of configured headers to this array (under 'headers' key).
	 */
	public function matchSignalsAgainstUserInJob(
		UserIdentity $userIdentity,
		string $eventType,
		array $extraData = [],
	): void {
		$extraData['headers'] = $this->getRequestHeaders();
		$this->jobQueueGroup->lazyPush(
			SuggestedInvestigationsMatchSignalsAgainstUserJob::newSpec( $userIdentity, $eventType, $extraData )
		);
	}

	/**
	 * Reads the configured request headers from the current main request.
	 *
	 * @return array<string,string> Map of lowercased header name to value, for the configured
	 *   headers that are present in the request. Empty when no headers are configured.
	 */
	private function getRequestHeaders(): array {
		$headers = [];
		$request = RequestContext::getMain()->getRequest();
		foreach ( $this->options->get( 'CheckUserSuggestedInvestigationsRequestHeaders' ) as $headerName ) {
			$value = $request->getHeader( $headerName );
			if ( $value !== false ) {
				$headers[strtolower( $headerName )] = $value;
			}
		}
		return $headers;
	}
}
