<?php

namespace MediaWiki\CheckUser\HookHandler;

use LogicException;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\Hook\IPInfoIPInfoHandlerHook;

class IPInfoHandler implements IPInfoIPInfoHandlerHook {
	private CheckUserGlobalContributionsLookup $globalContributionsLookup;

	public function __construct(
		CheckUserGlobalContributionsLookup $globalContributionsLookup
	) {
		$this->globalContributionsLookup = $globalContributionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onIPInfoHandlerRun(
		string $target,
		string $dataContext,
		array &$dataContainer
	): void {
		if ( $dataContext !== 'infobox' ) {
			return;
		}
		try {
			$globalContributionsCount = $this->globalContributionsLookup->getGlobalContributionsCount(
				$target,
				RequestContext::getMain()->getAuthority()
			);
			$dataContainer['ipinfo-source-checkuser'] = [
				'globalContributionsCount' => $globalContributionsCount
			];
		} catch ( LogicException $e ) {
			// Do nothing if the count could not be found and passed through
			return;
		}
	}

}
