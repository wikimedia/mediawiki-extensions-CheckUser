<template>
	<temp-accounts-onboarding-dialog
		v-model:open="dialogOpen"
		:total-steps="steps.length"
	>
		<template
			v-for="step in steps"
			:key="step.name"
			#[step.name]
		>
			<component :is="step.componentName"></component>
		</template>
	</temp-accounts-onboarding-dialog>
</template>

<script>

const { ref } = require( 'vue' );
const TempAccountsOnboardingDialog = require( './TempAccountsOnboardingDialog.vue' );
const TempAccountsOnboardingIntroStep = require( './TempAccountsOnboardingIntroStep.vue' );
const TempAccountsOnboardingIPInfoStep = require( './TempAccountsOnboardingIPInfoStep.vue' );
const TempAccountsOnboardingIPRevealStep = require( './TempAccountsOnboardingIPRevealStep.vue' );

// @vue/component
module.exports = exports = {
	name: 'App',
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		TempAccountsOnboardingDialog,
		TempAccountsOnboardingIntroStep,
		TempAccountsOnboardingIPInfoStep,
		TempAccountsOnboardingIPRevealStep
	},
	setup() {
		const dialogOpen = ref( true );

		// Generate the steps to be shown in the onboarding dialog. We need to generate
		// these steps programmatically as the IPInfo step will only be shown if
		// IPInfo is installed.
		const steps = [ { componentName: 'TempAccountsOnboardingIntroStep' } ];

		if ( mw.config.get( 'wgCheckUserIPInfoExtensionLoaded' ) ) {
			steps.push( { componentName: 'TempAccountsOnboardingIPInfoStep' } );
		}

		steps.push( { componentName: 'TempAccountsOnboardingIPRevealStep' } );

		steps.forEach( ( step, index ) => {
			step.name = 'step' + ( index + 1 );
		} );

		return {
			dialogOpen,
			steps
		};
	}
};
</script>
