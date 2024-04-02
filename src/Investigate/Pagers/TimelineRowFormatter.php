<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use Html;
use HtmlArmor;
use Language;
use Linker;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\ArchivedRevisionLookup;
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

class TimelineRowFormatter {
	private LinkRenderer $linkRenderer;
	private RevisionStore $revisionStore;
	private ArchivedRevisionLookup $archivedRevisionLookup;
	private TitleFormatter $titleFormatter;
	private SpecialPageFactory $specialPageFactory;
	private UserFactory $userFactory;
	private CommentFormatter $commentFormatter;

	private array $message = [];

	private Language $language;

	private User $user;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param RevisionStore $revisionStore
	 * @param ArchivedRevisionLookup $archivedRevisionLookup
	 * @param TitleFormatter $titleFormatter
	 * @param SpecialPageFactory $specialPageFactory
	 * @param CommentFormatter $commentFormatter
	 * @param UserFactory $userFactory
	 * @param User $user
	 * @param Language $language
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		RevisionStore $revisionStore,
		ArchivedRevisionLookup $archivedRevisionLookup,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		User $user,
		Language $language
	) {
		$this->linkRenderer = $linkRenderer;
		$this->revisionStore = $revisionStore;
		$this->archivedRevisionLookup = $archivedRevisionLookup;
		$this->titleFormatter = $titleFormatter;
		$this->specialPageFactory = $specialPageFactory;
		$this->commentFormatter = $commentFormatter;
		$this->userFactory = $userFactory;
		$this->user = $user;
		$this->language = $language;

		$this->preCacheMessages();
	}

	/**
	 * Format cu_changes record and display appropriate information
	 * depending on user privileges
	 *
	 * @param \stdClass $row
	 * @return string[][]
	 */
	public function getFormattedRowItems( \stdClass $row ): array {
		$revRecord = null;
		if (
			$row->cuc_this_oldid != 0 &&
			( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW )
		) {
			$revRecord = $this->revisionStore->getRevisionById( $row->cuc_this_oldid );
			if ( !$revRecord ) {
				// Revision may have been deleted
				$revRecord = $this->archivedRevisionLookup->getArchivedRevisionRecord( null, $row->cuc_this_oldid );
			}
		}
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
				'userLinks' => $this->getUserLinks( $row, $revRecord ),
				'actionText' => $this->getActionText( $row->cuc_actiontext ),
				'ipInfo' => $this->getIpInfo( $row->cuc_ip ),
				'userAgent' => $this->getUserAgent( $row->cuc_agent ?? '' ),
				'comment' => $this->getComment( $row, $revRecord ),
			],
		];
	}

	/**
	 * Show the comment, or redact if appropriate. If the revision is not found,
	 * show nothing.
	 *
	 * @param \stdClass $row
	 * @param RevisionRecord|null $revRecord
	 * @return string
	 */
	private function getComment( \stdClass $row, ?RevisionRecord $revRecord ): string {
		$comment = '';

		if (
			$row->cuc_this_oldid != 0 &&
			( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW )
		) {
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
	 * @param RevisionRecord|null $revRecord
	 * @return string
	 */
	private function getUserLinks( \stdClass $row, ?RevisionRecord $revRecord ): string {
		// Note: this is incomplete. It should match the checks
		// in SpecialCheckUser when displaying the same info
		$userIsHidden = $this->isUserHidden( $row->cuc_user_text );
		$userHiddenClass = '';
		if ( $userIsHidden ) {
			$userHiddenClass = 'history-deleted mw-history-suppressed';
		}
		if (
			!$userIsHidden &&
			$row->cuc_this_oldid != 0 &&
			( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW ) &&
			$revRecord instanceof RevisionRecord
		) {
			$userIsHidden = !RevisionRecord::userCanBitfield(
				$revRecord->getVisibility(),
				RevisionRecord::DELETED_USER,
				$this->user
			);
			$userHiddenClass = Linker::getRevisionDeletedClass( $revRecord );
		}
		if ( $userIsHidden ) {
			return Html::element(
				'span',
				[ 'class' => $userHiddenClass ],
				$this->msg( 'rev-deleted-user' )->text()
			);
		} else {
			$userId = $row->cuc_user ?? 0;
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
