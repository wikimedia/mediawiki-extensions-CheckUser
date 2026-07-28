'use strict';

/**
 * Add instrumentation specific to contributions pages
 * T418740: Instrumentation for the "SI cases" link on contributions special pages
 *
 * @param {function(string, object): void} logEvent
 */
function instrumentContributionsPages( logEvent ) {
	const $siCasesLink = $( '.mw-contributions-link-suggested-investigations' );
	if ( $siCasesLink.length !== 0 ) {
		$siCasesLink.on(
			'click',
			() => logEvent(
				'contributions_toollink_click',
				{ context: mw.config.get( 'wgRelevantUserName' ) }
			)
		);
	}
}

/**
 * Add instrumentation specific to Special:CheckUser
 * T418740: Instrumentation for the "SI cases" links on Special:CheckUser Get Users results.
 *
 * @param {function(string, object): void} logEvent
 */
function instrumentSpecialCheckUser( logEvent ) {
	// Use mousedown instead of click to also capture middle-click and right-click
	// "Open in new tab", since users need to keep the CheckUser results page open.
	const $siCasesLinks = $( '.mw-checkuser-get-users-results .mw-checkuser-si-cases-link' );
	if ( $siCasesLinks.length !== 0 ) {
		$siCasesLinks.on(
			'mousedown',
			() => logEvent( 'checkuser_si_cases_link_click', {
				context: 'special_checkuser_get_users'
			} )
		);
	}
}

/**
 * Add query parameters to be used in instrumenting Special:SuggestedInvestigations server-side. For
 * server-side implementation, see PageDisplay::instrumentSuggestedInvestigations.
 * This is done instead of client-side instrumentation due to significant loss in events.
 *
 * @param {function(string, object): void} logEvent
 */
function instrumentSpecialSuggestedInvestigations( logEvent ) {
	const isDetailsPage = mw.config.get( 'wgPageName' ).includes( '/detail/' );

	// Elements with this class will be instrumented by their data-subtype
	const customInstrumentClass = 'mw-checkuser-suggestedinvestigations-custom-instrument';

	// Instrument the links by class
	const subtypeByClass = {
		'mw-userlink': 'user-page',
		'mw-usertoollinks-contribs': 'contributions',
		'mw-usertoollinks-block': 'block',
		'mw-usertoollinks-past-checks': 'past-checks',
		'mw-usertoollinks-checkuser': 'check-user',
		'mw-checkuser-suggestedinvestigations-investigate-action': 'investigate',
		'mw-usertoollinks-suggestedinvestigations-cases': 'past-cases',
		'mw-usertoollinks-abusefilter-hits': 'abusefilter-hits',
		// The value will be replaced later with content of data-subtype
		[ customInstrumentClass ]: 'custom-instrument'
	};
	const linkClasses = Object.keys( subtypeByClass );
	const linkSelectors = linkClasses.map(
		( className ) => `.ext-checkuser-suggestedinvestigations-table .${ className }`
	).join( ', ' );
	$( linkSelectors ).each( ( _i, el ) => {
		const $el = $( el );
		const $a = $el.is( 'a' ) ? $el : $el.find( 'a' );
		const href = $a.attr( 'href' );

		if ( !href ) {
			return;
		}

		const subTypeClass = linkClasses.find(
			( className ) => $el[ 0 ].classList.contains( className )
		);
		const $usernameElement = $el.closest( '[data-username]' );
		const targetUser = $usernameElement.length ? $usernameElement.attr( 'data-username' ) : '';
		const inTopTable = $el.closest( '.ext-checkuser-suggestedinvestigations-table-main' ).length !== 0;
		let actionSource = 'main';
		if ( isDetailsPage && inTopTable ) {
			actionSource = 'details';
		} else if ( isDetailsPage ) {
			// This can happen if we instrument links from signal-specific details that are rendered below the main table
			actionSource = 'details_sub';
		}
		let subType = subtypeByClass[ subTypeClass ];
		if ( subTypeClass === customInstrumentClass ) {
			subType = $el.attr( 'data-subtype' );
		}

		const $container = $el.closest( 'tr' );
		const caseId = $container.find( '[data-case-id]' ).attr( 'data-case-id' );

		if ( !subType ) {
			mw.log.warn( 'Action subtype for link_click is not configured. Aborting' );
			return;
		}
		if ( !caseId ) {
			mw.log.warn( 'Case identifier needed for link_click and not found. Aborting' );
			return;
		}

		// If the link is a custom link, there's no guarantee it'll lead to a wiki page that
		// we can instrument on so instrument those clicks client-side despite the known event loss
		if ( $el[ 0 ].classList.contains( customInstrumentClass ) ) {
			$el.on( 'click auxclick contextmenu', ( e ) => {
				// Right-clicking on element fires both auxclick and contextmenu - skip the former
				if ( e.type === 'auxclick' && e.button === 2 ) {
					return;
				}

				logEvent( 'link_click', {
					subType: subType,
					source: actionSource,
					context: targetUser,
					caseId: caseId
				} );
			} );
		} else {
			// Add query parameters to make this link click associable with SuggestedInvestigations
			const url = new URL( href, window.location.origin );
			url.searchParams.append( 'si_subtype', subType );
			url.searchParams.append( 'si_actionsource', actionSource );
			url.searchParams.append( 'si_targetuser', targetUser );
			url.searchParams.append( 'si_caseid', caseId );
			$a.attr( 'href', url.toString() );
		}
	} );
}

/**
 * Initialize instrumentation for Suggested Investigations.
 *
 * @return {void}
 */
module.exports = () => {
	const useInstrument = require( './composables/useInstrument.js' );
	const logEvent = useInstrument();

	instrumentContributionsPages( logEvent );
	instrumentSpecialCheckUser( logEvent );
	instrumentSpecialSuggestedInvestigations( logEvent );
};
