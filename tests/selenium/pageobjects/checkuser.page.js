'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class CheckUserPage extends Page {
	get checkTarget() {
		return $( '#checktarget input' );
	}

	get getActionsCheckTypeRadio() {
		return $( '#checkuserradios input[type="radio"][value="subactions"]' );
	}

	get checkReasonInput() {
		return $( '#checkreason input' );
	}

	get submit() {
		return $( '#checkusersubmit button' );
	}

	get getActionsResults() {
		return $( '.mw-checkuser-get-actions-results' );
	}

	async open() {
		await super.openTitle( 'Special:CheckUser' );
	}
}

module.exports = new CheckUserPage();
