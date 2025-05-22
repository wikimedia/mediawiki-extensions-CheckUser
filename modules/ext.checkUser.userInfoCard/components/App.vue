<template>
	<cdx-popover
		v-model:open="store.isOpen"
		:anchor="store.currentTrigger"
		placement="bottom-start"
		:render-in-place="true"
		:use-close-button="!!store.error"
		class="ext-checkuser-userinfocard-popover"
	>
		<div
			v-if="store.loading"
			class="ext-checkuser-userinfocard-loading-indicator"
		>
			<cdx-progress-indicator>
				{{ loadingLabel }}
			</cdx-progress-indicator>
		</div>
		<user-info-card-error
			v-if="!store.loading && store.error"
			:message="store.error"
		></user-info-card-error>
		<template v-if="!store.loading && !store.error" #header>
			<user-card-header
				:username="store.userCard.username"
				:user-page-url="store.userCard.userPageUrl"
				:user-page-exists="store.userCard.userPageExists"
				:user-id="store.userCard.userId"
				@close="store.close()"
			></user-card-header>
		</template>
		<user-card-body
			v-if="!store.loading && !store.error"
			:user-id="store.userCard.userId"
			:username="store.userCard.username"
			:joined-date="store.userCard.joinedDate"
			:joined-relative="store.userCard.joinedRelativeTime"
			:active-blocks="store.userCard.activeBlocksCount"
			:past-blocks="store.userCard.pastBlocksCount"
			:global-edits="store.userCard.globalEditCount"
			:local-edits="store.userCard.localEditCount"
			:local-edits-reverted="store.userCard.localEditRevertedCount"
			:new-articles="store.userCard.newArticlesCount"
			:thanks-received="store.userCard.thanksReceivedCount"
			:thanks-sent="store.userCard.thanksGivenCount"
			:checks="store.userCard.checksCount"
			:last-checked="store.userCard.lastCheckedDate"
			:active-wikis="store.userCard.activeWikis"
			:recent-local-edits="store.userCard.recentLocalEdits"
			:total-local-edits="store.userCard.totalLocalEdits"
		></user-card-body>
	</cdx-popover>
</template>

<script>
const { CdxPopover, CdxProgressIndicator } = require( '@wikimedia/codex' );
const useUserInfoCardPopoverStore = require( '../stores/UserInfoCardPopover.js' );
const UserCardBody = require( './UserCardBody.vue' );
const UserCardHeader = require( './UserCardHeader.vue' );
const UserInfoCardError = require( './UserInfoCardError.vue' );

// @vue/component
module.exports = exports = {
	name: 'App',
	components: {
		CdxPopover,
		CdxProgressIndicator,
		UserCardHeader,
		UserCardBody,
		UserInfoCardError
	},
	setup() {
		const store = useUserInfoCardPopoverStore();
		const loadingLabel = mw.msg( 'checkuser-userinfocard-loading-label' );

		return {
			store,
			loadingLabel
		};
	},
	expose: [
		'store'
	]
};
</script>

<style>
.ext-checkuser-userinfocard-popover {
	min-width: 384px;
}

.ext-checkuser-userinfocard-loading-indicator {
	overflow: hidden;
	display: flex;
	justify-content: center;
}
</style>
