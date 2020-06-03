<?php

namespace MediaWiki\CheckUser;

use Html;
use HtmlArmor;
use Language;
use Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use Message;
use TitleFormatter;
use TitleValue;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class TimelineRowFormatter {

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var RevisionFactory */
	private $revisionFactory;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	/** @var array */
	private $message = [];

	/** @var Language */
	private $language;

	/** @var User */
	private $user;

	public function __construct(
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer,
		RevisionLookup $revisionLookup,
		RevisionStore $revisionStore,
		RevisionFactory $revisionFactory,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		User $user,
		Language $language
	) {
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
		$this->revisionLookup = $revisionLookup;
		$this->revisionStore = $revisionStore;
		$this->revisionFactory = $revisionFactory;
		$this->titleFormatter = $titleFormatter;
		$this->specialPageFactory = $specialPageFactory;
		$this->user = $user;
		$this->language = $language;

		$this->preCacheMessages();
	}

	/**
	 * Format cu_changes record and display appropiate information
	 * depending on user privileges
	 *
	 * @param \stdClass $row
	 * @return array
	 */
	public function getFormattedRowItems( \stdClass $row ) : array {
		return [
			'links' => [
				'logLink' => $this->getLogLink( $row ),
				'diffLink' => $this->getDiffLink( $row ),
				'historyLink' => $this->getHistoryLink( $row ),
				'newPageFlag' => $this->getNewPageFlag( (int)$row->cuc_type ),
				'minorFlag' => $this->getMinorFlag( (bool)$row->cuc_minor ),
			],
			'info' => [
				'title' => $this->getTitleLink( $row ),
				'time' => $this->getTime( $row->cuc_timestamp ),
				'userLinks' => $this->getUserLinks( $row ),
				'actionText' => $this->getActionText( $row->cuc_actiontext ),
				'ipInfo' => $this->getIpInfo( $row->cuc_ip ),
				'userAgent' => $this->getUserAgent( $row->cuc_agent ),
				'comment' => $this->getComment( $row ),
			],
		];
	}

	/**
	 * Show the comment, or redact if appropriate. If the revision is not found,
	 * show nothing.
	 *
	 * @param \stdClass $row
	 * @return string
	 */
	private function getComment( \stdClass $row ) : string {
		$comment = '';

		if (
			$row->cuc_this_oldid != 0 &&
			( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW )
		) {
			$revRecord = $this->revisionLookup->getRevisionById( $row->cuc_this_oldid );
			if ( !$revRecord ) {
				// Revision may have been deleted
				$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
				$queryInfo = $this->revisionStore->getArchiveQueryInfo();
				$archiveRow = $db->selectRow(
					$queryInfo['tables'],
					$queryInfo['fields'],
					[ 'ar_rev_id' => $row->cuc_this_oldid ],
					__METHOD__,
					[],
					$queryInfo['joins']
				);
				if ( $archiveRow ) {
					$revRecord = $this->revisionFactory->newRevisionFromArchiveRow( $archiveRow );
				}
			}
			if (
				$revRecord instanceof RevisionRecord &&
				RevisionRecord::userCanBitfield(
					$revRecord->getVisibility(),
					RevisionRecord::DELETED_COMMENT,
					$this->user
				)
			) {
				$comment = Linker::revComment( $revRecord );
			} else {
				$comment = Linker::commentBlock(
					$this->msg( 'rev-deleted-comment' )->text(),
					null,
					false,
					null,
					false
				);
			}
		}

		return $comment;
	}

	/**
	 * @param string $ip
	 * @return string
	 */
	private function getIpInfo( string $ip ) : string {
		// Note: in the old check user this links to self with ip as target. Can't do now
		// because of token. We could prefill a new investigation tab
		return $ip;
	}

	/**
	 * @param string $userAgent
	 * @return string
	 */
	private function getUserAgent( string $userAgent ) : string {
		return htmlspecialchars( $userAgent );
	}

	/**
	 * @param string $actionText
	 * @return string
	 */
	private function getActionText( string $actionText ) : string {
		return Linker::formatComment( $actionText );
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getTitleLink( \stdClass $row ) : string {
		if ( $row->cuc_type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}

		return $this->linkRenderer->makeLink( $title );
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getLogLink( \stdClass $row ) : string {
		if ( $row->cuc_type != RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}
		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					new TitleValue( NS_SPECIAL, $this->specialPageFactory->getLocalNameFor( 'Log' ) ),
					new HtmlArmor( $this->message['log'] ),
					[],
					[ 'page' => $this->titleFormatter->getPrefixedText( $title ) ]
				)
			)->escaped();
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getDiffLink( \stdClass $row ) : string {
		if ( $row->cuc_type == RC_NEW || $row->cuc_type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}

		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					$title,
					new HtmlArmor( $this->message['diff'] ),
					[],
					[
						'curid' => $row->cuc_page_id,
						'diff' => $row->cuc_this_oldid,
						'oldid' => $row->cuc_last_oldid
					]
				)
			)->escaped();
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getHistoryLink( \stdClass $row ) : string {
		if ( $row->cuc_type == RC_NEW || $row->cuc_type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}

		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					$title,
					new HtmlArmor( $this->message['hist'] ),
					[],
					[
						'curid' => $row->cuc_page_id,
						'action' => 'history'
					]
				)
			)->escaped();
	}

	/**
	 * @param int $type
	 * @return string
	 */
	private function getNewPageFlag( int $type ) : string {
		if ( $type == RC_NEW ) {
			return Html::rawElement( 'span',
				[ 'class' => 'newpage' ],
				$this->message['newpageletter']
			);
		}
		return '';
	}

	/**
	 * @param bool $minor
	 * @return string
	 */
	private function getMinorFlag( bool $minor ) : string {
		if ( $minor ) {
			return Html::rawElement(
				'span',
				[ 'class' => 'minor' ],
				$this->message['minoreditletter']
			);
		}
		return '';
	}

	/**
	 * @param string $timestamp
	 * @return string
	 */
	private function getTime( string $timestamp ) : string {
		return htmlspecialchars(
			$this->language->userTime( wfTimestamp( TS_MW, $timestamp ), $this->user )
		);
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getUserLinks( \stdClass $row ) : string {
		// Note: this is incomplete. It should match the checks
		// in SpecialCheckUser when displaying the same info
		$user = User::newFromId( $row->cuc_user );
		$userId = $user->getId();

		$links = Html::rawElement(
			'span', [], Linker::userLink( $userId, $user->getName() )
		);

		$links .= Linker::userToolLinksRedContribs(
			$userId,
			$user->getName(),
			$user->getEditCount()
		);

		return $links;
	}

	/**
	 * As we use the same small set of messages in various methods and that
	 * they are called often, we call them once and save them in $this->message
	 */
	private function preCacheMessages() {
		$msgKeys = [ 'diff', 'hist', 'minoreditletter', 'newpageletter', 'blocklink', 'log' ];
		foreach ( $msgKeys as $msg ) {
			$this->message[$msg] = $this->msg( $msg )->escaped();
		}
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @return Message
	 */
	private function msg( string $key, array $params = [] ) : Message {
		return new Message( $key, $params, $this->language );
	}
}
