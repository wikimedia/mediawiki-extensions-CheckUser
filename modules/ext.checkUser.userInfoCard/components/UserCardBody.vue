<template>
	<div class="ext-checkuser-userinfocard-body">
		<p class="ext-checkuser-userinfocard-joined">
			{{ joinedLabel }}: {{ joinedDate }} ({{ joinedRelative }})
		</p>
		<info-row-with-links
			v-for="( row, idx ) in infoRows"
			:key="idx"
			:icon="row.icon"
			:icon-class="row.iconClass"
			:main-label="row.mainLabel"
			:main-value="row.mainValue"
			:main-link="row.mainLink"
			:main-link-log-id="row.mainLinkLogId"
			:suffix-label="row.suffixLabel"
			:suffix-value="row.suffixValue"
			:suffix-link="row.suffixLink"
			:suffix-link-log-id="row.suffixLinkLogId"
		></info-row-with-links>
		<!-- v-html fetched directly from the server -->
		<!-- eslint-disable vue/no-v-html -->
		<p
			v-if="groups && groups.length > 0"
			class="ext-checkuser-userinfocard-groups"
			v-html="formattedGroups"
		>
		</p>
		<!-- eslint-enable vue/no-v-html -->
		<p
			v-if="activeWikisList && activeWikisList.length > 0"
			class="ext-checkuser-userinfocard-active-wikis"
		>
			<strong>{{ activeWikisLabel }}</strong>:
			<template v-for="( wiki, idx ) in activeWikisList" :key="idx">
				<a
					:href="wiki.url"
					@click="onWikiLinkClick( wiki.wikiId )"
				>
					{{ wiki.wikiId }}
				</a>{{ idx < activeWikisList.length - 1 ? ', ' : '' }}
			</template>
		</p>
		<user-activity-chart
			v-if="hasEditInLast60Days"
			:username="username"
			:recent-local-edits="recentLocalEdits"
			:total-local-edits="totalLocalEdits"
		></user-activity-chart>
	</div>
</template>

<script>
const { computed } = require( 'vue' );
const {
	cdxIconAlert,
	cdxIconEdit,
	cdxIconArticles,
	cdxIconHeart,
	cdxIconSearch,
	cdxIconNotice
} = require( './icons.json' );
const InfoRowWithLinks = require( './InfoRowWithLinks.vue' );
const UserActivityChart = require( './UserActivityChart.vue' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'UserCard',
	components: { InfoRowWithLinks, UserActivityChart },
	props: {
		username: {
			type: String,
			default: ''
		},
		joinedDate: {
			type: String,
			default: ''
		},
		joinedRelative: {
			type: String,
			default: ''
		},
		activeBlocks: {
			type: Number,
			default: 0
		},
		pastBlocks: {
			type: Number,
			default: 0
		},
		globalEdits: {
			type: Number,
			default: 0
		},
		localEdits: {
			type: Number,
			default: 0
		},
		localEditsReverted: {
			type: Number,
			default: 0
		},
		newArticles: {
			type: Number,
			default: 0
		},
		thanksReceived: {
			type: Number,
			default: 0
		},
		thanksSent: {
			type: Number,
			default: 0
		},
		checks: {
			type: Number,
			default: 0
		},
		lastChecked: {
			type: String,
			default: ''
		},
		groups: {
			type: String,
			default: ''
		},
		activeWikis: {
			// Active wikis and their URLs
			// Expected format: { [wikiId: string]: string }
			type: Object,
			default: () => ( {} )
		},
		hasEditInLast60Days: {
			type: Boolean,
			default: false
		},
		recentLocalEdits: {
			// Expected format: [ { date: Date, count: number }, ... ]
			type: Array,
			default: () => ( [] )
		},
		totalLocalEdits: {
			type: Number,
			default: 0
		}
	},
	setup( props ) {
		const logEvent = useInstrument();
		const joinedLabel = mw.msg( 'checkuser-userinfocard-joined-label' );

		const formattedGroups = computed( () => props.groups );

		const activeWikisLabel = mw.msg( 'checkuser-userinfocard-active-wikis-label' );
		const activeWikisList = computed( () => Object.keys( props.activeWikis ).map(
			( wikiId ) => ( { wikiId, url: props.activeWikis[ wikiId ] } )
		) );

		const activeBlocksLink = mw.Title.makeTitle(
			-1, `CentralAuth/${ props.username }`
		).getUrl();
		const pastBlocksLink = mw.Title.makeTitle( -1, 'Log/block' ).getUrl(
			{ page: props.username }
		);
		const globalEditsLink = mw.Title.makeTitle(
			-1, `GlobalContributions/${ props.username }`
		).getUrl();
		const localEditsLink = mw.Title.makeTitle(
			-1, `Contributions/${ props.username }`
		).getUrl();
		const newArticlesLink = mw.Title.makeTitle( -1, 'Contributions' ).getUrl(
			{ target: props.username, namespace: 'all', newOnly: 1 }
		);
		const thanksReceivedLink = mw.Title.makeTitle( -1, 'Log/thanks' ).getUrl(
			{ page: props.username }
		);
		const thanksSentLink = mw.Title.makeTitle( -1, 'Log/thanks' ).getUrl(
			{ user: props.username }
		);
		const checksLink = mw.Title.makeTitle( -1, 'CheckUserLog' ).getUrl(
			{ cuSearch: props.username }
		);

		const maxEdits = mw.config.get( 'wgCheckUserGEUserImpactMaxEdits' ) || 1000;
		const canViewCheckUserLog = mw.config.get( 'wgCheckUserCanViewCheckUserLog' );
		const canBlock = mw.config.get( 'wgCheckUserCanBlock' );

		const infoRows = computed( () => {
			const formattedMaxEdits = mw.language.convertNumber( maxEdits );
			const formattedNewArticles = props.newArticles >= maxEdits ?
				mw.msg( 'checkuser-userinfocard-new-articles-exceeds-max-to-display', formattedMaxEdits ) :
				props.newArticles;
			const rows = [];

			if ( canBlock ) {
				// Active blocks row
				rows.push( {
					icon: props.activeBlocks > 0 ? cdxIconAlert : cdxIconNotice,
					iconClass: props.activeBlocks > 0 ?
						'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks' :
						'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-active-blocks-from-all-wikis-main-label' ),
					mainValue: mw.language.convertNumber( props.activeBlocks ),
					mainLink: activeBlocksLink,
					mainLinkLogId: 'active_blocks'
				} );

				// Past blocks row
				rows.push( {
					icon: props.pastBlocks > 0 ? cdxIconAlert : cdxIconNotice,
					iconClass: props.pastBlocks > 0 ?
						'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks' :
						'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-past-blocks-main-label' ),
					mainValue: mw.language.convertNumber( props.pastBlocks ),
					mainLink: pastBlocksLink,
					mainLinkLogId: 'past_blocks'
				} );
			}

			rows.push( ...[
				{
					icon: cdxIconEdit,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-global-edits-row-main-label' ),
					mainValue: props.globalEdits,
					mainLink: globalEditsLink,
					mainLinkLogId: 'global_edits'
				},
				{
					icon: cdxIconEdit,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-local-edits-row-main-label' ),
					mainValue: props.localEdits,
					mainLink: localEditsLink,
					mainLinkLogId: 'local_edits',
					suffixLabel: mw.msg( 'checkuser-userinfocard-local-edits-row-suffix-label' ),
					suffixValue: props.localEditsReverted,
					suffixLink: localEditsLink,
					suffixLinkLogId: 'reverted_local_edits'
				},
				{
					icon: cdxIconArticles,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-new-articles-row-main-label' ),
					mainValue: formattedNewArticles,
					mainLink: newArticlesLink,
					mainLinkLogId: 'new_articles'
				},
				{
					icon: cdxIconHeart,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-thanks-row-main-label' ),
					mainValue: props.thanksReceived,
					mainLink: thanksReceivedLink,
					mainLinkLogId: 'thanks_received',
					suffixLabel: mw.msg( 'checkuser-userinfocard-thanks-row-suffix-label' ),
					suffixValue: props.thanksSent,
					suffixLink: thanksSentLink,
					suffixLinkLogId: 'thanks_sent'
				}
			] );

			if ( canViewCheckUserLog ) {
				rows.push( {
					icon: cdxIconSearch,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-checks-row-main-label' ),
					mainValue: props.checks,
					mainLink: checksLink,
					mainLinkLogId: 'last_checked',
					suffixLabel: mw.msg( 'checkuser-userinfocard-checks-row-suffix-label' ),
					suffixValue: props.lastChecked
				} );
			}
			return rows;
		} );

		function onWikiLinkClick( wikiId ) {
			logEvent( 'link_click', {
				subType: 'active_wiki',
				source: 'card_body',
				context: wikiId
			} );
		}

		return {
			joinedLabel,
			formattedGroups,
			activeWikisLabel,
			activeWikisList,
			infoRows,
			onWikiLinkClick
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-body {
	padding: @spacing-0;
	font-size: @font-size-small;
}

.ext-checkuser-userinfocard-joined {
	margin-top: @spacing-0;
	margin-bottom: @spacing-50;
}

.ext-checkuser-userinfocard-icon {
	margin-right: @spacing-25;
	color: var(--color-subtle);
}

.ext-checkuser-userinfocard-icon.ext-checkuser-userinfocard-icon-blocks {
	color: var(--color-icon-warning);
}

p.ext-checkuser-userinfocard-active-wikis {
	margin-top: @spacing-100;
}
</style>
