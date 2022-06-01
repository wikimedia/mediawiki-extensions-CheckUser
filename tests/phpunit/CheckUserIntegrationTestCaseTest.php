<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWikiIntegrationTestCase;

class CheckUserIntegrationTestCaseTest extends MediaWikiIntegrationTestCase {

	/**
	 * Needed because there is no way using TestUser.php
	 * to get a user guaranteed to not exist.
	 *
	 * @return \User a non-existent user or a fail.
	 */
	public function getNonExistentTestUser() {
		$testUser = $this->getServiceContainer()->getUserFactory()->newFromName( 'NonexistentUser 1234' );
		if ( $testUser->getId() !== 0 ) {
			$this->fail( 'User:NonexistentUser 1234 exists. Are you sure you are using a test DB?' );
		}
		return $testUser;
	}
}
