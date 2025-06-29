<template>
	<p class="ext-checkuser-userinfocard-short-paragraph">
		<cdx-icon
			v-if="icon"
			:icon="icon"
			:class="iconClass"
		></cdx-icon>
		<!--
		Security Note: This use of v-html is considered acceptable because:
		- Props are set via internal API with no user input (from UserCardBody.vue)
		- MediaWiki messages used here do not include unescaped placeholders
		- Anchor content is escaped with jQuery's .text()
		-->
		<!-- eslint-disable-next-line vue/no-v-html -->
		<span v-html="formattedMessage"></span>
	</p>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const { computed, onMounted, watch, nextTick } = require( 'vue' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'InfoRowWithLinks',
	components: { CdxIcon },
	props: {
		icon: { type: [ String, Object ], default: null },
		iconClass: { type: String, default: '' },
		messageKey: { type: String, required: true },
		mainValue: { type: [ String, Number ], default: '' },
		mainLink: { type: String, default: '' },
		mainLinkLogId: { type: String, default: '' },
		suffixValue: { type: [ String, Number ], default: '' },
		suffixLink: { type: String, default: '' },
		suffixLinkLogId: { type: String, default: '' }
	},
	setup( props ) {
		const logEvent = useInstrument();

		function onLinkClick( logId ) {
			logEvent( 'link_click', {
				subType: logId || 'unknown',
				source: 'card_body'
			} );
		}

		const formattedMessage = computed( () => {
			// If no main anchor or main value, this is just a label, return early
			if ( !props.mainLink && !props.mainValue ) {
				return mw.message( props.messageKey );
			}
			// FIXME: Remove jQuery usage for this functionality (T398172)
			// Create jQuery anchor objects for the links
			// We could do that in i18n messages, but we need to log the event on click
			const mainAnchor = props.mainLink ?
				$( '<a>' )
					.attr( 'id', `info-row-${ props.mainLinkLogId }` )
					.attr( 'href', props.mainLink )
					.text( props.mainValue ) :
				$( '<span>' ).text( props.mainValue );

			let suffixAnchor = null;
			if ( props.suffixValue !== '' && props.suffixValue !== null && props.suffixValue !== undefined ) {
				suffixAnchor = props.suffixLink ?
					$( '<a>' )
						.attr( 'id', `info-row-${ props.suffixLinkLogId }` )
						.attr( 'href', props.suffixLink )
						.text( props.suffixValue ) :
					$( '<span>' ).text( props.suffixValue );
			}

			if ( suffixAnchor ) {
				// Possible messages here
				// * checkuser-userinfocard-active-blocks
				// * checkuser-userinfocard-local-edits
				// * checkuser-userinfocard-thanks
				// * checkuser-userinfocard-checks
				// * checkuser-userinfocard-active-blocks-from-all-wikis
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

		// We need to attach the click handlers manually here.
		// v-html won't retain the listeners, so adding them in formattedMessage won't work.
		function attachClickHandlers() {
			// FIXME: Remove jQuery usage for this functionality (T398172)
			if ( props.mainLink && props.mainLinkLogId ) {
				$( `#info-row-${ props.mainLinkLogId }` )
					.off( 'click' )
					.on( 'click', () => onLinkClick( props.mainLinkLogId ) );
			}
			if ( props.suffixLink && props.suffixLinkLogId ) {
				$( `#info-row-${ props.suffixLinkLogId }` )
					.off( 'click' )
					.on( 'click', () => onLinkClick( props.suffixLinkLogId ) );
			}
		}

		onMounted( () => {
			nextTick( attachClickHandlers );
		} );

		watch( formattedMessage, () => {
			nextTick( attachClickHandlers );
		} );

		return {
			formattedMessage
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

p.ext-checkuser-userinfocard-short-paragraph {
	margin: @spacing-0 @spacing-0 @spacing-25;

	/* stylelint-disable-next-line selector-class-pattern */
	.cdx-icon {
		min-width: @size-100;
		min-height: @size-100;
		width: @size-100;
		height: @size-100;
	}
}
</style>
