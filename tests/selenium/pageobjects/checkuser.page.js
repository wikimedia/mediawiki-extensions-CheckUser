'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class CheckUserPage extends Page {
	get hasPermissionErrors() { return $( '.permissions-errors' ); }
	get checkTarget() { return $( '#checktarget input' ); }
	get checkTypeRadios() { return $( '#checkuserradios' ); }
	get getIPsCheckTypeRadio() { return $( '#checkuserradios input[value="subuserips"]' ); }
	get getEditsCheckTypeRadio() { return $( '#checkuserradios input[value="subedits"]' ); }
	get getUsersCheckTypeRadio() { return $( '#checkuserradios input[value="subipusers"]' ); }
	get durationSelector() { return $( '#period' ); }
	get checkReasonInput() { return $( '#checkreason input' ); }
	get submit() { return $( '#checkusersubmit button' ); }
	get getIPsResults() { return $( '.mw-checkuser-get-ips-results' ); }
	get getEditsResults() { return $( '.mw-checkuser-get-edits-results' ); }
	get getUsersResults() { return $( '.mw-checkuser-get-users-results' ); }

	async open() {
		await super.openTitle( 'Special:CheckUser' );
	}
}

module.exports = new CheckUserPage();
