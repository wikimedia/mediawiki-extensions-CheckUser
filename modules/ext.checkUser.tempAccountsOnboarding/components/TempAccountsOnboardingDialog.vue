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
			<div
				ref="stepWrapperRef"
				class="ext-checkuser-temp-account-onboarding-dialog-content"
				@touchstart="onTouchStart"
				@touchmove="onTouchMove">
				<transition :name="computedTransitionName">
					<slot :name="currentSlotName"></slot>
				</transition>
			</div>
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
						@click="navigatePrev"
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
						@click="navigateNext"
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

const { ref, computed, toRef, onUnmounted } = require( 'vue' );
const { CdxDialog, CdxButton, CdxIcon, useModelWrapper, useComputedDirection } = require( '@wikimedia/codex' );
const { cdxIconNext, cdxIconPrevious } = require( './icons.json' );
const TempAccountsOnboardingStepper = require( './TempAccountsOnboardingStepper.vue' );
const TRANSITION_NAMES = {
	LEFT: 'ext-checkuser-temp-account-onboarding-left',
	RIGHT: 'ext-checkuser-temp-account-onboarding-right'
};

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
	emits: [ 'update:open' ],
	setup( props, { emit } ) {
		const wrappedOpen = useModelWrapper( toRef( props, 'open' ), emit, 'update:open' );

		// Work out the slot name based on the current step.
		const currentStep = ref( 1 );
		const currentSlotName = computed( () => `step${ currentStep.value }` );

		// Work out whether the page is rtl or ltr. Needed to decide which
		// direction the animation for the step transition should go.
		const stepWrapperRef = ref( null );
		const computedDir = useComputedDirection( stepWrapperRef );
		const isRtl = computed( () => computedDir.value === 'rtl' );

		// Set up the variables needed to perform navigation and also
		// the associated animations.
		const initialX = ref( null );
		const currentNavigation = ref( null );
		const computedTransitionSet = computed( () => isRtl.value ?
			{ next: TRANSITION_NAMES.LEFT, prev: TRANSITION_NAMES.RIGHT } :
			{ next: TRANSITION_NAMES.RIGHT, prev: TRANSITION_NAMES.LEFT } );
		const computedTransitionName = computed(
			() => computedTransitionSet.value[ currentNavigation.value ]
		);

		/**
		 * Method used to navigate forward.
		 *
		 * Does nothing if the current step is the last defined step.
		 */
		function navigateNext() {
			if ( currentStep.value < props.totalSteps ) {
				currentNavigation.value = 'next';
				currentStep.value++;
			}
		}

		/**
		 * Method used to navigate backwards.
		 *
		 * Does nothing if the current step is the first step.
		 */
		function navigatePrev() {
			if ( currentStep.value > 1 ) {
				currentNavigation.value = 'prev';
				currentStep.value--;
			}
		}

		/**
		 * Handles a user starting a touch on their screen.
		 * Used to allow a user to navigate using a swipe of
		 * their screen.
		 *
		 * @param {TouchEvent} e
		 */
		function onTouchStart( e ) {
			const touchEvent = e.touches[ 0 ];
			initialX.value = touchEvent.clientX;
		}

		/**
		 * Return if the touch movement was a
		 * swipe to the left of the screen.
		 *
		 * @param {Touch} touch
		 * @return {boolean}
		 */
		const isSwipeToLeft = ( touch ) => {
			const newX = touch.clientX;
			return initialX.value > newX;
		};

		/**
		 * Handles a user swiping to the right
		 */
		const onSwipeToRight = () => {
			if ( isRtl.value === true ) {
				navigateNext();
			} else {
				navigatePrev();
			}
		};

		/**
		 * Handles a user swiping to the left
		 */
		const onSwipeToLeft = () => {
			if ( isRtl.value === true ) {
				navigatePrev();
			} else {
				navigateNext();
			}
		};

		/**
		 * Handles a user finishing a touch where there was
		 * a movement in a direction.
		 * Used to allow a user to navigate using a swipe of
		 * their screen.
		 *
		 * @param {TouchEvent} e
		 */
		function onTouchMove( e ) {
			if ( !initialX.value ) {
				return;
			}
			if ( isSwipeToLeft( e.touches[ 0 ] ) ) {
				onSwipeToLeft();
			} else {
				onSwipeToRight();
			}
			initialX.value = null;
		}

		/**
		 * Handles a close of the dialog when the dialog should not be seen again.
		 * The dialog is hidden for the user in future page loads via setting a
		 * preference.
		 *
		 * The close is prevented if the current step indicates that a warning should
		 * be displayed before closing the dialog.
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
			computedTransitionName,
			currentSlotName,
			onTouchStart,
			onTouchMove,
			stepWrapperRef,
			navigateNext,
			navigatePrev,
			onFinish,
			wrappedOpen
		};
	}
};
</script>
