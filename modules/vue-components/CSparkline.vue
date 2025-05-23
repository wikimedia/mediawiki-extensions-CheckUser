<template>
	<svg
		:id="`sparkline-${ id }`"
		xmlns="http://www.w3.org/2000/svg"
		class="ext-checkuser-CSparkline"
	>
		<title>
			{{ title }}
		</title>
	</svg>
</template>

<script>
const { onMounted } = require( 'vue' );
const d3 = require( '../lib/d3/d3.min.js' );
let chart, sparkline, area = null;

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	props: {
		title: {
			type: String,
			required: true
		},
		id: {
			type: String,
			required: true
		},
		data: {
			type: Object,
			required: true
		},
		dimensions: {
			type: Object,
			required: true
		},
		xAccessor: {
			type: String,
			required: true
		},
		yAccessor: {
			type: String,
			required: true
		}
	},
	setup( props ) {
		const plot = () => {
			chart.attr( 'viewBox', `0 0 ${ props.dimensions.width } ${ props.dimensions.height }` );
			// Create accessor functions from property names
			const getXValue = ( d ) => d[ props.xAccessor ];
			const getYValue = ( d ) => d[ props.yAccessor ];

			const xDomain = d3.extent( props.data, getXValue );
			const xScale = d3.scaleTime()
				.domain( xDomain )
				.range( [ 0, props.dimensions.width ] );
			// Get the maximum value from the Y data, or use 10 if all values are 0
			// This ensures the chart displays an accurate representation of the data
			// so the line doesn't stay in the middle of the graph
			const maxY = d3.max( props.data, getYValue ) || 10;
			const yDomain = [ 0, maxY ];

			const yScale = d3.scaleLinear()
				.domain( yDomain )
				// Flip svg Y-axis coordinate system and add some a pixel on top to avoid cutting
				// off anti-aliasing pixels. Do not add a pixel on the bottom, that would make the
				// graph non-0-based, and it's rare for the pageviews to be 0.
				.range( [ props.dimensions.height, 1 ] );

			const lineGenerator = d3.line()
				.x( ( d ) => xScale( getXValue( d ) ) )
				.y( ( d ) => yScale( getYValue( d ) ) );

			const areaGenerator = d3.area()
				.x( ( d ) => xScale( getXValue( d ) ) )
				.y1( ( d ) => yScale( getYValue( d ) ) )
				.y0( props.dimensions.height );

			sparkline
				.data( [ props.data ] )
				.attr( 'd', lineGenerator )
				.attr( 'stroke-width', 1 )
				.attr( 'stroke-linejoin', 'round' )
				.attr( 'fill', 'none' );
			area
				.data( [ props.data ] )
				.attr( 'd', areaGenerator );
		};

		onMounted( () => {
			chart = d3.select( `#sparkline-${ props.id }` );
			// Append order is relevant. Render the line over the area
			area = chart.append( 'path' ).attr( 'class', 'ext-checkuser-CSparkline__area' );
			sparkline = chart.append( 'path' ).attr( 'class', 'ext-checkuser-CSparkline__line' );
			plot();
		} );

		return {};
	}

};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-CSparkline {
	padding: @spacing-0 @spacing-50;

	&__line {
		stroke: @background-color-progressive--focus;
	}

	&__area {
		fill: @background-color-progressive-subtle;
	}
}
</style>
