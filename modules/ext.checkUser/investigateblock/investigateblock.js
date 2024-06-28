( function () {
	let userPageWidget,
		userPagePositionWidget,
		userPageTextWidget,
		talkPageWidget,
		talkPagePositionWidget,
		talkPageTextWidget,
		dropdownWidget = null,
		otherReasonWidget = null;

	function updateNoticeOptions() {
		const isUserPageChecked = userPageWidget.isSelected(),
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

	/**
	 * Update the 'required' attribute on the free-text field when the dropdown is changed.
	 * If the value of the dropdown is 'other', the free-text field is required. Otherwise,
	 * it is not required.
	 */
	function updateRequiredAttributeOnOtherField() {
		const $otherReasonInputElement = $( 'input', otherReasonWidget.$element );
		const $requiredIndicator = $( '.oo-ui-indicator-required', otherReasonWidget.$element );
		if ( dropdownWidget.getValue() === 'other' ) {
			// Set the required property for native browser validation and show the "required" OOUI indicator.
			$otherReasonInputElement.attr( 'required', 'required' );
			$requiredIndicator.show();
		} else {
			// Remove the required property and hide the "required" OOUI indicator.
			$otherReasonInputElement.removeAttr( 'required' );
			$requiredIndicator.hide();
		}
	}

	const $dropdownAndInput = $( '#mw-input-wpReason' );
	if ( $dropdownAndInput.length > 0 ) {
		const dropdownAndInputWidget = OO.ui.infuse( $dropdownAndInput );
		dropdownWidget = dropdownAndInputWidget.dropdowninput;
		otherReasonWidget = dropdownAndInputWidget.textinput;

		dropdownWidget.on( 'change', updateRequiredAttributeOnOtherField );

		updateRequiredAttributeOnOtherField();
	}
}() );
