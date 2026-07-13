<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\AbuseFilter\AbuseLogLookup;
use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;

class SuggestedInvestigationsUserLinkRenderer {

	/**
	 * @var bool Whether to use Special:GlobalContributions over Special:Contributions for
	 *   the user contributions link.
	 */
	public readonly bool $useGlobalContribs;

	/** @var array<int,int> The number of SI cases, keyed by the user id */
	private array $caseCountsByUser = [];

	/** @var array<int,bool> A cache for storing whether a user with given id is hidden or not */
	private array $hiddenUsersCache = [];

	/** @var array<int,bool> A cache for storing whether a user with given id has been checked or not */
	private array $pastChecksCache = [];

	/** @var array<int,int> Maps local user ID to the number of AbuseFilter hits for the user */
	private array $abuseFilterHitCountsByUserId = [];

	/**
	 * @var array<int, array{reverted: int, total: int}>
	 * Maps local user ID to the number of reverted and total revisions
	 */
	private array $userRevertedRevisions = [];

	/**
	 * @var array<int, array{reverted: int, total: int}>
	 * Maps local user ID to the number of reverted and total deleted revisions
	 */
	private array $userRevertedDeletedRevisions = [];

	/** @internal For use by ServiceWiring */
	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsUseGlobalContributionsLink',
		'CheckUserSuggestedInvestigationsEnabled',
	];

	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly IConnectionProvider $connectionProvider,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserEditTracker $userEditTracker,
		private readonly ?CentralAuthEditCounter $centralAuthEditCounter,
		private readonly UserFactory $userFactory,
		private readonly ServiceOptions $options,
		private readonly ?AbuseLogLookup $abuseLogLookup,
		private readonly SuggestedInvestigationsUserRevisionLookup $userRevisionLookup,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->useGlobalContribs = $this->centralAuthEditCounter !== null &&
			$this->specialPageFactory->exists( 'GlobalContributions' ) &&
			$options->get( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' );
	}

	/**
	 * Renders a user link, along with tool links suitable for SuggestedInvestigations
	 *
	 * @param UserIdentity $user The user to link to
	 * @param Authority $viewingAuthority The authority of the viewer. Used to decide whether to show a hidden
	 *     user at all and to decide what tool links to show.
	 * @param IContextSource $context The context to use for messages, language and user link rendering.
	 * @param array $options Additional options to configure what and how is being shown. The known options are:
	 *     - 'caseId' (?int) - id of the SI case this row is displayed in; used for generating prefill reason in
	 *         the 'check user' link; if unset, no prefill will be generated.
	 *     - 'caseDetailsLink' (?string) - full text title of the detail page for the relevant case, used in check user
	 *         reason prefill; if unset, no prefill will be generated.
	 */
	public function makeUserLinkLine(
		UserIdentity $user,
		Authority $viewingAuthority,
		IContextSource $context,
		array $options = [],
	): string {
		if ( !$this->isUserVisible( $user, $viewingAuthority ) ) {
			return Html::element(
				'span',
				[ 'class' => 'history-deleted' ],
				$context->msg( 'rev-deleted-user' )->text()
			);
		}

		$userLink = $this->linkRenderer->makeUserLink( $user, $context );

		$userToolLinks = [
			$this->makeContributionsLink( $user, $context, $viewingAuthority ),
			$this->makePastChecksLink( $user, $context, $viewingAuthority ),
			$this->makeCheckUserLink(
				$user,
				$context,
				$viewingAuthority,
				$options['caseId'] ?? null,
				$options['caseDetailsLink'] ?? null,
			),
			$this->makePreviousCasesLink( $user, $context, $viewingAuthority ),
			$this->makeAbuseLogLink( $user, $context, $viewingAuthority ),
		];
		$userToolLinks = array_filter( $userToolLinks );

		$userToolLinksHtml = $context->msg( 'parentheses' )
			->rawParams( $context->getLanguage()->pipeList( $userToolLinks ) )
			->escaped();

		return $context->msg( 'checkuser-suggestedinvestigations-user' )
			->rawParams( $userLink, $userToolLinksHtml )
			->parse();
	}

	private function makeContributionsLink(
		UserIdentity $user,
		MessageLocalizer $localizer,
		Authority $viewingAuthority
	): string {
		// Generate the link class for the "contribs" tool link
		$linkClass = 'mw-usertoollinks-contribs';
		$contributionsSpecialPage = $this->useGlobalContribs ? 'GlobalContributions' : 'Contributions';

		if ( $this->getUserEditCount( $user ) === 0 ) {
			// Use same CSS classes as Linker::userToolLinkArray to get a red link when no edits
			$linkClass .= ' mw-usertoollinks-contribs-no-edits';
		}

		$linkText = $localizer->msg( 'contribslink' )
			->params( $user->getName() )
			->text();

		// If the user has reverted revisions, update the contributions link to reveal that
		if ( !$this->useGlobalContribs ) {
			$revertedRevisionsForUser = $this->getRevertedRevisionsForUser( $user, $viewingAuthority );
			$revertedDeletedRevisionsForUser = $this->getRevertedDeletedRevisionsForUser( $user, $viewingAuthority );

			if (
				$revertedRevisionsForUser['reverted'] ||
				$revertedDeletedRevisionsForUser['reverted']
			) {
				$revertedRevisionCount = $revertedRevisionsForUser['reverted'] +
					$revertedDeletedRevisionsForUser['reverted'];
				$totalRevisionCount = $revertedRevisionsForUser['total'] + $revertedDeletedRevisionsForUser['total'];
				$linkText = $localizer->msg( 'checkuser-suggestedinvestigations-reverted-revisions' )
					->numParams(
						$revertedRevisionCount,
						$totalRevisionCount
					)
					->text();
			}
		}

		return $this->linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( $contributionsSpecialPage, $user->getName() ),
			$linkText,
			[ 'class' => $linkClass ]
		);
	}

	private function makePastChecksLink(
		UserIdentity $user,
		MessageLocalizer $localizer,
		Authority $viewingAuthority
	): ?string {
		// Only show link to past checks if the target user has been checked before and if viewer can see
		// the CheckUser log
		if (
			!$viewingAuthority->isAllowed( 'checkuser-log' )
			|| !$this->hasUserBeenChecked( $user, $viewingAuthority )
		) {
			return null;
		}

		return $this->linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'CheckUserLog', $user->getName() ),
			$localizer->msg( 'checkuser-suggestedinvestigations-user-past-checks-link-text' )
				->params( $user->getName() )
				->text(),
			[ 'class' => 'mw-usertoollinks-past-checks' ]
		);
	}

	private function makeCheckUserLink(
		UserIdentity $user,
		MessageLocalizer $localizer,
		Authority $viewingAuthority,
		?int $caseId = null,
		?string $caseDetailsLink = null
	): ?string {
		if ( !$viewingAuthority->isAllowed( 'checkuser' ) ) {
			return null;
		}

		// Generate a link to Special:CheckUser with a prefilled 'reason' input field that links back to the
		// case that this user is in.
		$prefilledReason = null;
		if ( $caseId !== null && $caseDetailsLink !== null ) {
			$prefilledReason = $localizer->msg( 'checkuser-suggestedinvestigations-user-check-reason-prefill' )
				->params( $caseDetailsLink )
				->numParams( $caseId )
				->params( $user->getName() )
				->inContentLanguage()
				->text();
		}

		return $this->linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'CheckUser', $user->getName() ),
			$localizer->msg( 'checkuser-suggestedinvestigations-user-check-link-text' )
				->params( $user->getName() )
				->text(),
			[ 'class' => 'mw-usertoollinks-checkuser' ],
			[ 'reason' => $prefilledReason ]
		);
	}

	private function makePreviousCasesLink(
		UserIdentity $user,
		MessageLocalizer $localizer,
		Authority $viewingAuthority
	): ?string {
		// Only show the link if there are at least two cases
		// (i.e., there's at least one other case)
		$siCaseCount = $this->getCaseCountForUser( $user, $viewingAuthority );
		if ( $siCaseCount <= 1 ) {
			return null;
		}

		return $this->linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'SuggestedInvestigations' ),
			$localizer->msg( 'checkuser-suggestedinvestigations-user-si-cases-count' )
				->numParams( $siCaseCount )
				->text(),
			[ 'class' => 'mw-usertoollinks-suggestedinvestigations-cases' ],
			[ 'username' => $user->getName(), 'hideCasesWithNoUserEdits' => '0' ]
		);
	}

	private function makeAbuseLogLink(
		UserIdentity $user,
		MessageLocalizer $localizer,
		Authority $viewingAuthority
	): ?string {
		// Link to Special:AbuseLog for the account
		$abuseFilterHits = $this->getFilterHitCountForUser( $user, $viewingAuthority );
		if ( $abuseFilterHits === 0 ) {
			return null;
		}

		return $this->linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'AbuseLog' ),
			$localizer->msg( 'checkuser-suggestedinvestigations-user-af-hits-count' )
				->numParams( $abuseFilterHits )
				->text(),
			[ 'class' => 'mw-usertoollinks-abusefilter-hits' ],
			[ 'wpSearchUser' => $user->getName() ]
		);
	}

	/**
	 * Returns the number of edits that will be used by this link renderer for deciding whether to
	 * display blue or red contributions link. Depending on the renderer configuration, it can be
	 * either global or local edits.
	 */
	public function getUserEditCount( UserIdentity $user ): int {
		if ( $this->useGlobalContribs ) {
			return $this->centralAuthEditCounter->getCount(
				CentralAuthUser::getInstance( $user )
			);
		} else {
			return $this->userEditTracker->getUserEditCount( $user ) ?? 0;
		}
	}

	/**
	 * Checks whether the target user is visible to the passed authority.
	 */
	public function isUserVisible( UserIdentity $user, Authority $viewingAuthority ): bool {
		if ( $viewingAuthority->isAllowed( 'hideuser' ) ) {
			return true;
		}
		if ( !isset( $this->hiddenUsersCache[$user->getId()] ) ) {
			$this->preloadNonEditingData( [ $user ], $viewingAuthority );
		}
		return !$this->hiddenUsersCache[$user->getId()];
	}

	private function getCaseCountForUser( UserIdentity $user, Authority $viewingAuthority ): int {
		if ( !isset( $this->caseCountsByUser[$user->getId()] ) ) {
			$this->preloadNonEditingData( [ $user ], $viewingAuthority );
		}
		return $this->caseCountsByUser[$user->getId()];
	}

	private function hasUserBeenChecked( UserIdentity $user, Authority $viewingAuthority ): bool {
		if ( !isset( $this->pastChecksCache[$user->getId()] ) ) {
			$this->preloadNonEditingData( [ $user ], $viewingAuthority );
		}
		return $this->pastChecksCache[$user->getId()];
	}

	private function getFilterHitCountForUser( UserIdentity $user, Authority $viewingAuthority ): int {
		if ( !isset( $this->abuseFilterHitCountsByUserId[$user->getId()] ) ) {
			$this->preloadNonEditingData( [ $user ], $viewingAuthority );
		}
		return $this->abuseFilterHitCountsByUserId[$user->getId()];
	}

	/** @return array{reverted: int, total: int} */
	private function getRevertedRevisionsForUser( UserIdentity $user, Authority $viewingAuthority ): array {
		if ( !isset( $this->userRevertedRevisions[$user->getId()] ) ) {
			$this->preloadNonEditingData( [ $user ], $viewingAuthority );
		}
		return $this->userRevertedRevisions[$user->getId()];
	}

	/** @return array{reverted: int, total: int} */
	private function getRevertedDeletedRevisionsForUser( UserIdentity $user, Authority $viewingAuthority ): array {
		if ( !isset( $this->userRevertedDeletedRevisions[$user->getId()] ) ) {
			$this->preloadNonEditingData( [ $user ], $viewingAuthority );
		}
		return $this->userRevertedDeletedRevisions[$user->getId()];
	}

	/**
	 * Preloads the edit counts for a list of users that are going to have links rendered afterward.
	 *
	 * Use this method to save DB queries, by performing batch lookups ahead of time, instead of single user at a time.
	 *
	 * This method is separate, so that data preloading in {@see SuggestedInvestigationsCasesPager::queryUsersForCases}
	 * doesn't have to be repeated in this class (if link renderer caller is sure that it was preloaded,
	 * they don't call this method).
	 * @param UserIdentity[] $users The users to preload the data for
	 */
	public function preloadEditCounts( array $users ): void {
		if ( $this->useGlobalContribs ) {
			$this->centralAuthEditCounter->preloadGetCountCache( array_map(
				static fn ( UserIdentity $user ) => CentralAuthUser::getInstance( $user ),
				$users
			) );
		} else {
			$this->userEditTracker->preloadUserEditCountCache( $users );
		}
	}

	/**
	 * Preloads data for a list of users that are going to have links rendered afterward.
	 *
	 * Use this method to save DB queries, by performing batch lookups ahead of time, instead of single user at a time.
	 * @param UserIdentity[] $users The users to preload the data for
	 * @param Authority $authority Authority in context of which the data should be preloaded. It may influence whether
	 *     hidden items are reflected in the count.
	 */
	public function preloadNonEditingData( array $users, Authority $authority ): void {
		$userIds = array_map( static fn ( UserIdentity $user ) => $user->getId(), $users );

		foreach ( $this->queryCaseCountsForUsers( $userIds ) as $userId => $caseCount ) {
			$this->caseCountsByUser[$userId] = $caseCount;
		}
		foreach ( $this->queryUserHiddenStatus( $users ) as $userId => $isHidden ) {
			$this->hiddenUsersCache[$userId] = $isHidden;
		}
		foreach ( $this->queryPastChecks( $userIds ) as $userId => $wasChecked ) {
			$this->pastChecksCache[$userId] = $wasChecked;
		}
		// $hitCounts may have some entries missing (primarily due to missing permissions); fill these with zeros
		$hitCounts = $this->abuseLogLookup?->getHitCountsForUsers( $authority, $userIds ) ?? [];
		foreach ( $userIds as $userId ) {
			$this->abuseFilterHitCountsByUserId[$userId] = $hitCounts[$userId] ?? 0;
		}

		// Account-level contributions data is only supported if global contributions are not being linked to
		if ( !$this->useGlobalContribs ) {
			$revertedRevisions = $this->userRevisionLookup->getAllRevisionCountsByUsers( $userIds );
			foreach ( $userIds as $userId ) {
				$this->userRevertedRevisions[$userId] = $revertedRevisions[$userId];
			}

			$revertedDeletedRevisions = $this->userRevisionLookup->getAllRevisionCountsByUsers( $userIds, true );
			foreach ( $userIds as $userId ) {
				$this->userRevertedDeletedRevisions[$userId] = $revertedDeletedRevisions[$userId];
			}
		}
	}

	/**
	 * @param int[] $userIds
	 * @return array<int,int>
	 */
	private function queryCaseCountsForUsers( array $userIds ): array {
		if ( !$userIds || !$this->options->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			return [];
		}
		$counts = array_fill_keys( $userIds, 0 );
		foreach ( array_chunk( $userIds, 100 ) as $userIdBatch ) {
			$res = $this->connectionProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN )
				->newSelectQueryBuilder()
				->select( [ 'siu_user_id', 'count' => 'COUNT(*)' ] )
				->from( 'cusi_user' )
				->where( [ 'siu_user_id' => $userIdBatch ] )
				->groupBy( 'siu_user_id' )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				$counts[(int)$row->siu_user_id] = (int)$row->count;
			}
		}
		return $counts;
	}

	/**
	 * @param UserIdentity[] $users
	 * @return array<int,bool>
	 */
	private function queryUserHiddenStatus( array $users ): array {
		$result = [];
		foreach ( $users as $userIdentity ) {
			// TODO: We should have a batch way of accessing the hidden status for a user
			$user = $this->userFactory->newFromUserIdentity( $userIdentity );
			$result[$user->getId()] = $user->isHidden();
		}
		return $result;
	}

	/**
	 * @param int[] $userIds
	 * @return array<int,bool>
	 */
	private function queryPastChecks( array $userIds ): array {
		$result = array_fill_keys( $userIds, false );
		foreach ( array_chunk( $userIds, 500 ) as $userIdsBatch ) {
			$checkedUsers = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
					->select( 'cul_target_id' )
					->from( 'cu_log' )
					->where( [ 'cul_target_id' => $userIdsBatch ] )
					->caller( __METHOD__ )
					->fetchFieldValues();

			foreach ( $checkedUsers as $userId ) {
				$result[$userId] = true;
			}
		}
		return $result;
	}
}
