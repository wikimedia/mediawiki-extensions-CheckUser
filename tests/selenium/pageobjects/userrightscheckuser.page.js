'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class UserRightsPageForCheckUserTests extends Page {
	get checkuserCheckbox() { return $( 'input.mw-userrights-groupcheckbox#wpGroup-checkuser' ); }
	get saveUserGroups() { return $( 'input[name=saveusergroups]' ); }

	open( username ) {
		super.openTitle( 'Special:UserRights/' + username );
	}

	async grantCheckUserToUser( username ) {
		await this.open( username );
		if ( !await this.checkuserCheckbox.isSelected() ) {
			await this.checkuserCheckbox.click();
			await this.saveUserGroups.click();
		}
	}

	async removeCheckUserFromUser( username ) {
		await this.open( username );
		if ( await this.checkuserCheckbox.isSelected() ) {
			await this.checkuserCheckbox.click();
			await this.saveUserGroups.click();
		}
	}
}

module.exports = new UserRightsPageForCheckUserTests();
