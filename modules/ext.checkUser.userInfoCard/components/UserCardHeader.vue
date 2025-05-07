<template>
	<div class="ext-checkuser-userinfocard-header">
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
			<!-- TODO: T393804 Add menu button -->
			<cdx-button
				:aria-label="openMenuAriaLabel"
				weight="quiet"
			>
				<cdx-icon :icon="cdxIconEllipsis"></cdx-icon>
			</cdx-button>
			<cdx-button
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
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconUserAvatar, cdxIconEllipsis, cdxIconClose } = require( './icons.json' );

// @vue/component
module.exports = exports = {
	name: 'UserCardHeader',
	components: {
		CdxIcon,
		CdxButton
	},
	props: {
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
		const openMenuAriaLabel = mw.msg( 'checkuser-userinfocard-open-menu-aria-label' );
		const closeAriaLabel = mw.msg( 'checkuser-userinfocard-close-button-aria-label' );

		return {
			cdxIconUserAvatar,
			cdxIconEllipsis,
			cdxIconClose,
			closeAriaLabel,
			openMenuAriaLabel
		};
	}
};
</script>

<style>
.ext-checkuser-userinfocard-header {
	width: 100%;
	min-width: 350px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.ext-checkuser-userinfocard-header-main {
	display: flex;
	align-items: center;
	gap: 0.5em;
}

.ext-checkuser-userinfocard-header-userinfo {
	display: flex;
	align-items: center;
	gap: 0.25em;
}

.ext-checkuser-userinfocard-header-username {
	margin: 0;
	font-weight: 600;
	font-size: 1.25em;
}

.ext-checkuser-userinfocard-header-controls {
	display: flex;
	align-items: center;
	gap: 0.25em;
}
</style>
