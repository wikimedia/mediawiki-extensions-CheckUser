<template>
	<temp-accounts-onboarding-step
		step-name="ip-reveal"
		:image-aria-label="$i18n(
			'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-image-aria-label'
		).text()"
	>
		<template #title>
			{{ $i18n(
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-title'
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
		</template>
	</temp-accounts-onboarding-step>
</template>

<script>

const TempAccountsOnboardingStep = require( './TempAccountsOnboardingStep.vue' );

// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingIPRevealStep',
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		TempAccountsOnboardingStep
	},
	setup() {
		// Parse the message as would be for content in a page, such that two newlines creates a new
		// paragraph block.

		// The step content links to Special:GlobalPreferences if this is installed.
		let contentMessageKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content';
		if ( mw.config.get( 'wgCheckUserGlobalPreferencesExtensionLoaded' ) ) {
			contentMessageKey += '-with-global-preferences';
		}

		// Uses:
		// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content
		// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content-with-global-preferences
		const paragraphs = mw.message( contentMessageKey ).parse().split( '\n\n' );

		return { paragraphs };
	}
};
</script>
