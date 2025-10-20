<template>
	<h6 class="ext-checkuser-temp-account-onboarding-dialog-preference-title">
		{{ sectionTitle }}
	</h6>
	<div
		v-if="checkboxDescriptionMessageKey !== ''"
		class="ext-checkuser-temp-account-onboarding-dialog-preference-description"
	>
		<!-- eslint-disable vue/no-v-html-->
		<p
			v-for="( paragraph, key ) in parseWithParagraphBreaks( checkboxDescriptionMessageKey )"
			:key="key"
			v-html="paragraph"
		></p>
		<!-- eslint-enable vue/no-v-html-->
	</div>
	<cdx-field
		:is-fieldset="true"
		class="ext-checkuser-temp-account-onboarding-dialog-preference"
		:status="checkboxFieldErrorState"
		:messages="checkboxFieldMessages"
	>
		<cdx-checkbox
			:model-value="preferenceValue"
			@update:model-value="onPreferenceChange"
		>
			<span v-i18n-html="checkboxMessageKey"></span>
		</cdx-checkbox>
	</cdx-field>
	<cdx-field class="ext-checkuser-temp-account-onboarding-dialog-save-preference">
		<cdx-button
			action="progressive"
			@click="onSavePreferenceButtonClick"
		>
			{{ savePreferenceButtonText }}
		</cdx-button>
	</cdx-field>
	<!-- eslint-disable vue/no-v-html-->
	<p
		v-if="preferencePostscript !== ''"
		class="ext-checkuser-temp-account-onboarding-dialog-preference-postscript"
		v-html="preferencePostscript"
	></p>
	<!-- eslint-enable vue/no-v-html-->
</template>

<script>

const { computed, ref } = require( 'vue' );
const { CdxCheckbox, CdxButton, CdxField } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingPreference',
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		CdxCheckbox,
		CdxButton,
		CdxField
	},
	props: {
		/** The initial value of the preference */
		initialValue: { type: [ String, Boolean ], required: true },
		/** The key to store the checked status in mw.storage.session */
		checkedStatusStorageKey: { type: String, required: true },
		/** The name of the preference used to update the preference via the API */
		name: { type: String, required: true },
		/** The message key for the text for the preference checkbox */
		checkboxMessageKey: { type: String, required: true },
		/** The message key for the text above the preference checkbox (optional) */
		checkboxDescriptionMessageKey: { type: String, required: false, default: '' },
		/** The text used as the section title displayed above the preference */
		sectionTitle: { type: String, required: true },
		/** The message key for the text below the preference checkbox (optional) */
		preferencePostscript: { type: String, required: false, default: '' }
	},
	setup( props, { expose } ) {
		/**
		 * False if no request has been made, empty string for successful request, and
		 * string for an error.
		 */
		const lastOptionsUpdateError = ref( false );
		const preferenceUpdateSuccessful = computed( () => lastOptionsUpdateError.value === '' );
		const attemptedToMoveWithoutPressingSave = ref( false );

		let savePreferenceButtonText;
		if ( mw.config.get( 'wgCheckUserGlobalPreferencesExtensionLoaded' ) ) {
			savePreferenceButtonText = mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-save-global-preference'
			);
		} else {
			savePreferenceButtonText = mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-save-preference'
			);
		}

		/**
		 * What type of message to show to the user underneath the preference checkbox:
		 * * 'error' means that the preference failed to save after pressing the submit button
		 * * 'success' means that the preference saved after pressing the submit button
		 * * 'warning' means the user has attempted to leave this step without saving the
		 *     preference value using the submit button
		 * * 'default' means to display no text underneath the checkbox
		 */
		const checkboxFieldErrorState = computed( () => {
			if ( lastOptionsUpdateError.value ) {
				return 'error';
			}
			if ( preferenceUpdateSuccessful.value ) {
				return 'success';
			}
			if ( attemptedToMoveWithoutPressingSave.value ) {
				return 'warning';
			}
			return 'default';
		} );

		// Create the success, warning, and error messages for the user.
		const checkboxFieldMessages = computed( () => ( {
			error: mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-preference-error', lastOptionsUpdateError.value
			),
			warning: mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-preference-warning', savePreferenceButtonText
			),
			success: mw.msg( 'checkuser-temporary-accounts-onboarding-dialog-preference-success' )
		} ) );

		// Keep a track of the value of the preference value on the client
		// and also separately server, along with any mismatch in these values.
		const preferenceValue = ref( props.initialValue );
		const serverPreferenceValue = ref( props.initialValue );
		const preferenceCheckboxStateNotYetSaved = computed(
			() => preferenceValue.value !== serverPreferenceValue.value
		);
		const preferenceUpdateInProgress = ref( false );

		/**
		 * Handles a click of the "Save preference" button.
		 */
		function onSavePreferenceButtonClick() {
			// Ignore duplicate clicks of the button to avoid race conditions
			if ( preferenceUpdateInProgress.value ) {
				return;
			}

			preferenceUpdateInProgress.value = true;
			const newPreferenceValue = preferenceValue.value;
			const api = new mw.Api();
			api.saveOption( props.name, newPreferenceValue ? 1 : 0, { global: 'create' } ).then(
				() => {
					preferenceUpdateInProgress.value = false;
					lastOptionsUpdateError.value = '';
					serverPreferenceValue.value = newPreferenceValue;
					mw.storage.session.set(
						props.checkedStatusStorageKey, newPreferenceValue ? 'checked' : ''
					);
				},
				( error, result ) => {
					preferenceUpdateInProgress.value = false;
					// Display a user-friendly error message if we have it,
					// otherwise use the error code.
					if ( result && result.error && result.error.info ) {
						lastOptionsUpdateError.value = result.error.info;
					} else {
						lastOptionsUpdateError.value = error;
					}
				}
			);
		}

		/**
		 * Handles when the IPInfo preference checkbox is checked or unchecked.
		 *
		 * @param {boolean} newValue Whether the preference checkbox is checked
		 */
		function onPreferenceChange( newValue ) {
			// Set the ref value to indicate the new state
			preferenceValue.value = newValue;
			lastOptionsUpdateError.value = false;
			attemptedToMoveWithoutPressingSave.value = false;
		}

		/**
		 * Returns whether this step will allow dialog to navigate to another step.
		 *
		 * Used to warn the user if they have not saved the changes to the
		 * preference checkbox.
		 *
		 * @return {boolean}
		 */
		function canMoveToAnotherStep() {
			const returnValue = !preferenceCheckboxStateNotYetSaved.value ||
				!!lastOptionsUpdateError.value;
			attemptedToMoveWithoutPressingSave.value = !returnValue;
			return returnValue;
		}

		/**
		 * Used to indicate if the user should be warned before they close the dialog,
		 * so that they can be alerted if they have not saved the changes to the
		 * preference.
		 *
		 * @return {boolean}
		 */
		function shouldWarnBeforeClosingDialog() {
			const returnValue = preferenceCheckboxStateNotYetSaved.value &&
				!lastOptionsUpdateError.value;
			attemptedToMoveWithoutPressingSave.value = returnValue;
			return returnValue;
		}

		/**
		 * Manually process the paragraph breaks in messages
		 *
		 * @param {string} messageKey
		 * @return {string[]} - array of strings split on the double newline
		 */
		function parseWithParagraphBreaks( messageKey ) {
			// * wikimedia-checkuser-tempaccount-enable-preference-description
			// * HACK: linter gets mad if only one message key is present
			return mw.message( messageKey ).parse().split( '\n\n' );
		}
		// Expose method to check if we can move to another step so that the step can expose this
		// to the overall dialog component.
		expose( { canMoveToAnotherStep, shouldWarnBeforeClosingDialog } );
		return {
			checkboxFieldErrorState,
			checkboxFieldMessages,
			preferenceValue,
			savePreferenceButtonText,
			onPreferenceChange,
			onSavePreferenceButtonClick,
			parseWithParagraphBreaks
		};
	}
};
</script>
