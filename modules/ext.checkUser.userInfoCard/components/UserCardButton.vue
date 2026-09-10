<template>
	<cdx-button
		v-if="ready"
		weight="quiet"
		size="small"
		:aria-label="ariaLabel"
		@click.prevent="togglePopover( $el, username )"
		@mousedown.prevent>
		<cdx-icon :icon="iconData" size="small"></cdx-icon>
	</cdx-button>
</template>

<script>
const { CdxButton, CdxIcon } = require( '../codex.js' );
const {
	cdxIconSuggestedInvestigations,
	cdxIconUserAvatar,
	cdxIconUserBlocked,
	cdxIconUserTemporary
} = require( './icons.json' );
const {
	getCustomIconVariant,
	getDefaultIconVariant,
	isUserInfoCardEnabled,
	setCustomIconVariant
} = require( '../util.js' );
const { getUserIconVariant } = require( '../rest.js' );

const iconsByVariant = {
	suggestedInvestigations: cdxIconSuggestedInvestigations,
	userAvatar: cdxIconUserAvatar,
	userBlocked: cdxIconUserBlocked,
	userTemporary: cdxIconUserTemporary
};

// @vue/component
module.exports = exports = {
	name: 'UserCardButton',
	components: { CdxButton, CdxIcon },
	props: {
		username: {
			type: String,
			required: true
		}
	},
	data() {
		return {
			ready: false,
			iconVariant: 'userAvatar'
		};
	},
	computed: {
		ariaLabel() {
			return mw.msg(
				'checkuser-userinfocard-toggle-button-aria-label',
				this.username
			);
		},
		iconData() {
			return iconsByVariant[ this.iconVariant ] || cdxIconUserAvatar;
		}
	},
	methods: {
		togglePopover() {}
	},
	watch: {
		username: {
			immediate: true,
			async handler( username ) {
				// Until the server answers, show what the name itself tells us, so that the
				// icon of the user before cannot stay on the button.
				this.iconVariant = getDefaultIconVariant( username );

				if ( !isUserInfoCardEnabled() ) {
					return;
				}

				// If the icon for that user is already known, reuse it instead of asking
				// the server again.
				const customIconVariant = getCustomIconVariant( username );
				if ( customIconVariant ) {
					this.iconVariant = customIconVariant;
				} else {
					try {
						const iconVariant = await getUserIconVariant( username );
						// Record the icon for any future uses.
						setCustomIconVariant( username, iconVariant );
						// While the request is in progress, the username prop can theoretically change,
						// only use response for the current user
						if ( username === this.username ) {
							this.iconVariant = iconVariant;
						}
					} catch ( e ) {
						// The icon is only a hint, and the button works without it, so keep the
						// icon which the name implies instead of hiding the button.
					}
				}

				if ( username === this.username ) {
					this.ready = true;
				}
			}
		}
	}
};
</script>
