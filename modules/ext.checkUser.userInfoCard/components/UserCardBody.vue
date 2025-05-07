<template>
	<div class="ext-checkuser-userinfocard-body">
		<p>{{ joinedLabel }}: {{ joinedDate }} ({{ joinedRelative }})</p>
		<p
			v-for="( row, idx ) in infoRows"
			:key="idx"
			class="ext-checkuser-userinfocard-short-paragraph"
		>
			<cdx-icon
				:icon="row.icon"
				size="small"
				:class="row.iconClass"
			></cdx-icon>
			<!-- TODO: T393946 Add links -->
			{{ row.text }}
		</p>
		<p
			v-if="activeWikis && activeWikis.length > 0"
			class="ext-checkuser-userinfocard-active-wikis"
		>
			{{ activeWikisLabel }}: {{ activeWikis.join( ', ' ) }}
		</p>
	</div>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const {
	cdxIconAlert,
	cdxIconEdit,
	cdxIconArticles,
	cdxIconHeart,
	cdxIconSearch
} = require( './icons.json' );

// @vue/component
module.exports = exports = {
	name: 'UserCard',
	components: { CdxIcon },
	props: {
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
		const activeWikisLabel = mw.msg( 'checkuser-userinfocard-active-wikis-label' );

		const infoRows = [
			{
				icon: cdxIconAlert,
				iconClass: 'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks',
				text: mw.msg(
					'checkuser-userinfocard-active-blocks-row',
					props.activeBlocks,
					props.pastBlocks
				)
			},
			{
				icon: cdxIconEdit,
				iconClass: 'ext-checkuser-userinfocard-icon',
				text: mw.msg( 'checkuser-userinfocard-global-edits-row', props.globalEdits )
			},
			{
				icon: cdxIconEdit,
				iconClass: 'ext-checkuser-userinfocard-icon',
				text: mw.msg(
					'checkuser-userinfocard-local-edits-row',
					props.localEdits,
					props.localEditsReverted
				)
			},
			{
				icon: cdxIconArticles,
				iconClass: 'ext-checkuser-userinfocard-icon',
				text: mw.msg( 'checkuser-userinfocard-new-articles-row', props.newArticles )
			},
			{
				icon: cdxIconHeart,
				iconClass: 'ext-checkuser-userinfocard-icon',
				text: mw.msg(
					'checkuser-userinfocard-thanks-row',
					props.thanksReceived,
					props.thanksSent
				)
			},
			{
				icon: cdxIconSearch,
				iconClass: 'ext-checkuser-userinfocard-icon',
				text: mw.msg(
					'checkuser-userinfocard-checks-row',
					props.checks,
					props.lastChecked
				)
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
