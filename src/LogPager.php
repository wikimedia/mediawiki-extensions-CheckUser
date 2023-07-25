<?php

namespace MediaWiki\CheckUser;

use Html;
use IContextSource;
use Linker;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use SpecialPage;
use ReverseChronologicalPager;
use Wikimedia\Rdbms\IResultWrapper;

class LogPager extends ReverseChronologicalPager {
	/**
	 * @var array
	 */
	protected $searchConds;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param IContextSource $context
	 * @param array $conds Should include 'queryConds', 'year', and 'month' keys
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		IContextSource $context,
		array $conds,
		LinkBatchFactory $linkBatchFactory,
		UserFactory $userFactory
	) {
		parent::__construct( $context );
		$this->searchConds = $conds['queryConds'];
		// getDateCond() actually *sets* the timestamp offset..
		$this->getDateCond( $conds['year'], $conds['month'] );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->userFactory = $userFactory;
	}

	public function formatRow( $row ) {
		$performerHidden = $this->userFactory->newFromUserIdentity(
			UserIdentityValue::newRegistered( $row->cul_user, $row->user_name )
		)->isHidden();
		if ( $performerHidden && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
			// Performer of the check is hidden and the logged in user does not have
			//  right to see hidden users.
			$user = Html::element(
				'span',
				[ 'class' => 'history-deleted' ],
				$this->msg( 'rev-deleted-user' )->text()
			);
		} else {
			$user = Linker::userLink( $row->cul_user, $row->user_name );
			if ( $performerHidden ) {
				// Performer is hidden, but current user has rights to see it.
				// Mark the username has hidden by wrapping it in a history-deleted span.
				$user = Html::rawElement(
					'span',
					[ 'class' => 'history-deleted' ],
					$user
				);
			}
			$user .= $this->msg( 'word-separator' )->escaped()
				. Html::rawElement( 'span', [ 'classes' => 'mw-usertoollinks' ],
					$this->msg( 'parentheses' )->params( $this->getLinkRenderer()->makeLink(
						SpecialPage::getTitleFor( 'CheckUserLog' ),
						$this->msg( 'checkuser-log-checks-by' )->text(),
						[],
						[
							'cuInitiator' => $row->user_name,
						]
					) )->text()
				);
		}

		$targetHidden = $this->userFactory->newFromUserIdentity(
			new UserIdentityValue( $row->cul_target_id, $row->cul_target_text )
		)->isHidden();
		if ( $targetHidden && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
			// Target of the check is hidden and the logged in user does not have
			//  right to see hidden users.
			$target = Html::element(
				'span',
				[ 'class' => 'history-deleted' ],
				$this->msg( 'rev-deleted-user' )->text()
			);
		} else {
			$target = Linker::userLink( $row->cul_target_id, $row->cul_target_text );
			if ( $targetHidden ) {
				// Target is hidden, but current user has rights to see it.
				// Mark the username has hidden by wrapping it in a history-deleted span.
				$target = Html::rawElement(
					'span',
					[ 'class' => 'history-deleted' ],
					$target
				);
			}
			$target .= Linker::userToolLinks( $row->cul_target_id, trim( $row->cul_target_text ) );
		}

		$lang = $this->getLanguage();
		$contextUser = $this->getUser();
		// Give grep a chance to find the usages:
		// checkuser-log-entry-userips, checkuser-log-entry-ipedits,
		// checkuser-log-entry-ipusers, checkuser-log-entry-ipedits-xff
		// checkuser-log-entry-ipusers-xff, checkuser-log-entry-useredits
		$rowContent = $this->msg(
			'checkuser-log-entry-' . $row->cul_type,
			$user,
			$target,
			htmlspecialchars(
				$lang->userTimeAndDate( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser )
			),
			htmlspecialchars(
				$lang->userDate( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser )
			),
			htmlspecialchars(
				$lang->userTime( wfTimestamp( TS_MW, $row->cul_timestamp ), $contextUser )
			)
		)->text();
		$rowContent .= Linker::commentBlock( $row->cul_reason );

		$attribs = [
			'data-mw-culogid' => $row->cul_id,
		];
		return Html::rawElement( 'li', $attribs, $rowContent ) . "\n";
	}

	/**
	 * @return string
	 */
	public function getStartBody() {
		if ( $this->getNumRows() ) {
			return '<ul>';
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public function getEndBody() {
		if ( $this->getNumRows() ) {
			return '</ul>';
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public function getEmptyBody() {
		return '<p>' . $this->msg( 'checkuser-empty' )->escaped() . '</p>';
	}

	public function getQueryInfo() {
		return [
			'tables' => [ 'cu_log', 'user' ],
			'fields' => $this->selectFields(),
			'conds' => array_merge( $this->searchConds, [ 'user_id = cul_user' ] )
		];
	}

	public function getIndexField() {
		return 'cul_timestamp';
	}

	public function selectFields() {
		return [
			'cul_id', 'cul_timestamp', 'cul_user', 'cul_reason', 'cul_type',
			'cul_target_id', 'cul_target_text', 'user_name'
		];
	}

	/**
	 * Do a batch query for links' existence and add it to LinkCache
	 *
	 * @param IResultWrapper $result
	 */
	protected function preprocessResults( $result ) {
		if ( $this->getNumRows() === 0 ) {
			return;
		}

		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );
		foreach ( $result as $row ) {
			// Performer
			$lb->add( NS_USER, $row->user_name );

			if ( $row->cul_type == 'userips' || $row->cul_type == 'useredits' ) {
				$lb->add( NS_USER, $row->cul_target_text );
				$lb->add( NS_USER_TALK, $row->cul_target_text );
			}
		}
		$lb->execute();
		$result->seek( 0 );
	}
}
