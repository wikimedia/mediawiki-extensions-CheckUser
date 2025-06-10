<template>
	<header class="ext-checkuser-userinfocard-header cdx-popover__header">
		<div class="ext-checkuser-userinfocard-header-main">
			<cdx-icon :icon="cdxIconUserAvatar"></cdx-icon>
			<div class="ext-checkuser-userinfocard-header-userinfo">
				<div class="ext-checkuser-userinfocard-header-username">
					<a
						:href="userPageUrl"
						:class="[ userPageExists ? 'mw-userlink' : 'new' ]"
					>{{ username }}</a>
				</div>
			</div>
		</div>
		<div class="ext-checkuser-userinfocard-header-controls">
			<user-card-menu :user-id="userId" :username="username"></user-card-menu>
			<cdx-button
				:aria-label="closeAriaLabel"
				weight="quiet"
				@click="$emit( 'close' )"
			>
				<cdx-icon :icon="cdxIconClose"></cdx-icon>
			</cdx-button>
		</div>
	</header>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const UserCardMenu = require( './UserCardMenu.vue' );
const { cdxIconUserAvatar, cdxIconClose } = require( './icons.json' );

// @vue/component
module.exports = exports = {
	name: 'UserCardHeader',
	components: {
		CdxIcon,
		CdxButton,
		UserCardMenu
	},
	props: {
		userId: {
			type: [ String, Number ],
			required: true
		},
		username: {
			type: String,
			required: true
		},
		userPageUrl: {
			type: String,
			required: true
		},
		userPageExists: {
			type: Boolean,
			required: true
		}
	},
	emits: [ 'close' ],
	setup() {
		const closeAriaLabel = mw.msg( 'checkuser-userinfocard-close-button-aria-label' );

		return {
			cdxIconUserAvatar,
			cdxIconClose,
			closeAriaLabel
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
	align-items: center;
	margin-bottom: @spacing-50;
}

.ext-checkuser-userinfocard-header-main {
	display: flex;
	align-items: center;
	gap: @spacing-50;
}

.ext-checkuser-userinfocard-header-userinfo {
	display: flex;
	align-items: center;
	gap: @spacing-25;
}

.ext-checkuser-userinfocard-header-username {
	margin: @spacing-0;
	font-weight: @font-weight-bold;
	font-size: @font-size-large;
	line-height: @line-height-x-small;
}

.ext-checkuser-userinfocard-header-controls {
	display: flex;
	align-items: center;
	gap: @spacing-25;
}
</style>
