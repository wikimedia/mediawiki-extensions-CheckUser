'use strict';

const moment = require( 'moment' );

/**
 * Process edit count data to include only the last 60 days and fill in missing dates with 0
 *
 * @param {Object} editCountByDay Raw edit count data from API { "YYYY-MM-DD": count, ... }
 * @return {Object} Object with processed edit count data and total edit count
 *   { processedData: [{ date: Date, count: number }], totalEdits: number }
 */
function processEditCountByDay( editCountByDay ) {
	const rawData = editCountByDay || {};

	const sixtyDaysAgo = moment().subtract( 60, 'days' );
	const processedData = [];
	let totalEdits = 0;

	for ( let i = 0; i <= 60; i++ ) {
		const date = moment( sixtyDaysAgo ).add( i, 'days' );
		const dateStr = date.format( 'YYYY-MM-DD' );

		if ( rawData[ dateStr ] ) {
			processedData.push( { date: date.toDate(), count: rawData[ dateStr ] } );
			totalEdits += rawData[ dateStr ];
		} else {
			processedData.push( { date: date.toDate(), count: 0 } );
		}
	}

	return { processedData, totalEdits };
}

module.exports = {
	processEditCountByDay
};
