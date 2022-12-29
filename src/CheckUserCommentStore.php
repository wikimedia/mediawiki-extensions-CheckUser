<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\CheckUser;

use Language;
use MediaWiki\CommentStore\CommentStoreBase;
use MediaWiki\MediaWikiServices;

class CheckUserCommentStore extends CommentStoreBase {
	/**
	 * @return self
	 */
	public static function getStore() {
		return MediaWikiServices::getInstance()->get( 'CheckUserCommentStore' );
	}

	/**
	 * @param Language $lang
	 * @param int $stage
	 */
	public function __construct( Language $lang, int $stage ) {
		parent::__construct( [], $lang, $stage );
	}
}
