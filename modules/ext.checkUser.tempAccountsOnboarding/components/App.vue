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
		TempAccountsOnboardingIntroStep
	},
	setup() {
		const dialogOpen = ref( true );

		// Generate the steps to be shown in the onboarding dialog. This needs to be
		// generated like this because we will later add steps that may not be shown
		// depending on a JS config variable.
		const steps = [ { componentName: 'TempAccountsOnboardingIntroStep' } ];
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
