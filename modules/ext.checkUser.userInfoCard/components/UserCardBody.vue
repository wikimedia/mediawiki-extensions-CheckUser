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
			:suffix-label="row.suffixLabel"
			:suffix-value="row.suffixValue"
			:suffix-link="row.suffixLink"
		></info-row-with-links>
		<p
			v-if="activeWikisList && activeWikisList.length > 0"
			class="ext-checkuser-userinfocard-active-wikis"
		>
			{{ activeWikisLabel }}:
			<template v-for="( wiki, idx ) in activeWikisList" :key="idx">
				<a :href="wiki.url" class="mw-userlink">
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
	cdxIconSearch
} = require( './icons.json' );
const InfoRowWithLinks = require( './InfoRowWithLinks.vue' );
const UserActivityChart = require( './UserActivityChart.vue' );

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
		const joinedLabel = mw.msg( 'checkuser-userinfocard-joined-label' );

		const activeWikisLabel = mw.msg( 'checkuser-userinfocard-active-wikis-label' );
		const activeWikisList = computed( () => Object.keys( props.activeWikis ).map(
			( wikiId ) => ( { wikiId, url: props.activeWikis[ wikiId ] } )
		) );

		const activeBlocksLink = mw.Title.makeTitle( -1, 'BlockList' ).getUrl(
			{ wpTarget: props.username, limit: 50, wpFormIdentifier: 'blocklist' }
		);
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
				rows.push( {
					icon: cdxIconAlert,
					iconClass: 'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks',
					mainLabel: mw.msg( 'checkuser-userinfocard-active-blocks-row-main-label' ),
					mainValue: props.activeBlocks,
					mainLink: activeBlocksLink,
					suffixLabel: mw.msg( 'checkuser-userinfocard-active-blocks-row-suffix-label' ),
					suffixValue: props.pastBlocks,
					suffixLink: pastBlocksLink
				} );
			}

			rows.push( ...[
				{
					icon: cdxIconEdit,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-global-edits-row-main-label' ),
					mainValue: props.globalEdits,
					mainLink: globalEditsLink
				},
				{
					icon: cdxIconEdit,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-local-edits-row-main-label' ),
					mainValue: props.localEdits,
					mainLink: localEditsLink,
					suffixLabel: mw.msg( 'checkuser-userinfocard-local-edits-row-suffix-label' ),
					suffixValue: props.localEditsReverted,
					suffixLink: localEditsLink
				},
				{
					icon: cdxIconArticles,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-new-articles-row-main-label' ),
					mainValue: formattedNewArticles,
					mainLink: newArticlesLink
				},
				{
					icon: cdxIconHeart,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-thanks-row-main-label' ),
					mainValue: props.thanksReceived,
					mainLink: thanksReceivedLink,
					suffixLabel: mw.msg( 'checkuser-userinfocard-thanks-row-suffix-label' ),
					suffixValue: props.thanksSent,
					suffixLink: thanksSentLink
				}
			] );

			if ( canViewCheckUserLog ) {
				rows.push( {
					icon: cdxIconSearch,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainLabel: mw.msg( 'checkuser-userinfocard-checks-row-main-label' ),
					mainValue: props.checks,
					mainLink: checksLink,
					suffixLabel: mw.msg( 'checkuser-userinfocard-checks-row-suffix-label' ),
					suffixValue: props.lastChecked
				} );
			}
			return rows;
		} );

		return {
			joinedLabel,
			activeWikisLabel,
			activeWikisList,
			infoRows
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
