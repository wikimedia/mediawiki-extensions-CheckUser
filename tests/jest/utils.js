'use strict';

/**
 * Mocks mw.Api().saveOption() and returns a jest.fn()
 * that is used as the saveOption() method. This can
 * be used to expect that the saveOption() method is
 * called with the correct arguments.
 *
 * @param {boolean} shouldBeSuccessful If the call to mw.Api().saveOption() should be a success
 * @param {Object} [mockResponse] Optional. The response from the API.
 * @param {string} [mockErrorCode] Optional. If the request is mocked to fail, this defines
 *   the error code returned to the reject handler.
 * @return {jest.fn}
 */
function mockApiSaveOption( shouldBeSuccessful, mockResponse, mockErrorCode ) {
	const apiSaveOption = jest.fn();
	if ( shouldBeSuccessful ) {
		apiSaveOption.mockResolvedValue( mockResponse || { options: 'success' } );
	} else {
		// Create a mock that acts like $.Deferred(), which means that the reject
		// handler can be passed two arguments.
		apiSaveOption.mockReturnValue( {
			then: ( _, rejectHandler ) => rejectHandler(
				mockErrorCode || 'errorcode', mockResponse || { error: { info: 'Error' } }
			)
		} );
	}
	jest.spyOn( mw, 'Api' ).mockImplementation( () => ( {
		saveOption: apiSaveOption
	} ) );
	return apiSaveOption;
}

/**
 * Waits for the return value of a given function to be true.
 * Will wait for a maximum of 1 second for the condition to be true.
 *
 * @param {Function} conditionCheck
 */
async function waitFor( conditionCheck ) {
	let tries = 0;
	while ( !conditionCheck() && tries < 20 ) {
		tries++;
		await new Promise( ( resolve ) => {
			setTimeout( () => resolve(), 50 );
		} );
	}
}

/**
 * Mocks mw.config.get to return the values for the specified config and throw if
 * a configuration value not provided is asked for by the code under test.
 *
 * @param {Object.<string, any>} mockConfigValues
 */
function mockJSConfig( mockConfigValues ) {
	jest.spyOn( mw.config, 'get' ).mockImplementation( ( actualConfigName ) => {
		if ( actualConfigName in mockConfigValues ) {
			return mockConfigValues[ actualConfigName ];
		} else {
			throw new Error( 'Did not expect a call to get the value of ' + actualConfigName );
		}
	} );
}

/**
 * Expect that the given element contains the given text, or if
 * this is not the case then wait for this to be the case.
 *
 * @param {*} element The element that should contain the text
 * @param {string} text The text to search for
 */
async function waitForAndExpectTextToExistInElement( element, text ) {
	await waitFor( () => element.text().includes( text ) );
	expect( element.text() ).toContain( text );
}

module.exports = {
	mockApiSaveOption,
	waitFor,
	mockJSConfig,
	waitForAndExpectTextToExistInElement
};
