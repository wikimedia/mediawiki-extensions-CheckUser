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
}() );
