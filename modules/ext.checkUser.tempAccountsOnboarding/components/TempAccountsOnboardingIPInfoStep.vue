<template>
	<temp-accounts-onboarding-step
		step-name="ip-info"
		:image-aria-label="$i18n(
			'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-image-aria-label'
		).text()"
	>
		<template #title>
			{{ $i18n(
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-title'
			).text() }}
		</template>
		<template #content>
			<!-- eslint-disable vue/no-v-html-->
			<p
				v-for="( paragraph, key ) in paragraphs"
				:key="key"
				v-html="paragraph"
			></p>
			<!-- eslint-enable vue/no-v-html-->
			<template v-if="shouldShowIPInfoPreference">
				<h6 class="ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title">
					{{ $i18n(
						'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-title'
					).text() }}
				</h6>
				<!-- TODO: Use a selenium test to e2e test this once dialog is not hidden -->
				<cdx-field
					:is-fieldset="true"
					class="ext-checkuser-temp-account-onboarding-dialog-ip-info-preference"
					:status="checkboxFieldErrorState"
					:messages="checkboxFieldMessages"
				>
					<cdx-checkbox
						:model-value="ipInfoPreferenceValue"
						@update:model-value="onPreferenceChange"
					>
						<span v-i18n-html="'ipinfo-preference-use-agreement'"></span>
					</cdx-checkbox>
				</cdx-field>
			</template>
		</template>
	</temp-accounts-onboarding-step>
</template>

<script>

const { computed, ref } = require( 'vue' );
const { CdxCheckbox, CdxField } = require( '@wikimedia/codex' );
const TempAccountsOnboardingStep = require( './TempAccountsOnboardingStep.vue' );

// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingIPInfoStep',
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		TempAccountsOnboardingStep,
		CdxCheckbox,
		CdxField
	},
	setup() {
		// Hide the IPInfo preference checkbox if the user has already checked the preference.
		const initialIPInfoPreferenceValue = mw.user.options.get( 'ipinfo-use-agreement' ) !== '0' &&
			mw.user.options.get( 'ipinfo-use-agreement' ) !== 0;
		const shouldShowIPInfoPreference = !initialIPInfoPreferenceValue;

		// Display an error underneath the checkbox if the update of the IPInfo
		// preference fails to go through.
		const lastOptionsUpdateError = ref( '' );
		const checkboxFieldErrorState = computed( () => lastOptionsUpdateError.value ? 'error' : 'default' );
		const checkboxFieldMessages = computed( () => ( {
			error: mw.message(
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error', lastOptionsUpdateError.value
			).text()
		} ) );

		const ipInfoPreferenceValue = ref( initialIPInfoPreferenceValue );
		const lastSuccessfulIPInfoPreferenceValueState = ref( initialIPInfoPreferenceValue );

		/**
		 * Handles when the IPInfo preference checkbox is checked or unchecked.
		 *
		 * @param {boolean} newValue Whether the preference checkbox is checked
		 */
		function onPreferenceChange( newValue ) {
			// Set the ref value to indicate the new state
			ipInfoPreferenceValue.value = newValue;

			const api = new mw.Api();
			api.saveOption( 'ipinfo-use-agreement', newValue ? 1 : 0 ).then(
				() => {
					lastOptionsUpdateError.value = '';
					lastSuccessfulIPInfoPreferenceValueState.value = newValue;
				},
				( error, result ) => {
					// Display a user-friendly error message if we have it,
					// otherwise use the error code.
					if ( result && result.error && result.error.info ) {
						lastOptionsUpdateError.value = result.error.info;
					} else {
						lastOptionsUpdateError.value = error;
					}

					// Revert the state of the checkbox back to before the call.
					// We can't use newValue here because a user may be pressing the
					// checkbox more than once if the response is slow and so the last
					// time we reach here it may not be for the last check of the checkbox.
					ipInfoPreferenceValue.value = lastSuccessfulIPInfoPreferenceValueState.value;
				}
			);
		}

		// Parse the message as would be for content in a page, such that two newlines creates a new
		// paragraph block.
		const paragraphs = mw.message(
			'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-content'
		).parse().split( '\n\n' );

		return {
			checkboxFieldErrorState,
			checkboxFieldMessages,
			shouldShowIPInfoPreference,
			ipInfoPreferenceValue,
			onPreferenceChange,
			paragraphs
		};
	}
};
</script>
