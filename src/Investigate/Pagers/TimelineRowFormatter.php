<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use Html;
use HtmlArmor;
use Language;
use Linker;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserRigorOptions;
use Message;
use TitleFormatter;
use TitleValue;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

class TimelineRowFormatter {

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var RevisionStore */
	private $revisionStore;

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

	/** @var UserFactory */
	private $userFactory;

	/** @var CommentFormatter */
	private $commentFormatter;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param ILoadBalancer $loadBalancer
	 * @param RevisionStore $revisionStore
	 * @param TitleFormatter $titleFormatter
	 * @param SpecialPageFactory $specialPageFactory
	 * @param CommentFormatter $commentFormatter
	 * @param UserFactory $userFactory
	 * @param User $user
	 * @param Language $language
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer,
		RevisionStore $revisionStore,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		User $user,
		Language $language
	) {
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
		$this->revisionStore = $revisionStore;
		$this->titleFormatter = $titleFormatter;
		$this->specialPageFactory = $specialPageFactory;
		$this->commentFormatter = $commentFormatter;
		$this->userFactory = $userFactory;
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
	public function getFormattedRowItems( \stdClass $row ): array {
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
				'userAgent' => $this->getUserAgent( $row->cuc_agent ?? '' ),
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
	private function getComment( \stdClass $row ): string {
		$comment = '';

		if (
			$row->cuc_this_oldid != 0 &&
			( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW )
		) {
			$revRecord = $this->revisionStore->getRevisionById( $row->cuc_this_oldid );
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
					$revRecord = $this->revisionStore->newRevisionFromArchiveRow( $archiveRow );
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
				$comment = $this->commentFormatter->formatRevision( $revRecord, $this->user );
			} else {
				$comment = $this->commentFormatter->formatBlock(
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
	private function getIpInfo( string $ip ): string {
		// Note: in the old check user this links to self with ip as target. Can't do now
		// because of token. We could prefill a new investigation tab
		return IPUtils::prettifyIP( $ip );
	}

	/**
	 * @param string $userAgent
	 * @return string
	 */
	private function getUserAgent( string $userAgent ): string {
		return htmlspecialchars( $userAgent );
	}

	/**
	 * @param string $actionText
	 * @return string
	 */
	private function getActionText( string $actionText ): string {
		return $this->commentFormatter->format( $actionText );
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getTitleLink( \stdClass $row ): string {
		if ( $row->cuc_type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}

		// Hide the title link if the title for a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
			return '';
		}

		return $this->linkRenderer->makeLink(
			$title,
			null,
			[ 'class' => 'ext-checkuser-investigate-timeline-row-title' ]
		);
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getLogLink( \stdClass $row ): string {
		if ( $row->cuc_type != RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}

		// Hide the 'logs' link if the title is a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
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
	private function getDiffLink( \stdClass $row ): string {
		if ( $row->cuc_type == RC_NEW || $row->cuc_type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}

		// Hide the diff link if the title for a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
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
	private function getHistoryLink( \stdClass $row ): string {
		if ( $row->cuc_type == RC_NEW || $row->cuc_type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->cuc_namespace, $row->cuc_title );

		if ( !$title ) {
			return '';
		}

		// Hide the history link if the title for a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
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
	private function getNewPageFlag( int $type ): string {
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
	private function getMinorFlag( bool $minor ): string {
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
	private function getTime( string $timestamp ): string {
		return htmlspecialchars(
			$this->language->userTime( wfTimestamp( TS_MW, $timestamp ), $this->user )
		);
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getUserLinks( \stdClass $row ): string {
		// Note: this is incomplete. It should match the checks
		// in SpecialCheckUser when displaying the same info

		if ( $this->isUserHidden( $row->cuc_user_text ) ) {
			return Html::element(
				'span',
				[ 'class' => 'history-deleted mw-history-suppressed' ],
				$this->msg( 'rev-deleted-user' )->text()
			);
		}

		$userId = $row->cuc_user;
		if ( $userId > 0 ) {
			$user = $this->userFactory->newFromId( $userId );
		} else {
			// This is an IP
			$user = $this->userFactory->newFromName( $row->cuc_user_text, UserRigorOptions::RIGOR_NONE );
		}

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
	private function msg( string $key, array $params = [] ): Message {
		return new Message( $key, $params, $this->language );
	}

	/**
	 * Should a given username should be hidden from the current user.
	 *
	 * @param string $username
	 * @return bool
	 */
	private function isUserHidden( string $username ): bool {
		$user = $this->userFactory->newFromName( $username );
		return $user !== null && $user->isHidden() && !$this->user->isAllowed( 'hideuser' );
	}
}
