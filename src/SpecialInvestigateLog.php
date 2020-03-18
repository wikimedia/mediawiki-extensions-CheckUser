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
 * @ingroup SpecialPage
 */

namespace MediaWiki\CheckUser;

use FormSpecialPage;

class SpecialInvestigateLog extends FormSpecialPage {
	/** @var PagerFactory */
	private $pagerFactory;

	public function __construct( PagerFactory $pagerFactory ) {
		parent::__construct( 'InvestigateLog', 'investigate' );

		$this->pagerFactory = $pagerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// Perform access check ourselves. A filters form will be
		// defined in a follow up task and parent::execute()
		// can be used instead
		// @see parent::execute().
		$this->setParameter( $par );
		$this->setHeaders();

		// This will throw exceptions if there's a problem
		$this->checkExecutePermissions( $this->getUser() );

		$securityLevel = $this->getLoginSecurityLevel();
		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			return;
		}
		// @see parent::execute() for above block

		$pager = $this->pagerFactory->createPager( $this->getContext() );
		$this->getOutput()->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);

		$this->addPageSubtitle();
	}

	/**
	 * Add page subtitle linking to Special:Investigate
	 */
	private function addPageSubtitle() {
		$subtitle = $this->getLinkRenderer()->makeKnownLink(
			self::getTitleFor( 'Investigate' ),
			$this->msg( 'checkuser-investigate-log-subtitle' )->text()
		);
		$this->getOutput()->addSubtitle( $subtitle );
	}

	/**
	 * @inheritDoc
	 */
	public function getFormFields() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function requiresWrite() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'checkuser-investigate-log' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		return false;
	}
}
