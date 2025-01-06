<template>
	<cdx-dialog
		v-model:open="wrappedOpen"
		class="ext-checkuser-temp-account-onboarding-dialog"
		:title="$i18n( 'checkuser-temporary-accounts-onboarding-dialog-title' ).text()"
		:hide-title="true"
	>
		<!-- Dialog Header -->
		<template #header>
			<div class="ext-checkuser-temp-account-onboarding-dialog__header">
				<div class="ext-checkuser-temp-account-onboarding-dialog__header__top">
					<h4 class="ext-checkuser-temp-account-onboarding-dialog__header__top__title">
						{{ $i18n( 'checkuser-temporary-accounts-onboarding-dialog-title' ).text() }}
					</h4>
					<cdx-button
						class="ext-checkuser-temp-account-onboarding-dialog__header__top__button"
						weight="quiet"
						@click="onFinish"
					>
						{{ $i18n(
							'checkuser-temporary-accounts-onboarding-dialog-skip-all'
						).text() }}
					</cdx-button>
				</div>
				<temp-accounts-onboarding-stepper
					v-model:model-value="currentStep"
					class="ext-checkuser-temp-account-onboarding-dialog__header__stepper"
					:total-steps="totalSteps"
				></temp-accounts-onboarding-stepper>
			</div>
		</template>
		<!-- Dialog Content -->
		<div>
			<multi-pane
				ref="multiPaneRef"
				v-model:current-step="currentStep"
				:total-steps="totalSteps"
				@update:current-step="( newVal ) => currentStep = newVal"
			>
				<slot :name="currentSlotName"></slot>
			</multi-pane>
		</div>
		<!-- Dialog Footer -->
		<template #footer>
			<div class="ext-checkuser-temp-account-onboarding-dialog__footer">
				<div class="ext-checkuser-temp-account-onboarding-dialog__footer__navigation">
					<cdx-button
						v-if="currentStep !== 1"
						class="
							ext-checkuser-temp-account-onboarding-dialog__footer__navigation--prev
						"
						:aria-label="$i18n(
							'checkuser-temporary-accounts-onboarding-dialog-previous-label'
						).text()"
						@click="onPrevClick"
					>
						<cdx-icon
							:icon="cdxIconPrevious"
							:icon-label="$i18n(
								'checkuser-temporary-accounts-onboarding-dialog-previous-label'
							).text()"
						></cdx-icon>
					</cdx-button>
					<cdx-button
						v-if="currentStep === totalSteps"
						weight="primary"
						action="progressive"
						class="
							ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next
						"
						@click="onFinish"
					>
						{{ $i18n(
							'checkuser-temporary-accounts-onboarding-dialog-close-label'
						).text() }}
					</cdx-button>
					<cdx-button
						v-else
						weight="primary"
						action="progressive"
						:aria-label="$i18n(
							'checkuser-temporary-accounts-onboarding-dialog-next-label'
						).text()"
						class="
							ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next
						"
						@click="onNextClick"
					>
						<cdx-icon
							:icon="cdxIconNext"
							:icon-label="$i18n(
								'checkuser-temporary-accounts-onboarding-dialog-next-label'
							).text()"
						></cdx-icon>
					</cdx-button>
				</div>
			</div>
		</template>
	</cdx-dialog>
</template>

<script>

const { ref, computed, toRef, watch, onUnmounted } = require( 'vue' );
const { CdxDialog, CdxButton, CdxIcon, useModelWrapper } = require( '@wikimedia/codex' );
const { cdxIconNext, cdxIconPrevious } = require( './icons.json' );
const TempAccountsOnboardingStepper = require( './TempAccountsOnboardingStepper.vue' );
const MultiPane = require( './MultiPane.vue' );

/**
 * The Temporary Accounts onboarding dialog component. This defines the structure of the dialog and
 * the user of the component will define the content.
 */
// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingDialog',
	components: {
		CdxDialog,
		CdxButton,
		CdxIcon,
		MultiPane,
		TempAccountsOnboardingStepper
	},
	props: {
		/**
		 * Whether the dialog is visible. Should be provided via a v-model:open
		 * binding in the parent scope.
		 */
		/* eslint-disable-next-line vue/no-unused-properties */
		open: {
			type: Boolean,
			default: false
		},
		/** The total number of steps */
		totalSteps: {
			type: Number,
			required: true
		}
	},
	emits: [ 'update:open', 'update:currentStep' ],
	setup( props, { emit } ) {
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );

		// Work out the slot name based on the current step.
		const currentStep = ref( 1 );
		const currentSlotName = computed( () => `step${ currentStep.value }` );
		const multiPaneRef = ref( null );

		// Emit the currentStep update to the parent element to cause updates.
		watch( currentStep, () => {
			emit( 'update:currentStep', currentStep.value );
		} );

		function onPrevClick() {
			multiPaneRef.value.navigatePrev();
		}

		function onNextClick() {
			multiPaneRef.value.navigateNext();
		}

		/**
		 * Handles a close of the dialog via any method. When doing this, it sets a
		 * user preference to indicate that the dialog has been seen and should not be
		 * shown again.
		 */
		function onFinish() {
			const api = new mw.Api();
			api.saveOption( 'checkuser-temporary-accounts-onboarding-dialog-seen', 1 );
			emit( 'update:open', false );
		}

		/**
		 * Handle keypresses when the dialog is open, such that when the escape key is pressed
		 * the dialog is closed.
		 *
		 * @param {KeyboardEvent} event The keypress event
		 */
		function handleKeypress( event ) {
			if ( event.key === 'Escape' || event.key === 'Esc' ) {
				onFinish();
			}
		}

		window.addEventListener( 'keyup', handleKeypress, { once: true } );

		onUnmounted( () => {
			window.removeEventListener( 'keyup', handleKeypress );
		} );

		return {
			cdxIconNext,
			cdxIconPrevious,
			currentStep,
			currentSlotName,
			multiPaneRef,
			onNextClick,
			onPrevClick,
			onFinish,
			wrappedOpen
		};
	}
};
</script>
