<template>
	<cdx-popover
		v-model:open="store.isOpen"
		:anchor="store.currentTrigger"
		placement="bottom-start"
		:render-in-place="true"
		:use-close-button="true"
	>
		User ID: {{ store.userCard.userId }}
	</cdx-popover>
</template>

<script>
const { CdxPopover } = require( '@wikimedia/codex' );
const useUserInfoCardPopoverStore = require( '../stores/UserInfoCardPopover.js' );

// @vue/component
module.exports = exports = {
	name: 'App',
	components: {
		CdxPopover
	},
	setup() {
		const store = useUserInfoCardPopoverStore();

		return {
			store
		};
	},
	expose: [
		'store'
	]
};
</script>
