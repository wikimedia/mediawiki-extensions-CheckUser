<template>
	<div class="ext-checkuser-userinfocard-activity-chart">
		<c-sparkline
			:id="`user-activity-${ userId }`"
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

// @vue/component
module.exports = exports = {
	name: 'UserActivityChart',
	components: { CSparkline },
	props: {
		userId: {
			type: String,
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
		const activityChartLabel = mw.msg(
			'checkuser-userinfocard-activity-chart-label', props.totalLocalEdits
		);

		return {
			activityChartLabel
		};
	}
};
</script>

<style>
.ext-checkuser-userinfocard-activity-chart {
	margin-top: 1rem;
}
</style>
