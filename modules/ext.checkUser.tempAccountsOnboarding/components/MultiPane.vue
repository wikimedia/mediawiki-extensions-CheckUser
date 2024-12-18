<template>
	<div
		ref="rootElementRef"
		class="ext-checkuser-temp-account-onboarding-dialog-MultiPane"
		@touchstart="onTouchStart"
		@touchmove="onTouchMove">
		<transition :name="computedTransitionName">
			<slot v-if="$slots.step1" :name="currentSlotName"></slot>
			<slot v-else></slot>
		</transition>
	</div>
</template>

<script>
const { computed, ref, toRef, watch } = require( 'vue' );
const { useModelWrapper, useComputedDirection } = require( '@wikimedia/codex' );
const TRANSITION_NAMES = {
	LEFT: 'ext-checkuser-temp-account-onboarding-left',
	RIGHT: 'ext-checkuser-temp-account-onboarding-right'
};

/**
 * A component which allows a dialog to move between several defined steps
 * (defined through slots) and applies animations when moving between steps.
 *
 * This is a modified copy of the MultiPane component from the
 * mediawiki/extensions/GrowthExperiments repository.
 */
// @vue/component
module.exports = exports = {
	name: 'MultiPane',
	props: {
		/** The current step (starts from 1) */
		/* eslint-disable-next-line vue/no-unused-properties */
		currentStep: {
			type: Number,
			required: true
		},
		/** The total number of steps */
		totalSteps: {
			type: Number,
			required: true
		}
	},
	emits: [ 'update:currentStep' ],
	setup( props, { emit, expose } ) {
		// Work out whether the page is rtl or ltr.
		// Needed to decide which direction the animation should go.
		const rootElementRef = ref( null );
		const computedDir = useComputedDirection( rootElementRef );
		const isRtl = computed( () => computedDir.value === 'rtl' );

		// Work out the slot name based on the current step.
		const wrappedCurrentStep = useModelWrapper( toRef( props, 'currentStep' ), emit, 'update:currentStep' );
		const currentSlotName = computed( () => `step${ wrappedCurrentStep.value }` );

		// Keep a track of the current step so that if the parent updates the currentStep
		// property we can work out which direction the navigation should go.
		const currentStepInternal = ref( 1 );

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
		 * Updates the internal step counter used to detect changes made by
		 * the parent to the currentStep property, and also sets the
		 * variables needed for transitions.
		 *
		 * @param {'prev'|'next'} actionName
		 */
		function navigate( actionName ) {
			currentNavigation.value = actionName;
			if ( actionName === 'next' ) {
				currentStepInternal.value++;
			} else {
				currentStepInternal.value--;
			}
		}

		/**
		 * Method used to navigate forward.
		 *
		 * Does nothing if the current step is the last defined step.
		 *
		 * @public
		 */
		function navigateNext() {
			if ( wrappedCurrentStep.value < props.totalSteps ) {
				navigate( 'next' );
				wrappedCurrentStep.value++;
			}
		}

		/**
		 * Method used to navigate backwards.
		 *
		 * Does nothing if the current step is the first step.
		 *
		 * @public
		 */
		function navigatePrev() {
			if ( wrappedCurrentStep.value > 1 ) {
				navigate( 'prev' );
				wrappedCurrentStep.value--;
			}
		}

		// React to changes on the currentStep model, needed if the
		// parent does not use the convenience methods navigatePrev, navigateNext
		// but modifies the currentStep model directly
		watch( wrappedCurrentStep, () => {
			if ( currentStepInternal.value < wrappedCurrentStep.value ) {
				navigate( 'next' );
			} else if ( currentStepInternal.value > wrappedCurrentStep.value ) {
				navigate( 'prev' );
			}
		} );

		function onTouchStart( e ) {
			const touchEvent = e.touches[ 0 ];
			initialX.value = touchEvent.clientX;
		}

		const isSwipeToLeft = ( touchEvent ) => {
			const newX = touchEvent.clientX;
			return initialX.value > newX;
		};

		const onSwipeToRight = () => {
			if ( isRtl.value === true ) {
				navigateNext();
			} else {
				navigatePrev();
			}
		};

		const onSwipeToLeft = () => {
			if ( isRtl.value === true ) {
				navigatePrev();
			} else {
				navigateNext();
			}
		};

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

		// Make navigate methods available on parent component through a ref
		expose( { navigatePrev, navigateNext } );
		return {
			computedTransitionName,
			currentSlotName,
			onTouchStart,
			onTouchMove,
			rootElementRef
		};
	}
};
</script>
