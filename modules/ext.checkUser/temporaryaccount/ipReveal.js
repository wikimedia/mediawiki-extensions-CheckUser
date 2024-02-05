var ipRevealUtils = require( './ipRevealUtils.js' );
var { performRevealRequest } = require( './rest.js' );

function makeButton( target, revIds, logIds ) {
	var button = new OO.ui.ButtonWidget( {
		label: mw.msg( 'checkuser-tempaccount-reveal-ip-button-label' ),
		framed: false,
		quiet: true,
		flags: [
			'progressive'
		],
		classes: [ 'ext-checkuser-tempaccount-reveal-ip-button' ]
	} );
	button.once( 'click', function () {
		button.$element.trigger( 'revealIp' );
		button.$element.off( 'revealIp' );
		$( document ).trigger( 'userRevealed', [ target ] );
	} );

	button.$element.on( 'revealIp', function () {
		button.$element.off( 'revealIp' );
		performRevealRequest( target, revIds, logIds ).then( function ( response ) {
			var ip = response.ips[ ( revIds.targetId || logIds.targetId ) || 0 ];
			if ( !ipRevealUtils.getRevealedStatus( target ) ) {
				ipRevealUtils.setRevealedStatus( target );
			}
			button.$element.replaceWith(
				$( '<span>' )
					.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
					.text( ip || mw.msg( 'checkuser-tempaccount-reveal-ip-missing' ) )
			);
		} ).fail( function () {
			button.$element.replaceWith(
				$( '<span>' )
					.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
					.text( mw.msg( 'checkuser-tempaccount-reveal-ip-error' ) )
			);
		} );
	} );

	return button.$element;
}

/**
 * Add a button to a "typical" page. This functionality is here because
 * it is shared between initOnLoad and initOnHook.
 *
 * @param {jQuery} $content
 */
function addButton( $content ) {
	var allRevIds = {};
	var allLogIds = {};
	var $userLinks = $content.find( '.mw-tempuserlink' );

	$userLinks.each( function () {
		getAllIds( $( this ), allRevIds, getRevisionId );
		getAllIds( $( this ), allLogIds, getLogId );
	} );

	$userLinks.after( function () {
		var target = $( this ).text();
		var revIds = getIdsForTarget( $( this ), target, allRevIds, getRevisionId );
		var logIds = getIdsForTarget( $( this ), target, allLogIds, getLogId );
		return makeButton( target, revIds, logIds );
	} );
}

function getAllIds( $element, allIds, getId ) {
	var id = getId( $element );
	if ( id ) {
		var target = $element.text();
		if ( !allIds[ target ] ) {
			allIds[ target ] = [];
		}
		allIds[ target ].push( id );
	}
}

function getIdsForTarget( $element, target, allIds, getId ) {
	var id = getId( $element );
	var ids;
	if ( id ) {
		ids = allIds[ target ];
	}
	return {
		targetId: id,
		allIds: ids
	};
}

/**
 * Add enable multireveal for a "typical" page. This functionality is here
 * because it is shared between initOnLoad and initOnHook.
 *
 * @param {jQuery} $element
 */
function enableMultiReveal( $element ) {
	$element.on( 'userRevealed', function ( _e, userLookup ) {
		// Find all temp user links that share the username
		var $userLinks = $( '.mw-tempuserlink' ).filter( function () {
			return $( this ).text() === userLookup;
		} );

		// Convert the user links into pointers to the IP reveal button
		$userLinks = $userLinks.map( function ( _i, el ) {
			return $( el ).next( '.ext-checkuser-tempaccount-reveal-ip-button' );
		} );

		// Synthetically trigger a reveal event
		$userLinks.each( function () {
			$( this ).trigger( 'revealIp' );
		} );
	} );
}

/**
 * Get revision ID from the surrounding mw-changeslist-line list item.
 *
 * @param {jQuery} $element
 * @return {number|undefined}
 */
function getRevisionId( $element ) {
	return $element.closest( '[data-mw-revid]' ).data( 'mw-revid' );
}

function getLogId( $element ) {
	// Check if CheckUserEventTablesMigrationStage contains SCHEMA_COMPAT_READ_NEW
	// eslint-disable-next-line no-bitwise
	if ( mw.config.get( 'wgCheckUserEventTablesMigrationStage' ) & 0x200 ) {
		return $element.closest( '[data-mw-logid]' ).data( 'mw-logid' );
	}
}

module.exports = {
	makeButton: makeButton,
	addButton: addButton,
	enableMultiReveal: enableMultiReveal,
	getRevisionId: getRevisionId
};
