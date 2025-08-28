<?php
/*
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

namespace MediaWiki\CheckUser\SuggestedInvestigations;

use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialSuggestedInvestigations extends SpecialPage {

	public function __construct(
		private readonly CommentStore $commentStore
	) {
		parent::__construct( 'SuggestedInvestigations', 'checkuser' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		$this->addNavigationLinks();

		$this->addHelpLink( 'Help:Extension:CheckUser/Suggested investigations' );
		$this->getOutput()->addWikiMsg( 'checkuser-suggestedinvestigations-summary' );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'checkuser-suggestedinvestigations' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Returns an array of navigation links to be added to the subtitle area of the page.
	 * The syntax is [ message key => special page name ].
	 */
	private function getNavigationLinks(): array {
		$links = [
			'checkuser' => 'CheckUser',
			'checkuser-investigate' => 'Investigate',
		];

		if ( $this->getUser()->isAllowed( 'checkuser-log' ) ) {
			$links['checkuser-showlog'] = 'CheckUserLog';
		}

		return $links;
	}

	/**
	 * Adds navigation links to the subtitle area of the page.
	 */
	private function addNavigationLinks(): void {
		$links = $this->getNavigationLinks();

		if ( count( $links ) ) {
			$subtitle = '';
			foreach ( $links as $message => $page ) {
				$subtitle .= Html::rawElement(
					'span',
					[],
					$this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( $page ),
						$this->msg( $message )->text()
					)
				);
			}

			$this->getOutput()->addSubtitle( Html::rawElement(
				'span',
				[ 'class' => 'mw-checkuser-links-no-parentheses' ],
				$subtitle
			) );
		}
	}
}
