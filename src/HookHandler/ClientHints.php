<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiEditPage;
use MediaWiki\Api\ApiLogout;
use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\ClientHints\UserAgentClientHintsManagerHelperTrait;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsTrigger;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Psr\Log\LoggerInterface;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * HookHandler for entry points related to requesting User-Agent Client Hints data.
 */
class ClientHints implements
	SpecialPageBeforeExecuteHook,
	BeforePageDisplayHook,
	APIGetAllowedParamsHook,
	PageSaveCompleteHook
{
	use UserAgentClientHintsManagerHelperTrait;

	public function __construct(
		private readonly Config $config,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserAgentClientHintsManager $userAgentClientHintsManager,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly LoggerInterface $logger,
		private readonly SuggestedInvestigationsTrigger $suggestedInvestigationsTrigger,
	) {
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			return;
		}

		$request = $special->getRequest();

		$specialPagesList = $this->config->get( 'CheckUserClientHintsSpecialPages' );

		$headerSent = false;
		if ( in_array( $special->getName(), $specialPagesList ) ) {
			// If the special page name is a value in the config, then this is the old format and we should
			// consider it as collecting the data via the header.
			$request->response()->header( $this->getClientHintsHeaderString() );
			$headerSent = true;
		} elseif ( array_key_exists( $special->getName(), $specialPagesList ) ) {
			// If the special page name is a key in the config, then the value is the method which the data is
			// collected.
			$types = $specialPagesList[$special->getName()];
			if ( !is_array( $types ) ) {
				$types = [ $types ];
			}
			if ( in_array( 'header', $types ) ) {
				$request->response()->header( $this->getClientHintsHeaderString() );
				$headerSent = true;
			}
		}

		if ( $this->config->get( 'CheckUserClientHintsUnsetHeaderWhenPossible' ) && !$headerSent ) {
			$request->response()->header( $this->getEmptyClientHintsHeaderString() );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			return;
		}

		// Add the module to all pages (all namespaces, and special pages).
		// All pages are needed in order to handle logouts via the personal tools
		// menu, which could happen from any page.
		$this->addJsClientHintsModule( $out );

		$title = $out->getTitle();
		if ( $title->isSpecialPage() ) {
			$specialPage = $this->specialPageFactory->getPage( $title->getDBkey() );
			if ( $specialPage ) {
				$specialPagesList = $this->config->get( 'CheckUserClientHintsSpecialPages' );
				if ( array_key_exists( $specialPage->getName(), $specialPagesList ) ) {
					// If the special page name is a key in the config, then the value is the method which the data is
					// collected.
					$types = $specialPagesList[$specialPage->getName()];
					if ( !is_array( $types ) ) {
						$types = [ $types ];
					}
					if ( in_array( 'header', $types ) ) {
						// If this is a special page in the list of special page that client hints
						// knows about, then assume that the client hints server-side header handling
						// has already been done in onSpecialPageBeforeExecute
						return;
					}
				}
			}
		}

		if ( $this->config->get( 'CheckUserAlwaysSetClientHintHeaders' ) ) {
			$request = $out->getRequest();
			$request->response()->header( $this->getClientHintsHeaderString() );
		} elseif ( $this->config->get( 'CheckUserClientHintsUnsetHeaderWhenPossible' ) ) {
			$request = $out->getRequest();
			$request->response()->header( $this->getEmptyClientHintsHeaderString() );
		}
	}

	/**
	 * Add the JS Client Hints module to the given OutputPage instance.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	private function addJsClientHintsModule( OutputPage $out ): void {
		$out->addJsConfigVars( [
			// Roundabout way to ensure we have a list of values like "architecture", "bitness"
			// etc for use with the client-side JS API. Make sure we get 1) just the values
			// from the configuration, 2) filter out any empty entries, 3) convert to a list
			'wgCheckUserClientHintsHeadersJsApi' => array_values( array_filter(
				$this->config->get( 'CheckUserClientHintsHeaders' )
			) ),
			'wgCheckUserClientHintsInEditRequest' => $this->config->get(
				'CheckUserClientHintsInEditRequest'
			),
		] );
		$out->addModules( 'ext.checkUser.clientHints' );
	}

	/** @inheritDoc */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			return;
		}
		$acceptsClientHints = $module instanceof ApiLogout || (
			$this->config->get( 'CheckUserClientHintsInEditRequest' ) && (
				$module instanceof ApiEditPage ||
				// CheckUser cannot depend on these extensions, so match by name
				in_array( $module->getModuleName(), [ 'visualeditoredit', 'discussiontoolsedit' ], true )
			)
		);
		if ( $acceptsClientHints ) {
			$params[ClientHintsData::REQUEST_FIELD] = [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_SENSITIVE => true,
				ApiBase::PARAM_HELP_MSG => 'checkuser-apihelp-param-checkuserclienthints',
			];
		}
	}

	/** @inheritDoc */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ): void {
		// Don't process null edits, as the revision ID will not be performed by the user
		// who just attempted to edit
		if ( $editResult->isNullEdit() ) {
			return;
		}

		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$revisionId = $revisionRecord->getId();

		// These values can only be set via the HTTP headers, not by client side code.
		$this->storeHeaderOnlyClientHintsData( $revisionId, 'revision', $request );

		if (
			!$this->config->get( 'CheckUserClientHintsEnabled' ) ||
			!$this->config->get( 'CheckUserClientHintsInEditRequest' )
		) {
			return;
		}

		// Only use the field if the performer of the edit made the request that carries it.
		if ( !$user->equals( $context->getUser() ) ) {
			return;
		}

		$requestFieldData = $this->storeClientHintsDataFromRequestField( $revisionId, 'revision', $request );
		if ( !$requestFieldData ) {
			return;
		}

		if ( $user->isRegistered() ) {
			$this->suggestedInvestigationsTrigger->matchSignalsAgainstUserInJob(
				$user,
				SuggestedInvestigationsSignalMatchService::EVENT_CLIENT_HINTS_SAVED,
				[ 'clientHints' => $requestFieldData->jsonSerialize(), 'revId' => $revisionId ]
			);
		}
	}

	/**
	 * Get the list of headers to use with Accept-CH.
	 */
	private function getClientHintsHeaderString(): string {
		$headers = implode(
			', ',
			array_filter( array_keys( $this->config->get( 'CheckUserClientHintsHeaders' ) ) )
		);
		return "Accept-CH: $headers";
	}

	/**
	 * Get an Accept-CH header string to tell the client to stop sending client-hint data.
	 */
	private function getEmptyClientHintsHeaderString(): string {
		return "Accept-CH: ";
	}

}
