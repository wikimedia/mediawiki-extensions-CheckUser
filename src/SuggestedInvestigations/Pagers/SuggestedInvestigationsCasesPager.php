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

namespace MediaWiki\CheckUser\SuggestedInvestigations\Pagers;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Navigation\SuggestedInvestigationsPagerNavigationBuilder;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Navigation\CodexPagerNavigationBuilder;
use MediaWiki\Pager\CodexTablePager;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class SuggestedInvestigationsCasesPager extends CodexTablePager {

	/**
	 * @var int|null When not null, only display the case with this ID. This is currently only
	 *   used for the detail view, where one case is row is displayed.
	 */
	public int|null $caseIdFilter = null;

	/**
	 * @var array An array describing what filters are currently applied, passed to the
	 *   client using a JS config var and used for instrumentation.
	 * @internal Only public for use by {@link SpecialSuggestedInvestigations}
	 */
	public array $appliedFilters;

	/**
	 * @var CaseStatus[] The list of case statuses to be shown in the table. Empty array means all statuses.
	 */
	private array $statusFilter;

	/**
	 * @var string[] If not an empty array, then only show cases with these users in
	 */
	private array $userNamesFilter;

	/**
	 * @var int The number of filters applied (counting all filters present in the filters dialog)
	 */
	private int $numberOfFiltersApplied = 0;

	/**
	 * @var array An array of database signal names to the display name for that signal
	 */
	private array $signalsToDisplayNames = [];

	/**
	 * The unique sort fields for the sort options for unique paginate
	 */
	private const INDEX_FIELDS = [
		'sic_created_timestamp' => [ 'sic_created_timestamp', 'sic_id' ],
		'sic_status' => [ 'sic_status', 'sic_created_timestamp', 'sic_id' ],
	];

	/** Database with the users and cu_log table */
	private IReadableDatabase $localDb;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserEditTracker $userEditTracker,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly CommentFormatter $commentFormatter,
		private readonly CheckUserGlobalContributionsLookup $checkUserGlobalContributionsLookup,
		LinkRenderer $linkRenderer,
		IContextSource $context,
		array $signals
	) {
		// If we didn't set mDb here, the parent constructor would set it to the database replica
		// for the default database domain
		$this->mDb = $this->connectionProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		parent::__construct(
			$this->msg( 'checkuser-suggestedinvestigations-table-caption' )->text(),
			$context,
			$linkRenderer
		);
		// $this->mDefaultLimit does *not* set the actual default limit in the superclass, it's used to constructURLs
		$this->mDefaultLimit = 10;
		$this->mLimit = $this->mRequest->getInt( 'limit', $this->mDefaultLimit );
		if ( $this->mLimit <= 0 ) {
			$this->mLimit = $this->mDefaultLimit;
		}
		if ( $this->mLimit > 100 ) {
			$this->mLimit = 100;
		}
		$this->mLimitsShown = [ 10, 20, 50 ];

		$this->parseFilters();

		$this->localDb = $this->connectionProvider->getReplicaDatabase();

		foreach ( $signals as $signal ) {
			if ( is_array( $signal ) ) {
				if ( array_key_exists( 'displayName', $signal ) ) {
					$this->signalsToDisplayNames[$signal['name']] = $signal['displayName'];
					continue;
				}

				$signal = $signal['name'];
			}

			// For grepping, the currently known signal messages are:
			// * checkuser-suggestedinvestigations-signal-dev-signal-1
			// * checkuser-suggestedinvestigations-signal-dev-signal-2
			$this->signalsToDisplayNames[$signal] = $this->msg(
				'checkuser-suggestedinvestigations-signal-' . $signal
			)->parse();
		}
	}

	/**
	 * Parses the filters set in the request and applies them to the pager.
	 * Intended for calling during execution of {@link self::__construct}
	 */
	private function parseFilters(): void {
		$this->statusFilter = array_filter( array_map(
			CaseStatus::newFromStringName( ... ),
			$this->mRequest->getArray( 'status', [] )
		) );
		if ( count( $this->statusFilter ) !== 0 ) {
			$this->numberOfFiltersApplied++;
		}

		$this->userNamesFilter = array_filter( $this->mRequest->getArray( 'username', [] ) );
		if ( $this->userNamesFilter ) {
			$this->numberOfFiltersApplied++;
		}

		$this->appliedFilters = [
			'status' => array_map(
				static fn ( CaseStatus $status ) => strtolower( $status->name ),
				$this->statusFilter
			),
			'username' => $this->userNamesFilter,
		];
	}

	/**
	 * @inheritDoc
	 * @param string $name
	 * @param null|string|array $value
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		return match ( $name ) {
			'users' => $this->formatUsersCell( $value ),
			'signals' => $this->formatSignalsCell( $value ),
			'sic_created_timestamp' => $this->formatTimestampCell( $value ),
			'sic_status' => $this->formatStatusCell( CaseStatus::from( (int)$value ), $this->mCurrentRow->sic_id ),
			'sic_status_reason' => $this->formatStatusReasonCell(
				$value,
				CaseStatus::from( (int)$this->mCurrentRow->sic_status ),
				$this->mCurrentRow->sic_id
			),
			'actions' => $this->formatActionsCell(
				$this->mCurrentRow->sic_id,
				CaseStatus::from( (int)$this->mCurrentRow->sic_status ),
				$this->mCurrentRow->sic_status_reason
			),
			default => throw new InvalidArgumentException( 'Unknown field name: ' . $name ),
		};
	}

	/**
	 * @param UserIdentity[] $users
	 * @return string
	 */
	private function formatUsersCell( array $users ): string {
		$formattedUsers = Html::openElement( 'ul', [ 'class' => 'mw-checkuser-suggestedinvestigations-users' ] );

		// Hide users after the third by default, but only if we'd be hiding at least two users
		$userHideThreshold = 3;
		if ( count( $users ) <= $userHideThreshold + 1 ) {
			$userHideThreshold++;
		}

		$detailViewLink = $this->getDetailViewTitle( $this->mCurrentRow->sic_url_identifier )->getFullText();

		$useGlobalContribs = $this->specialPageFactory->exists( 'GlobalContributions' ) &&
			$this->getConfig()->get( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' );
		$contributionsSpecialPage = $useGlobalContribs ? 'GlobalContributions' : 'Contributions';

		foreach ( $users as $i => $user ) {
			$userLink = $this->getLinkRenderer()->makeUserLink( $user, $this->getContext() );

			// Generate a link to Special:CheckUser with a prefilled 'reason' input field that links back to the
			// case that this user is in.
			$checkUserPrefilledReason = $this->msg( 'checkuser-suggestedinvestigations-user-check-reason-prefill' )
				->params( $detailViewLink )
				->numParams( $this->mCurrentRow->sic_id )
				->params( $user->getName() )
				->inContentLanguage()
				->text();

			// Generate the link class for the "contribs" tool link
			$userContribsLinkClass = 'mw-usertoollinks-contribs';

			if ( $useGlobalContribs ) {
				$editCount = $this->checkUserGlobalContributionsLookup->getGlobalContributionsCount(
					$user->getName(), $this->getAuthority()
				);
			} else {
				$editCount = $this->userEditTracker->getUserEditCount( $user );
			}
			if ( $editCount === 0 ) {
				// Use same CSS classes as Linker::userToolLinkArray to get a red link when no contribs
				$userContribsLinkClass .= ' mw-usertoollinks-contribs-no-edits';
			}

			// Add link to either Special:Contributions or Special:GlobalContributions
			$userToolLinks = [];
			$userToolLinks[] = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( $contributionsSpecialPage, $user->getName() ),
				$this->msg( 'contribslink' )
					->params( $user->getName() )
					->text(),
				[ 'class' => $userContribsLinkClass ]
			);

			// Add link to Special:CheckUserLog if the user has been checked before and the
			// viewing authority has the 'checkuser-log' right
			$hasPastChecks = $this->localDb->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cu_log' )
				->where( [ 'cul_target_id' => $user->getId() ] )
				->caller( __METHOD__ )
				->fetchField();

			if ( $hasPastChecks && $this->getAuthority()->isAllowed( 'checkuser-log' ) ) {
				$userToolLinks[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUserLog', $user->getName() ),
					$this->msg( 'checkuser-suggestedinvestigations-user-past-checks-link-text' )
						->params( $user->getName() )
						->text()
				);
			}

			// Add link to Special:CheckUser if the user has the 'checkuser' right
			if ( $this->getAuthority()->isAllowed( 'checkuser' ) ) {
				$userToolLinks[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUser', $user->getName() ),
					$this->msg( 'checkuser-suggestedinvestigations-user-check-link-text' )
						->params( $user->getName() )
						->text(),
					[],
					[ 'reason' => $checkUserPrefilledReason ]
				);
			}

			$formattedUsers .= Html::rawElement(
				'li', [
					'class' => $i >= $userHideThreshold ?
						'mw-checkuser-suggestedinvestigations-user-defaulthide'
						: '',
				],
				$this->msg( 'checkuser-suggestedinvestigations-user' )
					->rawParams(
						$userLink,
						$this->msg( 'parentheses' )
							->rawParams( $this->getLanguage()->pipeList( $userToolLinks ) )
							->escaped()
					)
					->parse()
			);
		}
		$formattedUsers .= Html::closeElement( 'ul' );
		return $formattedUsers;
	}

	private function formatSignalsCell( array $signals ): string {
		$signalLabels = array_map( $this->getSignalDisplayNameForSignal( ... ), $signals );

		return $this->getLanguage()->commaList( $signalLabels );
	}

	private function getSignalDisplayNameForSignal( string $signal ): string {
		if ( !array_key_exists( $signal, $this->signalsToDisplayNames ) ) {
			// This can happen when a signal was once defined, but is no longer
			// defined. This can also happen for the local dev signals when creating fake cases.
			// In both cases, fallback to trying the i18n message constructed from the signal name.
			return $this->msg( 'checkuser-suggestedinvestigations-signal-' . $signal );
		}
		return $this->signalsToDisplayNames[$signal];
	}

	private function formatTimestampCell( string $timestamp ): string {
		$lang = $this->getLanguage();
		$user = $this->getContext()->getUser();

		// If in the detail view (which is when the case ID filter is set),
		// don't show the link as it will be a link to the current page.
		if ( $this->caseIdFilter ) {
			return htmlspecialchars( $lang->userTimeAndDate( $timestamp, $user ) );
		}

		return $this->getLinkRenderer()->makeKnownLink(
			$this->getDetailViewTitle( $this->mCurrentRow->sic_url_identifier ),
			$lang->userTimeAndDate( $timestamp, $user )
		);
	}

	private function formatStatusCell( CaseStatus $status, int $caseId ): string {
		$statusKey = match ( $status ) {
			CaseStatus::Open => 'checkuser-suggestedinvestigations-status-open',
			CaseStatus::Resolved => 'checkuser-suggestedinvestigations-status-resolved',
			CaseStatus::Invalid => 'checkuser-suggestedinvestigations-status-invalid',
		};
		$statusText = $this->msg( $statusKey )->text();

		$chipType = match ( $status ) {
			CaseStatus::Resolved => 'success',
			CaseStatus::Invalid => 'warning',
			default => 'notice',
		};

		$codex = new Codex();
		$statusChip = $codex->infoChip()
			->setText( $statusText )
			->setStatus( $chipType )
			->setIcon( 'cdx-info-chip__icon' )
			->build()
			->getHtml();

		return Html::rawElement(
			'div',
			[ 'data-case-id' => $caseId, 'class' => 'mw-checkuser-suggestedinvestigations-status' ],
			$statusChip
		);
	}

	private function formatStatusReasonCell( string $reason, CaseStatus $status, int $caseId ): string {
		if ( $status === CaseStatus::Invalid && $reason === '' ) {
			$reason = $this->msg( 'checkuser-suggestedinvestigations-status-reason-default-invalid' )->text();
		}

		return Html::rawElement(
			'span',
			[ 'data-case-id' => $caseId, 'class' => 'mw-checkuser-suggestedinvestigations-status-reason' ],
			$this->commentFormatter->format( $reason )
		);
	}

	private function formatActionsCell( int $caseId, CaseStatus $status, string $reason ): string {
		$actionsHtml = Html::openElement( 'div', [
			'class' => 'mw-checkuser-suggestedinvestigations-actions',
		] );

		/** @var UserIdentity[] $users */
		$users = $this->mCurrentRow->users;

		$investigateEnabled = false;
		$investigateUrl = null;

		// Enable the "Investigate" button only if there are not too many targets
		if ( count( $users ) <= SpecialInvestigate::MAX_TARGETS ) {
			$investigateEnabled = true;

			$prefilledReason = $this->msg( 'checkuser-suggestedinvestigations-user-investigate-reason-prefill' )
				->params( $this->getDetailViewTitle( $this->mCurrentRow->sic_url_identifier )->getFullText() )
				->numParams( $this->mCurrentRow->sic_id )
				->inContentLanguage()
				->text();

			$investigateUrl = SpecialPage::getTitleFor( 'Investigate' )->getFullURL( [
				// Special:Investigate expects a list of usernames separated by newlines
				'targets' => implode( "\n", array_map( static fn ( $u ) => $u->getName(), $users ) ),
				'reason' => $prefilledReason,
			] );
		}

		// Render the "Investigate" button as a link, because it will make it more natural: it supports by default
		// opening in a new tab, the user won't need to wait for the JS to load, and it works even if JS is disabled.
		// HTML structure as defined on
		// https://doc.wikimedia.org/codex/main/components/demos/button.html#link-buttons-and-other-elements
		$investigateButtonClasses = [
			'cdx-button',
			'cdx-button--fake-button',
			$investigateEnabled ? 'cdx-button--fake-button--enabled' : 'cdx-button--fake-button--disabled',
			'cdx-button--weight-quiet',
			'cdx-button--icon-only',
		];

		$investigateButtonTitle = $this->msg( 'checkuser-suggestedinvestigations-action-investigate' )->text();
		if ( !$investigateEnabled ) {
			$investigateButtonTitle = $this->msg( 'checkuser-suggestedinvestigations-action-investigate-disabled' )
				->numParams( SpecialInvestigate::MAX_TARGETS )->text();
		}

		$actionsHtml .= Html::openElement(
			'a', [
				'role' => 'button',
				'class' => $investigateButtonClasses,
				'title' => $investigateButtonTitle,
				'href' => $investigateUrl,
			]
		);
		$actionsHtml .= Html::element( 'span', [
			'class' => 'cdx-button__icon mw-checkuser-suggestedinvestigations-icon--investigate',
		] );
		$actionsHtml .= Html::closeElement( 'a' );

		$codex = new Codex();
		$actionsHtml .= $codex->button()
			->setIconOnly( true )
			->setIconClass( 'mw-checkuser-suggestedinvestigations-icon--edit' )
			->setAttributes( [
				'title' => $this->msg( 'checkuser-suggestedinvestigations-action-change-status' )->text(),
				'data-case-id' => $caseId,
				'data-case-status' => strtolower( $status->name ),
				'data-case-status-reason' => $reason,
				'class' => 'mw-checkuser-suggestedinvestigations-change-status-button',
			] )
			->setWeight( 'quiet' )
			->build()
			->getHtml();

		$actionsHtml .= Html::closeElement( 'div' );
		return $actionsHtml;
	}

	/** @inheritDoc */
	public function reallyDoQuery( $offset, $limit, $order ) {
		$cases = parent::reallyDoQuery( $offset, $limit, $order );

		$caseIds = [];
		foreach ( $cases as $case ) {
			$caseIds[] = $case->sic_id;
		}

		$signals = $this->querySignalsForCases( $caseIds );
		$caseUsers = $this->queryUsersForCases( $caseIds );

		$result = [];

		foreach ( $cases as $caseRow ) {
			$caseRow->signals = $signals[$caseRow->sic_id] ?? [];
			$caseRow->users = $caseUsers[$caseRow->sic_id] ?? [];
			$result[] = $caseRow;
		}

		return new FakeResultWrapper( $result );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$queryInfo = [
			'tables' => [
				'cusi_case',
			],
			'fields' => [
				'sic_id',
				'sic_status',
				'sic_created_timestamp',
				'sic_status_reason',
				'sic_url_identifier',
			],
			'conds' => [],
		];

		if ( $this->caseIdFilter !== null ) {
			$queryInfo['conds']['sic_id'] = $this->caseIdFilter;
		}

		if ( count( $this->statusFilter ) ) {
			$queryInfo['conds']['sic_status'] = array_map(
				static fn ( $status ) => $status->value,
				$this->statusFilter
			);
		}

		if ( $this->userNamesFilter ) {
			$userIdentities = $this->userIdentityLookup->newSelectQueryBuilder()
				->whereUserNames( $this->userNamesFilter )
				->caller( __METHOD__ )
				->fetchUserIdentities();
			$userIds = array_map(
				static fn ( UserIdentity $user ) => $user->getId(),
				iterator_to_array( $userIdentities )
			);

			// If there are user IDs, then join on the query.
			// If none of the usernames equate to user IDs, then make the query return nothing
			// to indicate these usernames are not in any case.
			if ( $userIds ) {
				$queryInfo['tables'][] = 'cusi_user';
				$queryInfo['join_conds']['cusi_user'] = [ 'JOIN', 'sic_id = siu_sic_id' ];
				$queryInfo['conds']['siu_user_id'] = $userIds;
			} else {
				$queryInfo['conds'][] = '1=0';
			}
		}

		return $queryInfo;
	}

	/**
	 * Returns an array that maps each case ID to an array of signals. Only the name of the signals are returned.
	 * @return string[][]
	 */
	private function querySignalsForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$dbr = $this->getDatabase();
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name' ] )
			->distinct()
			->from( 'cusi_signal' )
			->where( [
				'sis_sic_id' => $caseIds,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$signalsForCases = [];
		foreach ( $result as $row ) {
			$caseId = $row->sis_sic_id;
			if ( !isset( $signalsForCases[$caseId] ) ) {
				$signalsForCases[$caseId] = [];
			}

			$signalsForCases[$caseId][] = $row->sis_name;
		}

		return $signalsForCases;
	}

	/**
	 * Returns an array that maps each case ID to an array of user identities
	 * associated with that case, ordered by account ID descending for each case.
	 * @return UserIdentity[][]
	 */
	private function queryUsersForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$dbr = $this->getDatabase();
		$resultCaseUserId = $dbr->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id' ] )
			->from( 'cusi_user' )
			->where( [
				'siu_sic_id' => $caseIds,
			] )
			->orderBy( [ 'siu_sic_id', 'siu_user_id' ], SelectQueryBuilder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchResultSet();

		$userIds = [];
		foreach ( $resultCaseUserId as $row ) {
			$userIds[] = $row->siu_user_id;
		}
		$userIds = array_unique( $userIds );

		$dbrUsers = $this->localDb;
		$userIdToName = [];
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$resultUsers = $dbrUsers->newSelectQueryBuilder()
				->select( [ 'user_id', 'user_name' ] )
				->from( 'user' )
				->where( [
					'user_id' => $userIdChunk,
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $resultUsers as $row ) {
				$userIdToName[$row->user_id] = $row->user_name;
			}
		}

		$usersForCases = [];
		foreach ( $resultCaseUserId as $row ) {
			$caseId = $row->siu_sic_id;
			if ( !isset( $usersForCases[$caseId] ) ) {
				$usersForCases[$caseId] = [];
			}

			$userId = $row->siu_user_id;
			$userName = $userIdToName[$userId];
			$usersForCases[$caseId][] = UserIdentityValue::newRegistered( $userId, $userName );
		}

		return $usersForCases;
	}

	/**
	 * Gets the Title for the detail view page for a case identified by it's URL identifier.
	 */
	private function getDetailViewTitle( int $urlIdentifier ): Title {
		return SpecialPage::getTitleFor( 'SuggestedInvestigations', 'detail/' . dechex( $urlIdentifier ) );
	}

	/**
	 * Renders the button used to open the filters dialog
	 * along with the table caption
	 *
	 * @inheritDoc
	 */
	protected function getHeader(): string {
		if ( !$this->shouldShowVisibleCaption() ) {
			return '';
		}

		$tableCaption = Html::element(
			'div',
			[ 'class' => 'cdx-table__header__caption', 'aria-hidden' => 'true' ],
			$this->mCaption
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'cdx-table__header' ],
			$tableCaption . $this->getNavigationBuilder()->getFilterButton()
		);
	}

	/**
	 * Only show the filter button and table caption when not
	 * in detailed view, as detailed view does not need filters.
	 *
	 * @inheritDoc
	 */
	protected function shouldShowVisibleCaption(): bool {
		return $this->caseIdFilter === null;
	}

	/** @inheritDoc */
	protected function getTableClass(): string {
		$tableClasses = [ 'ext-checkuser-suggestedinvestigations-table' ];
		if ( $this->isNavigationBarShown() ) {
			$tableClasses[] = 'ext-checkuser-suggestedinvestigations-table-with-navigation-bar';
		}
		return parent::getTableClass() . ' ' . implode( ' ', $tableClasses );
	}

	/** @inheritDoc */
	public function getFullOutput(): ParserOutput {
		$pout = parent::getFullOutput();
		$pout->addModules( [ 'ext.checkUser.suggestedInvestigations' ] );
		$pout->setJsConfigVar(
			'wgCheckUserSuggestedInvestigationsActiveFilters',
			$this->appliedFilters
		);
		return $pout;
	}

	/** @inheritDoc */
	protected function isFieldSortable( $field ) {
		// When only one row could appear, there is no need
		// to allow sorting that one row
		if ( $this->caseIdFilter !== null ) {
			return false;
		}

		return $field === 'sic_created_timestamp'
			|| $field === 'sic_status';
	}

	/** @inheritDoc */
	public function getDefaultSort() {
		return 'sic_created_timestamp';
	}

	/** @inheritDoc */
	protected function getDefaultDirections() {
		return self::DIR_DESCENDING;
	}

	/** @inheritDoc */
	public function getIndexField() {
		return [ self::INDEX_FIELDS[$this->mSort] ];
	}

	/** @inheritDoc */
	protected function getFieldNames() {
		return [
			'users' => $this->msg( 'checkuser-suggestedinvestigations-header-users' )->text(),
			'signals' => $this->msg( 'checkuser-suggestedinvestigations-header-signals' )->text(),
			'sic_created_timestamp' => $this->msg( 'checkuser-suggestedinvestigations-header-created' )->text(),
			'sic_status' => $this->msg( 'checkuser-suggestedinvestigations-header-status' )->text(),
			'sic_status_reason' => $this->msg( 'checkuser-suggestedinvestigations-header-notes' )->text(),
			'actions' => $this->msg( 'checkuser-suggestedinvestigations-header-actions' )->text(),
		];
	}

	/** @inheritDoc */
	public function getModuleStyles(): array {
		return array_merge( parent::getModuleStyles(), [
			'ext.checkUser.suggestedInvestigations.styles',
			'mediawiki.interface.helpers.styles',
		] );
	}

	protected function createNavigationBuilder(): CodexPagerNavigationBuilder {
		$builder = new SuggestedInvestigationsPagerNavigationBuilder(
			$this->getContext(), $this->getRequest()->getQueryValues(),
			$this->numberOfFiltersApplied
		);
		$builder->setNavClass( $this->getNavClass() );
		return $builder;
	}

	/**
	 * @return SuggestedInvestigationsPagerNavigationBuilder
	 */
	public function getNavigationBuilder(): SuggestedInvestigationsPagerNavigationBuilder {
		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return parent::getNavigationBuilder();
	}
}
