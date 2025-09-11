( function () {
	const $userLists = $( '.mw-checkuser-suggestedinvestigations-users' );
	$userLists.each( function () {
		const $list = $( this );
		const $hiddenByDefault = $list.find( 'li.mw-checkuser-suggestedinvestigations-user-defaulthide' );

		if ( $hiddenByDefault.length === 0 ) {
			return;
		}

		const numUsers = mw.language.convertNumber( $hiddenByDefault.length );

		// If there's a collapsible part, create buttons to show/hide the hidden items
		const $showLessButton = $( '<button>' );
		const $showMoreButton = $( '<button>' )
			.addClass( 'cdx-button cdx-button--weight-quiet cdx-button--action-progressive' )
			.attr( 'type', 'button' )
			.text( mw.msg( 'checkuser-suggestedinvestigations-user-showmore', numUsers ) )
			.on( 'click', () => {
				$hiddenByDefault.removeClass( 'mw-checkuser-suggestedinvestigations-user-defaulthide' );
				$showMoreButton.detach();
				$list.after( $showLessButton );
			} );

		$showLessButton.addClass( 'cdx-button cdx-button--weight-quiet cdx-button--action-progressive' )
			.attr( 'type', 'button' )
			.text( mw.msg( 'checkuser-suggestedinvestigations-user-showless', numUsers ) )
			.on( 'click', () => {
				$hiddenByDefault.addClass( 'mw-checkuser-suggestedinvestigations-user-defaulthide' );
				$showLessButton.detach();
				$list.after( $showMoreButton );
			} );

		$list.after( $showMoreButton );
	} );

	const Vue = require( 'vue' );
	const ChangeInvestigationStatusDialog = require( './components/ChangeInvestigationStatusDialog.vue' );

	let suggestedInvestigationsChangeStatusApp = null;

	$( '.mw-checkuser-suggestedinvestigations-change-status-button' ).on( 'click', ( event ) => {
		event.preventDefault();

		// Unmount the previous instance of the change status dialog, if any
		if ( suggestedInvestigationsChangeStatusApp !== null ) {
			suggestedInvestigationsChangeStatusApp.unmount();
		}

		suggestedInvestigationsChangeStatusApp = Vue.createMwApp(
			ChangeInvestigationStatusDialog
		);
		suggestedInvestigationsChangeStatusApp
			.mount( '#ext-suggestedinvestigations-change-status-app' );
	} );
}() );
