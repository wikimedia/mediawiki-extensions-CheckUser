'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class CheckUserPage extends Page {
	get hasPermissionErrors() { return $( '.permissions-errors' ); }
	get checkTarget() { return $( '#checktarget input' ); }
	get checkUserRadios() { return $( '#checkuserradios' ); }
	get durationSelector() { return $( '#period' ); }
	get checkReasonInput() { return $( '#checkreason input' ); }
	get checkUserSubmit() { return $( '#checkusersubmit button' ); }
	get checkUserResults() { return $( '#checkuserresults' ); }

	async open() {
		await super.openTitle( 'Special:CheckUser' );
	}
}

module.exports = new CheckUserPage();
