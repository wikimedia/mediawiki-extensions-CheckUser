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
 * Add instrumentation specific to Special:SuggestedInvestigations
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
		// The value will be replaced later with content of data-subtype
		[ customInstrumentClass ]: 'custom-instrument'
	};
	const linkClasses = Object.keys( subtypeByClass );
	const linkSelector = linkClasses.map(
		( className ) => '.ext-checkuser-suggestedinvestigations-table .' + className
	).join( ', ' );

	// Listening also for additional click types, especially for the "Open in new tab" option,
	// since users may want to keep the SI results page open.
	$( linkSelector ).on( 'click auxclick contextmenu', function ( e ) {
		// Right-clicking on element fires both auxclick and contextmenu - skip the former
		if ( e.type === 'auxclick' && e.button === 2 ) {
			return;
		}

		const subTypeClass = linkClasses.find(
			( className ) => this.classList.contains( className )
		);
		const usernameElement = this.closest( '[data-username]' );
		const targetUser = usernameElement ? usernameElement.getAttribute( 'data-username' ) : '';

		const inTopTable = this.closest( '.ext-checkuser-suggestedinvestigations-table-main' ) !== null;
		let actionSource = 'main';
		if ( isDetailsPage && inTopTable ) {
			actionSource = 'details';
		} else if ( isDetailsPage ) {
			// This can happen if we instrument links from signal-specific details that are rendered below the main table
			actionSource = 'details_sub';
		}

		let subType = subtypeByClass[ subTypeClass ];
		if ( subTypeClass === customInstrumentClass ) {
			subType = this.getAttribute( 'data-subtype' );
		}

		if ( subType === null ) {
			mw.log.warn( 'Action subtype for link_click is not configured. Not sending the event.' );
			return;
		}

		logEvent( 'link_click', {
			subType: subType,
			source: actionSource,
			context: targetUser
		} );
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
