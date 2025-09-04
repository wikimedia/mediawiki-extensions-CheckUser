( function () {
	const $userLists = $( '.mw-checkuser-suggestedinvestigations-users' );
	$userLists.each( function () {
		const $list = $( this );
		const $hiddenByDefault = $list.find( 'li.mw-checkuser-suggestedinvestigations-user-defaulthide' );

		if ( $hiddenByDefault.length === 0 ) {
			return;
		}

		// Else, append a button
		const $button = $( '<button>' )
			.addClass( 'cdx-button cdx-button--weight-quiet cdx-button--action-progressive' )
			.attr( 'type', 'button' )
			.text( mw.msg( 'checkuser-suggestedinvestigations-user-showmore', $hiddenByDefault.length ) )
			.on( 'click', () => {
				$hiddenByDefault.removeClass( 'mw-checkuser-suggestedinvestigations-user-defaulthide' );
				$button.remove();
			} );
		$list.after( $button );
	} );
}() );
