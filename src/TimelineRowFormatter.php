<?php

namespace MediaWiki\CheckUser;

use Html;
use HtmlArmor;
use Language;
use Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionStore;
use Message;
use SpecialPage;
use Title;
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
		User $user,
		Language $language
	) {
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
		$this->revisionLookup = $revisionLookup;
		$this->revisionStore = $revisionStore;
		$this->revisionFactory = $revisionFactory;
		$this->user = $user;
		$this->language = $language;

		$this->preCacheMessages();
	}

	/**
	 * Format cu_changes record and display appropiate information
	 * depending on user privileges
	 *
	 * @param \stdClass $row
	 * @return string
	 */
	public function format( \stdClass $row ) : string {
		return sprintf(
			'%s . . %s . . %s %s %s %s %s',
			$this->getLinks( $row ),
			$this->getTime( $row->cuc_timestamp ),
			$this->getUserLinks( $row ),
			$this->getActionText( $row->cuc_actiontext ),
			$this->getIpInfo( $row->cuc_ip ),
			$this->getUserAgent( $row->cuc_agent ),
			$this->getComment( $row->cuc_comment )
		);
	}

	/**
	 * @param string $comment
	 * @return string
	 */
	private function getComment( string $comment ) : string {
		// Note: this is incomplete. It should match the checks
		// in SpecialCheckUser when displaying the same info
		// $user will be used to determine wether a comment can be
		// displayed or not

		// This method will depend on RevisionLookup, RevisionStore,
		// LoadBalancer and RevisionFactory being injected

		// TODO: Return only after checking that the user
		// has the rights to see the comment
		// return Linker::commentBlock( $comment );
		return '';
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
	private function getLinks( \stdClass $row ) : string {
		// Note: this is incomplete. It should match the checks
		// in SpecialCheckUser when displaying the same info
		$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
		$links = [];

		if ( $row->cuc_type == RC_LOG ) {
			$links['log'] = $this->getLogLink( $title );
		} else {
			$links['diff'] = $this->getDiffLink( $row );
			$links['history'] = $this->getHistoryLink( $row );
			$flags = $this->getFlags( (int)$row->cuc_type, (bool)$row->cuc_minor );

			if ( $flags ) {
				$links += $flags;
			}

			$links['title'] = $this->linkRenderer->makeLink( $title );
		}

		// TODO: add hook and validation

		return implode( ' ', $links );
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function getLogLink( Title $title ) : string {
		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'Log' ),
					new HtmlArmor( $this->message['log'] ),
					[],
					[ 'page' => $title->getPrefixedText() ]
				)
			)->escaped();
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getDiffLink( \stdClass $row ) : string {
		if ( $row->cuc_type == RC_NEW ) {
			return '';
		}

		$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
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
		if ( $row->cuc_type == RC_NEW ) {
			return '';
		}

		$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
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
	 * @param bool $minor
	 * @return array
	 */
	private function getFlags( int $type, bool $minor ) : array {
		$flags = [];
		if ( $type == RC_NEW ) {
			$flags['newpage'] = Html::rawElement( 'span',
				[ 'class' => 'newpage' ],
				$this->message['newpageletter']
			);
		}
		if ( $minor ) {
			$flags['minor'] = Html::rawElement(
				'span',
				[ 'class' => 'minor' ],
				$this->message['minoreditletter']
			);
		}
		return $flags;
	}

	/**
	 * @param string $timestamp
	 * @return string
	 */
	private function getTime( string $timestamp ) : string {
		return htmlspecialchars(
			$this->language->time( wfTimestamp( TS_MW, $timestamp ), true, true )
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
