<?php

namespace MediaWiki\CheckUser;

use User;

/**
 * User Manager to wrap static User methods.
 *
 * This service makes it easier to mock static User methods. The service can be removed when a
 * better alternative exists in MediaWiki core.
 *
 * @see https://phabricator.wikimedia.org/T255276
 *
 * @internal
 */
class UserManager {
	/**
	 * Get user ID from a user name.
	 *
	 * @param string $username
	 * @return int|null Id, or null if the username is invalid or non-existent
	 */
	public function idFromName( string $username ) : ?int {
		return User::idFromName( $username );
	}
}
