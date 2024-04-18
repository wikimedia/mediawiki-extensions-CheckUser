module.exports = function addBlockForm( documentRoot ) {
	// Attributes used for pinnable highlighting
	var blockButton = OO.ui.infuse( $( '.ext-checkuser-investigate-subtitle-block-button' ) ),
		$placeholderWidget = $( '.ext-checkuser-investigate-subtitle-placeholder-widget' ),
		targets = mw.config.get( 'wgCheckUserInvestigateTargets' ),
		excludeTargets = mw.config.get( 'wgCheckUserInvestigateExcludeTargets' ),
		targetsWidget = new OO.ui.MenuTagMultiselectWidget( {
			options: excludeTargets.map( function ( target ) {
				return {
					data: target,
					label: target
				};
			} ),
			selected: targets.filter( function ( target ) {
				return excludeTargets.indexOf( target ) === -1;
			} ),
			classes: [
				'ext-checkuser-investigate-subtitle-targets-widget'
			]
		} ),
		continueButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'checkuser-investigate-subtitle-continue-button-label' ),
			flags: [ 'primary', 'progressive' ],
			classes: [
				'ext-checkuser-investigate-subtitle-continue-button'
			]
		} ),
		cancelButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'checkuser-investigate-subtitle-cancel-button-label' ),
			flags: [ 'progressive' ],
			framed: false,
			classes: [
				'ext-checkuser-investigate-subtitle-cancel-button'
			]
		} );

	function toggleBlockFromButtons( showBlockForm ) {
		blockButton.toggle( !showBlockForm );
		continueButton.toggle( showBlockForm );
		cancelButton.toggle( showBlockForm );
		targetsWidget.toggle( showBlockForm );
	}

	$placeholderWidget.replaceWith( targetsWidget.$element );
	blockButton.$element.parent().prepend(
		continueButton.$element,
		cancelButton.$element
	);

	toggleBlockFromButtons( false );
	blockButton.on( 'click', toggleBlockFromButtons.bind( null, true ) );
	cancelButton.on( 'click', toggleBlockFromButtons.bind( null, false ) );

	continueButton.on( 'click', function () {
		var $form, params, key;

		$form = $( '<form>' ).attr( {
			action: new mw.Title( 'Special:InvestigateBlock' ).getUrl(),
			method: 'post',
			target: '_blank'
		} ).addClass( [ 'oo-ui-element-hidden', 'ext-checkuser-investigate-hidden-block-form' ] );

		params = {
			wpTargets: targetsWidget.getValue().join( '\n' ),
			allowedTargets: targets
		};
		for ( key in params ) {
			$form.append( $( '<input>' ).attr( {
				type: 'hidden',
				name: key,
				value: params[ key ]
			} ) );
		}

		if ( !documentRoot ) {
			documentRoot = 'body';
		}
		$form.appendTo( documentRoot ).trigger( 'submit' );
	} );
};
