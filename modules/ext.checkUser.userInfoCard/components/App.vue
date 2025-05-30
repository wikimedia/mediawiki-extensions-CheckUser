<template>
	<cdx-popover
		v-model:open="isOpen"
		:anchor="currentTrigger"
		placement="bottom-start"
		:render-in-place="true"
		class="ext-checkuser-userinfocard-popover"
	>
		<!-- Empty container for teleported content -->
		<div
			v-if="isOpen"
			ref="cardContainer"
			class="ext-checkuser-userinfocard-container"
		></div>
	</cdx-popover>

	<!--
		Separate cached component, visually attached to popover.
		CdxPopover mounts/destroys child component, so caching with <keep-alive>
		only works when the component is mounted outside the popover.
	-->
	<keep-alive>
		<user-card-view
			v-if="isOpen"
			:key="componentKey"
			:user-id="userId"
			:wiki-id="wikiId"
			:container="cardContainer"
			@close="close"
		></user-card-view>
	</keep-alive>
</template>

<script>
const { ref, computed } = require( 'vue' );
const { CdxPopover } = require( '@wikimedia/codex' );
const UserCardView = require( './UserCardView.vue' );

// @vue/component
module.exports = exports = {
	name: 'App',
	components: {
		CdxPopover,
		UserCardView
	},
	setup() {
		const isOpen = ref( false );
		const currentTrigger = ref( null );
		const userId = ref( null );
		const wikiId = ref( null );
		const cardContainer = ref( null );

		// Methods
		function open( target ) {
			currentTrigger.value = target;
			isOpen.value = true;
		}

		function close() {
			isOpen.value = false;
			currentTrigger.value = null;
		}

		function setUserInfo( newUserId, newWikiId ) {
			userId.value = newUserId;
			wikiId.value = newWikiId;
		}

		// Using userId as key to ensure component is cached when user changes
		const componentKey = computed( () => userId.value || 'default' );

		return {
			isOpen,
			currentTrigger,
			userId,
			wikiId,
			cardContainer,
			open,
			close,
			setUserInfo,
			componentKey
		};
	},
	expose: [
		'open',
		'setUserInfo'
	]
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-popover {
	min-width: @size-2400;
}

.ext-checkuser-userinfocard-container {
	display: contents;
}

// Overwrite cdx-popover__body overflow because of the menu button in the header
.ext-checkuser-userinfocard-popover .cdx-popover__body {
	overflow: unset;
}
</style>
