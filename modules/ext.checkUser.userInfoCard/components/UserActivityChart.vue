<template>
	<div class="ext-checkuser-userinfocard-activity-chart">
		<c-sparkline
			:id="componentId"
			:title="activityChartLabel"
			:data="recentLocalEdits"
			:dimensions="{ width: 300, height: 24 }"
			x-accessor="date"
			y-accessor="count"
		></c-sparkline>
	</div>
	<p>{{ activityChartLabel }}</p>
</template>

<script>
const CSparkline = require( '../../vue-components/CSparkline.vue' );
const { hashUsername } = require( '../util.js' );

// @vue/component
module.exports = exports = {
	name: 'UserActivityChart',
	components: { CSparkline },
	props: {
		username: {
			type: [ String, Number ],
			required: true
		},
		recentLocalEdits: {
			// Expected format: [ { date: Date, count: number }, ... ]
			type: Array,
			required: true
		},
		totalLocalEdits: {
			type: Number,
			required: true
		}
	},
	setup( props ) {
		const componentId = `user-activity-${ hashUsername( props.username ) }`;
		const activityChartLabel = mw.msg(
			'checkuser-userinfocard-activity-chart-label', props.totalLocalEdits
		);

		return {
			activityChartLabel,
			componentId
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-activity-chart {
	margin-top: @spacing-100;
}
</style>
