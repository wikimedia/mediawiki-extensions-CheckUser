'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class VersionPage extends Page {
	get checkuserExtension() { return $( '#mw-version-ext-specialpage-CheckUser' ); }

	open() {
		super.openTitle( 'Special:Version' );
	}
}

module.exports = new VersionPage();
