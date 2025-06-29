<template>
	<!-- Header teleport - only when data is ready -->
	<teleport :to="headerContainer" :disabled="!headerContainer">
		<user-card-header
			v-if="!loading && !error"
			:username="userCard.username"
			:user-page-url="userCard.userPageUrl"
			:user-page-is-known="userCard.userPageIsKnown"
			:user-page-watched="userCard.userPageWatched"
			@close="$emit( 'close' )"
		></user-card-header>
	</teleport>

	<!-- Body teleport - handles all states -->
	<teleport :to="bodyContainer" :disabled="!bodyContainer">
		<user-card-loading-view v-if="loading"></user-card-loading-view>

		<user-info-card-error
			v-else-if="error"
			:message="error"
		></user-info-card-error>

		<div v-else class="ext-checkuser-userinfocard-view">
			<user-card-body
				:username="userCard.username"
				:joined-date="userCard.joinedDate"
				:joined-relative="userCard.joinedRelativeTime"
				:active-blocks="userCard.activeBlocksCount"
				:past-blocks="userCard.pastBlocksCount"
				:global-edits="userCard.globalEditCount"
				:groups="userCard.groups"
				:global-groups="userCard.globalGroups"
				:local-edits="userCard.localEditCount"
				:local-edits-reverted="userCard.localEditRevertedCount"
				:new-articles="userCard.newArticlesCount"
				:thanks-received="userCard.thanksReceivedCount"
				:thanks-sent="userCard.thanksGivenCount"
				:checks="userCard.checkUserChecks"
				:last-checked="userCard.checkUserLastCheck"
				:active-wikis="userCard.activeWikis"
				:recent-local-edits="userCard.recentLocalEdits"
				:total-local-edits="userCard.totalLocalEdits"
				:has-edit-in-last-60-days="userCard.hasEditInLast60Days"
			></user-card-body>
		</div>
	</teleport>
</template>

<script>
const UserCardBody = require( './UserCardBody.vue' );
const UserCardHeader = require( './UserCardHeader.vue' );
const UserCardLoadingView = require( './UserCardLoadingView.vue' );
const UserInfoCardError = require( './UserInfoCardError.vue' );
const DateFormatter = require( 'mediawiki.DateFormatter' );
const { processEditCountByDay, parseMediaWikiTimestamp } = require( '../util.js' );

// @vue/component
module.exports = exports = {
	name: 'UserCardView',
	components: {
		UserCardHeader,
		UserCardBody,
		UserCardLoadingView,
		UserInfoCardError
	},
	props: {
		username: {
			type: [ String ],
			required: true
		},
		headerContainer: {
			type: [ Object, HTMLElement ],
			default: null
		},
		bodyContainer: {
			type: [ Object, HTMLElement ],
			default: null
		}
	},
	emits: [ 'close' ],
	setup( props ) {
		const { ref, reactive, onActivated, onMounted } = require( 'vue' );

		// State
		const loading = ref( false );
		const error = ref( null );
		const userCard = reactive( {
			userPageUrl: '',
			userPageIsKnown: false,
			username: '',
			joinedDate: '',
			joinedRelativeTime: '',
			globalEditCount: 0,
			thanksReceivedCount: 0,
			thanksGivenCount: 0,
			activeBlocksCount: 0,
			pastBlocksCount: 0,
			localEditCount: 0,
			localEditRevertedCount: 0,
			newArticlesCount: 0,
			checkUserChecks: 0,
			checkUserLastCheck: '',
			activeWikis: {},
			groups: '',
			globalGroups: '',
			userPageWatched: false
		} );

		// Methods
		function fetchUserInfo() {
			if ( !props.username || props.username.trim().length === 0 ) {
				return;
			}

			loading.value = true;
			error.value = null;

			const token = mw.user.tokens.get( 'csrfToken' );
			const rest = new mw.Rest();
			const payload = {
				token,
				username: props.username
			};

			rest.post( '/checkuser/v0/userinfo', payload )
				.then( ( userInfo ) => {
					if ( !userInfo ) {
						throw new Error( mw.msg( 'checkuser-userinfocard-error-no-data' ) );
					}

					const {
						name,
						firstRegistration,
						globalEditCount,
						thanksReceived,
						thanksGiven,
						userPageIsKnown,
						newArticlesCount,
						totalEditCount,
						activeLocalBlocksAllWikis,
						pastBlocksOnLocalWiki,
						revertedEditCount,
						userPageWatched,
						checkUserChecks,
						checkUserLastCheck,
						activeWikis,
						groups,
						globalGroups
					} = userInfo;
					const userTitleObj = mw.Title.makeTitle( 2, name );
					const userPageUrl = userTitleObj.getUrl();
					const { processedData, totalEdits } = processEditCountByDay(
						userInfo.editCountByDay
					);

					// Update reactive state
					userCard.userPageUrl = userPageUrl;
					userCard.userPageIsKnown = !!userPageIsKnown;
					userCard.username = name;

					// Parse and format firstRegistration date
					const firstRegDate = parseMediaWikiTimestamp( firstRegistration );
					userCard.joinedDate = firstRegDate ?
						DateFormatter.formatDate( firstRegDate ) :
						'';
					userCard.joinedRelativeTime = firstRegDate ?
						DateFormatter.formatRelativeTimeOrDate( firstRegDate ) :
						'';

					userCard.globalEditCount = globalEditCount;
					userCard.thanksReceivedCount = thanksReceived;
					userCard.thanksGivenCount = thanksGiven;
					userCard.recentLocalEdits = processedData;
					userCard.hasEditInLast60Days = processedData.some( ( item ) => item.count > 0 );
					userCard.totalLocalEdits = totalEdits;
					userCard.newArticlesCount = newArticlesCount;
					userCard.localEditCount = totalEditCount;
					userCard.localEditRevertedCount = revertedEditCount;
					userCard.userPageWatched = !!userPageWatched;
					userCard.activeBlocksCount = activeLocalBlocksAllWikis;
					userCard.pastBlocksCount = pastBlocksOnLocalWiki;
					userCard.checkUserChecks = checkUserChecks;

					// Parse and format checkUserLastCheck date
					const lastCheckDate = parseMediaWikiTimestamp( checkUserLastCheck );
					userCard.checkUserLastCheck = lastCheckDate ?
						DateFormatter.formatDate( lastCheckDate ) :
						'';
					userCard.activeWikis = !activeWikis || Array.isArray( activeWikis ) ?
						{} : activeWikis;
					userCard.groups = groups;
					userCard.globalGroups = globalGroups;

					loading.value = false;
				} )
				.catch( ( err, errOptions ) => {
					// Retrieving the error message from mw.Rest().post()
					const { xhr } = errOptions || {};
					const responseJSON = ( xhr && xhr.responseJSON ) || {};
					const userLang = mw.config.get( 'wgUserLanguage' );
					if (
						responseJSON.messageTranslations &&
						responseJSON.messageTranslations[ userLang ]
					) {
						error.value = responseJSON.messageTranslations[ userLang ];
					} else if ( err.message ) {
						error.value = err.message;
					} else {
						error.value = mw.msg( 'checkuser-userinfocard-error-generic' );
					}
					loading.value = false;
				} );
		}

		// Lifecycle hooks for keep-alive - triggered on every activation
		onActivated( () => {
			if ( !userCard.username && !loading.value ) {
				fetchUserInfo();
			}
		} );

		// Regular Vue lifecycle hook - triggered only once per key (username)
		onMounted( () => {
			fetchUserInfo();
		} );

		return {
			loading,
			error,
			userCard
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-view {
	display: flex;
	flex-direction: column;
}
</style>
