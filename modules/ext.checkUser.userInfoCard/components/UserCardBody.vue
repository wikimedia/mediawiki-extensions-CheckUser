<template>
	<div class="ext-checkuser-userinfocard-body">
		<p>{{ joinedLabel }}: {{ joinedDate }} ({{ joinedRelative }})</p>
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
			v-if="activeWikis && activeWikis.length > 0"
			class="ext-checkuser-userinfocard-active-wikis"
		>
			{{ activeWikisLabel }}: {{ activeWikis.join( ', ' ) }}
		</p>
	</div>
</template>

<script>
const {
	cdxIconAlert,
	cdxIconEdit,
	cdxIconArticles,
	cdxIconHeart,
	cdxIconSearch
} = require( './icons.json' );
const InfoRowWithLinks = require( './InfoRowWithLinks.vue' );

// @vue/component
module.exports = exports = {
	name: 'UserCard',
	components: { InfoRowWithLinks },
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
			type: Array,
			default: () => []
		}
	},
	setup( props ) {
		const joinedLabel = mw.msg( 'checkuser-userinfocard-joined-label' );
		// TODO: T394461 - mount the links for the active wikis once we start receiving from the API
		const activeWikisLabel = mw.msg( 'checkuser-userinfocard-active-wikis-label' );

		const activeBlocksLink = mw.Title.makeTitle( -1, 'BlockList' ).getUrl(
			{ wpTarget: props.username, limit: 50, wpFormIdentifier: 'blocklist' }
		);
		const pastBlocksLink = mw.Title.makeTitle( -1, 'Log/block' ).getUrl(
			{ user: props.username }
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
			{ user: props.username }
		);
		const thanksSentLink = mw.Title.makeTitle( -1, 'Log/thanks' ).getUrl(
			{ page: props.username }
		);
		const checksLink = mw.Title.makeTitle( -1, 'CheckUserLog' ).getUrl(
			{ cuSearch: props.username }
		);

		const infoRows = [
			{
				icon: cdxIconAlert,
				iconClass: 'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks',
				mainLabel: mw.msg( 'checkuser-userinfocard-active-blocks-row-main-label' ),
				mainValue: props.activeBlocks,
				mainLink: activeBlocksLink,
				suffixLabel: mw.msg( 'checkuser-userinfocard-active-blocks-row-suffix-label' ),
				suffixValue: props.pastBlocks,
				suffixLink: pastBlocksLink
			},
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
				mainValue: props.newArticles,
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
			},
			{
				icon: cdxIconSearch,
				iconClass: 'ext-checkuser-userinfocard-icon',
				mainLabel: mw.msg( 'checkuser-userinfocard-checks-row-main-label' ),
				mainValue: props.checks,
				mainLink: checksLink,
				suffixLabel: mw.msg( 'checkuser-userinfocard-checks-row-suffix-label' ),
				suffixValue: props.lastChecked
			}
		];

		return {
			joinedLabel,
			activeWikisLabel,
			infoRows
		};
	}
};
</script>

<style>
.ext-checkuser-userinfocard-body {
	padding: 0;
}

p.ext-checkuser-userinfocard-short-paragraph {
	margin: 0 0 0.15rem;
}

.ext-checkuser-userinfocard-icon {
	margin-right: 0.25rem;
	color: var(--color-subtle);
}

.ext-checkuser-userinfocard-icon.ext-checkuser-userinfocard-icon-blocks {
	color: var(--color-icon-warning);
}

p.ext-checkuser-userinfocard-active-wikis {
	margin-top: 1rem;
}
</style>
