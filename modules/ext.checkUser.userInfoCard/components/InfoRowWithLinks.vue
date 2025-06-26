<template>
	<p class="ext-checkuser-userinfocard-short-paragraph">
		<cdx-icon
			v-if="icon"
			:icon="icon"
			:class="iconClass"
		></cdx-icon>
		{{ mainLabel }}:
		<a
			v-if="mainLink"
			:href="mainLink"
			@click="onLinkClick( mainLinkLogId )"
		>
			{{ mainValue }}
		</a>
		<span v-else>
			{{ mainValue }}
		</span>
		<template v-if="suffixLabel && suffixValue !== ''">
			({{ suffixLabel }}:
			<a
				v-if="suffixLink"
				:href="suffixLink"
				@click="onLinkClick( suffixLinkLogId )"
			>
				{{ suffixValue }}
			</a>
			<span v-else>
				{{ suffixValue }}
			</span>)
		</template>
	</p>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'InfoRowWithLinks',
	components: { CdxIcon },
	props: {
		icon: { type: [ String, Object ], default: null },
		iconClass: { type: String, default: '' },
		mainLabel: { type: String, default: '' },
		mainValue: { type: [ String, Number ], default: '' },
		mainLink: { type: String, default: '' },
		mainLinkLogId: { type: String, default: '' },
		suffixLabel: { type: String, default: '' },
		suffixValue: { type: [ String, Number ], default: '' },
		suffixLink: { type: String, default: '' },
		suffixLinkLogId: { type: String, default: '' }
	},
	setup() {
		const logEvent = useInstrument();

		function onLinkClick( logId ) {
			logEvent( 'link_click', {
				subType: logId || 'unknown',
				source: 'card_body'
			} );
		}

		return {
			onLinkClick
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

p.ext-checkuser-userinfocard-short-paragraph {
	margin: @spacing-0 @spacing-0 @spacing-25;

	.cdx-icon {
		min-width: @size-100;
		min-height: @size-100;
		width: @size-100;
		height: @size-100;
	}
}
</style>
