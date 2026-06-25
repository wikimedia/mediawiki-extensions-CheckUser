<template>
	<info-row
		:icon="icon"
		:icon-class="iconClass"
	>
		<!--
		Security Note: This use of v-html is considered acceptable because:
		- Props are set via internal API with no user input (from UserCardBody.vue)
		- MediaWiki messages used here do not include unescaped placeholders
		- Anchor content is escaped by assigning it via textContent
		-->
		<!-- eslint-disable vue/no-v-html -->
		<span
			v-tooltip:bottom="tooltipMessage"
			v-html="formattedMessage"
		></span>
		<!-- eslint-enable vue/no-v-html -->
	</info-row>
</template>

<script>
const { computed, onMounted, watch, nextTick } = require( 'vue' );
const InfoRow = require( './InfoRow.vue' );
const useInstrument = require( '../composables/useInstrument.js' );
const { CdxTooltip } = require( '../codex.js' );

// @vue/component
module.exports = exports = {
	name: 'InfoRowWithLinks',
	components: { InfoRow },
	directives: {
		tooltip: CdxTooltip
	},
	props: {
		icon: { type: [ String, Object ], default: null },
		iconClass: { type: String, default: '' },
		messageKey: { type: String, required: true },
		tooltipKey: { type: String, default: '' },
		mainValue: { type: [ String, Number ], default: '' },
		mainLink: { type: String, default: '' },
		mainLinkLogId: { type: String, default: '' },
		suffixValue: { type: [ String, Number ], default: '' },
		suffixLink: { type: String, default: '' },
		suffixLinkLogId: { type: String, default: '' },
		username: { type: String, default: '' }
	},
	setup( props ) {
		const logEvent = useInstrument();

		function onLinkClick( logId ) {
			logEvent( 'link_click', {
				subType: logId || 'unknown',
				source: 'card_body',
				context: JSON.stringify( { username: props.username } )
			} );
		}

		// Create the DOM node passed as a parameter to the i18n message. When a
		// link is present we build an anchor with a stable id (so the click
		// handler can be attached afterwards), otherwise a plain span. We could
		// build the link in the i18n message itself, but we need to log the
		// event on click, hence the manually instrumented node.
		function createValueNode( link, logId, value ) {
			let node;
			if ( link ) {
				node = document.createElement( 'a' );
				node.id = `info-row-${ logId }`;
				node.href = link;
			} else {
				node = document.createElement( 'span' );
			}
			// Assigning via textContent escapes the value.
			node.textContent = value;
			return node;
		}

		const formattedMessage = computed( () => {
			// If no main anchor or main value, this is just a label, return early
			if ( !props.mainLink && !props.mainValue ) {
				return mw.message( props.messageKey );
			}
			const mainAnchor = createValueNode(
				props.mainLink, props.mainLinkLogId, props.mainValue
			);

			let suffixAnchor = null;
			if ( props.suffixValue !== '' && props.suffixValue !== null && props.suffixValue !== undefined ) {
				suffixAnchor = createValueNode(
					props.suffixLink, props.suffixLinkLogId, props.suffixValue
				);
			}

			if ( suffixAnchor ) {
				// Possible messages here
				// * checkuser-userinfocard-active-blocks
				// * checkuser-userinfocard-local-edits
				// * checkuser-userinfocard-thanks
				// * checkuser-userinfocard-checks
				// * checkuser-userinfocard-active-blocks-from-all-wikis
				// * checkuser-userinfocard-active-blocks-from-all-wikis-with-local
				// * checkuser-userinfocard-checks-empty
				// * checkuser-userinfocard-past-blocks
				return mw.message( props.messageKey, mainAnchor, suffixAnchor ).parse();
			} else {
				// Possible messages here
				// * checkuser-userinfocard-global-edits
				// * checkuser-userinfocard-new-articles
				return mw.message( props.messageKey, mainAnchor ).parse();
			}
		} );

		const tooltipMessage = computed( () => {
			if ( !props.tooltipKey ) {
				return null;
			}

			// Possible messages here
			// * checkuser-userinfocard-temporary-account-bucketcount-tooltip
			// * HACK: pass eslint; other tooltips should also be named *-tooltip
			return mw.message( props.tooltipKey ).parse();
		} );

		// We need to attach the click handlers manually here.
		// v-html won't retain the listeners, so adding them in formattedMessage won't work.
		// Track the attached listeners so we can detach them before re-attaching
		// (the v-html re-render swaps in fresh nodes).
		let attachedHandlers = [];

		function detachClickHandlers() {
			attachedHandlers.forEach( ( { element, handler } ) => {
				element.removeEventListener( 'click', handler );
			} );
			attachedHandlers = [];
		}

		function attachClickHandler( logId ) {
			const element = document.getElementById( `info-row-${ logId }` );
			if ( !element ) {
				return;
			}
			const handler = () => onLinkClick( logId );
			element.addEventListener( 'click', handler );
			attachedHandlers.push( { element, handler } );
		}

		function attachClickHandlers() {
			detachClickHandlers();
			if ( props.mainLink && props.mainLinkLogId ) {
				attachClickHandler( props.mainLinkLogId );
			}
			if ( props.suffixLink && props.suffixLinkLogId ) {
				attachClickHandler( props.suffixLinkLogId );
			}
		}

		onMounted( () => {
			nextTick( attachClickHandlers );
		} );

		watch( formattedMessage, () => {
			nextTick( attachClickHandlers );
		} );

		return {
			formattedMessage,
			tooltipMessage
		};
	}
};
</script>
