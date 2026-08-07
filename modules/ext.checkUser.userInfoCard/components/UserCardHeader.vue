<template>
	<div
		class="ext-checkuser-userinfocard-header"
		@mouseleave="resetCopied"
	>
		<div class="ext-checkuser-userinfocard-header-main">
			<cdx-icon
				:icon="userIcon"
				class="ext-checkuser-userinfocard-header-icon"></cdx-icon>
			<div class="ext-checkuser-userinfocard-header-userinfo">
				<span
					ref="focusTrapRef"
					tabindex="-1"></span>
				<div class="ext-checkuser-userinfocard-header-username">
					<a
						:href="userPageUrl"
						:class="[ userPageIsKnown ? 'mw-userlink' : 'new' ]"
						@click="onUsernameClick"
					>{{ username }}</a>
				</div>
				<cdx-button
					v-if="canCopy"
					v-tooltip:bottom="copyLabel"
					class="ext-checkuser-userinfocard-header-copy-button"
					:aria-label="copyLabel"
					weight="quiet"
					@click="onCopyClick"
				>
					<cdx-icon
						:icon="copied ? cdxIconCheck : cdxIconCopy"
						size="small"></cdx-icon>
				</cdx-button>
			</div>
		</div>
		<div class="ext-checkuser-userinfocard-header-controls">
			<user-card-menu
				:username="username"
				:user-page-watched="userPageWatched"
				:special-central-auth-url="specialCentralAuthUrl"
				:gender="gender"
			></user-card-menu>
			<cdx-button
				class="ext-checkuser-userinfocard-header-close-button"
				:aria-label="closeAriaLabel"
				weight="quiet"
				@click="$emit( 'close' )"
			>
				<cdx-icon :icon="cdxIconClose"></cdx-icon>
			</cdx-button>
		</div>
	</div>
</template>

<script>
const { ref, onActivated, onMounted, nextTick, computed } = require( 'vue' );
const { CdxIcon, CdxButton, CdxTooltip } = require( '../codex.js' );
const UserCardMenu = require( './UserCardMenu.vue' );
const {
	cdxIconUserAvatar, cdxIconUserTemporary, cdxIconClose, cdxIconUserBlocked,
	cdxIconCopy, cdxIconCheck
} = require( './icons.json' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'UserCardHeader',
	components: {
		CdxIcon,
		CdxButton,
		UserCardMenu
	},
	directives: {
		tooltip: CdxTooltip
	},
	props: {
		username: {
			type: String,
			required: true
		},
		gender: {
			type: String,
			default: 'unknown'
		},
		userPageUrl: {
			type: String,
			required: true
		},
		userPageIsKnown: {
			type: Boolean,
			required: true
		},
		userPageWatched: {
			type: Boolean,
			default: false
		},
		specialCentralAuthUrl: {
			type: String,
			default: ''
		},
		hasLocalBlockGlobalBlockOrLock: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'close' ],
	setup( props ) {
		const focusTrapRef = ref();
		const logEvent = useInstrument();
		const closeAriaLabel = mw.msg( 'checkuser-userinfocard-close-button-aria-label' );
		const userIcon = computed( () => {
			if ( props.hasLocalBlockGlobalBlockOrLock ) {
				return cdxIconUserBlocked;
			}
			return mw.util.isTemporaryUser( props.username ) ?
				cdxIconUserTemporary : cdxIconUserAvatar;
		} );

		function onUsernameClick() {
			logEvent( 'link_click', {
				subType: 'user_page',
				source: 'card_header',
				context: JSON.stringify( { username: props.username } )
			} );
		}

		// Don't offer a broken copy button
		// eslint-disable-next-line compat/compat
		const canCopy = !!( navigator.clipboard && navigator.clipboard.writeText );
		const copied = ref( false );
		const copyLabel = computed( () => mw.msg(
			copied.value ?
				'checkuser-userinfocard-copy-username-copied' :
				'checkuser-userinfocard-copy-username-button-aria-label'
		) );

		/**
		 * Return the copy button to its initial state, e.g. once the pointer
		 * leaves the header and the button is hidden again.
		 */
		function resetCopied() {
			copied.value = false;
		}

		/**
		 * Copy the user name to the clipboard.
		 *
		 * @return {Promise} Resolved once the copy has been handled. Returned
		 *   for the benefit of the tests; the template ignores it.
		 */
		function onCopyClick() {
			logEvent( 'copy_username', {
				source: 'card_header',
				context: JSON.stringify( { username: props.username } )
			} );

			// eslint-disable-next-line compat/compat
			return navigator.clipboard.writeText( props.username ).then( () => {
				copied.value = true;
			} ).catch( ( e ) => {
				mw.errorLogger.logError( e, 'error.checkuser' );
				mw.notify(
					mw.msg( 'checkuser-userinfocard-copy-username-error' ),
					{ type: 'error' }
				);
			} );
		}

		function focusOnRef() {
			// Wait for the DOM to update before focusing
			nextTick( () => {
				if ( focusTrapRef.value ) {
					focusTrapRef.value.focus( { preventScroll: true } );
				}
			} );
		}

		onMounted( () => {
			focusOnRef();
		} );

		onActivated( () => {
			focusOnRef();
			resetCopied();
		} );

		return {
			userIcon,
			cdxIconClose,
			cdxIconCopy,
			cdxIconCheck,
			closeAriaLabel,
			canCopy,
			copied,
			copyLabel,
			onCopyClick,
			resetCopied,
			onUsernameClick,
			focusTrapRef
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-header {
	width: @size-full;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}

.ext-checkuser-userinfocard-header-main {
	display: flex;
	align-items: flex-start;
	gap: @spacing-50;
	flex: 1;
	min-width: 0;

	.ext-checkuser-userinfocard-header-icon {
		height: @size-200;
	}
}

.ext-checkuser-userinfocard-header-userinfo {
	display: flex;
	align-items: center;
	align-self: center;
	gap: @spacing-25;
	flex: 1;
	min-width: 0;
}

.ext-checkuser-userinfocard-header-username {
	margin: @spacing-0;
	font-weight: @font-weight-bold;
	line-height: @line-height-small;
	word-break: break-word;
	overflow-wrap: break-word;
	min-width: 0;
}

.ext-checkuser-userinfocard-header-copy-button {
	// To align this button with more and close, which are not vertically-centered
	align-self: flex-start;

	// If the user has a traditional pointer, show the button only on hovering the header
	@media ( pointer: fine ) {
		opacity: 0;

		&:focus-visible,
		.ext-checkuser-userinfocard-header:hover & {
			opacity: 1;
		}
	}
}

.ext-checkuser-userinfocard-header-controls {
	display: flex;
	align-items: flex-start;
	gap: @spacing-25;
}
</style>
