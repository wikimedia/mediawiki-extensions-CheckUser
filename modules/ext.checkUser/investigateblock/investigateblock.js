( function () {
	var userPageWidget,
		userPagePositionWidget,
		userPageTextWidget,
		talkPageWidget,
		talkPagePositionWidget,
		talkPageTextWidget;

	function updateNoticeOptions() {
		var isUserPageChecked = userPageWidget.isSelected(),
			isTalkPageChecked = talkPageWidget.isSelected();

		userPagePositionWidget.setDisabled( !isUserPageChecked );
		userPageTextWidget.setDisabled( !isUserPageChecked );

		talkPagePositionWidget.setDisabled( !isTalkPageChecked );
		talkPageTextWidget.setDisabled( !isTalkPageChecked );
	}

	if ( $( '#mw-htmlform-options' ).length > 0 ) {
		userPageWidget = OO.ui.infuse( $( '#mw-input-wpUserPageNotice' ) );
		userPagePositionWidget = OO.ui.infuse( $( '#mw-input-wpUserPageNoticePosition' ) );
		userPageTextWidget = OO.ui.infuse( $( '#mw-input-wpUserPageNoticeText' ) );
		talkPageWidget = OO.ui.infuse( $( '#mw-input-wpTalkPageNotice' ) );
		talkPagePositionWidget = OO.ui.infuse( $( '#mw-input-wpTalkPageNoticePosition' ) );
		talkPageTextWidget = OO.ui.infuse( $( '#mw-input-wpTalkPageNoticeText' ) );

		userPageWidget.on( 'change', updateNoticeOptions );
		talkPageWidget.on( 'change', updateNoticeOptions );

		updateNoticeOptions();
	}
}() );
