<template>
	<cdx-button
		v-if="ready"
		weight="quiet"
		size="small"
		:aria-label="ariaLabel"
		@click.prevent="togglePopover( this, username )"
		@mousedown.prevent>
		<cdx-icon :icon="iconData" size="small"></cdx-icon>
	</cdx-button>
</template>

<script>
const { CdxButton, CdxIcon } = require( '../codex.js' );
const { cdxIconUserAvatar, cdxIconUserBlocked, cdxIconUserTemporary } = require( './icons.json' );
const { isUserInfoCardEnabled } = require( '../util.js' );
const rest = new mw.Rest();

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
			blocked: false
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
			return this.blocked ? cdxIconUserBlocked :
				mw.util.isTemporaryUser( this.username ) ? cdxIconUserTemporary :
					cdxIconUserAvatar;
		}
	},
	methods: {
		togglePopover() {}
	},
	async created() {
		if ( !isUserInfoCardEnabled() ) {
			return;
		}

		const response = await rest.get( `/checkuser/v0/userinfo/blocked/${ this.username }` );

		this.blocked = response.shouldShowBlockedIcon;
		this.ready = true;
	}
};
</script>
