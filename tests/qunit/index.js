'use strict';

QUnit.dump.maxDepth = 999;

// List all test files here.
require( './ext.checkUser/checkuser/getUsersBlockForm.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/utils.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/createTableText.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/createTable.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/generateData.test.js' );
require( './ext.checkUser.clientHints/index.test.js' );
require( './ext.checkUser/investigate/blockform.test.js' );
require( './ext.checkUser/temporaryaccount/ipRevealUtils.test.js' );
require( './ext.checkUser/temporaryaccount/ipReveal.test.js' );
require( './ext.checkUser/temporaryaccount/initOnLoad.test.js' );
require( './ext.checkUser/temporaryaccount/initOnHook.test.js' );
require( './ext.checkUser/temporaryaccount/rest.test.js' );
require( './ext.checkUser/temporaryaccount/SpecialBlock.test.js' );
require( './ext.checkUser/temporaryaccount/SpecialContributions.test.js' );
