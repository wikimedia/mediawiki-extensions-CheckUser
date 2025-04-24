<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use InvalidArgumentException;
use LogicException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\User\CentralId\CentralIdLookup;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

class CheckUserGlobalContributionsLookup implements CheckUserQueryInterface {

	use ContributionsRangeTrait;

	private IConnectionProvider $dbProvider;
	private ExtensionRegistry $extensionRegistry;
	private CentralIdLookup $centralIdLookup;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private Config $config;
	private RevisionStore $revisionStore;

	public function __construct(
		IConnectionProvider $dbProvider,
		ExtensionRegistry $extensionRegistry,
		CentralIdLookup $centralIdLookup,
		CheckUserLookupUtils $checkUserLookupUtils,
		Config $config,
		RevisionStore $revisionStore
	) {
		$this->dbProvider = $dbProvider;
		$this->extensionRegistry = $extensionRegistry;
		$this->centralIdLookup = $centralIdLookup;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->config = $config;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * Ensure CentralAuth is loaded
	 *
	 * @throws LogicException if CentralAuth is not loaded, as it's a dependency
	 */
	public function checkCentralAuthEnabled() {
		if ( !$this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			throw new LogicException(
				"CentralAuth authentication is needed but not available"
			);
		}
	}

	/**
	 * Get an array of active wikis from either cuci_temp_edit or cuci_user
	 *
	 * @param string $target
	 * @param Authority $authority
	 * @return string[]
	 */
	public function getActiveWikis( string $target, Authority $authority ) {
		$this->checkCentralAuthEnabled();

		$activeWikis = [];
		$cuciDb = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );

		if ( $this->isValidIPOrQueryableRange( $target, $this->config ) ) {
			$targetIPConditions = $this->checkUserLookupUtils->getIPTargetExprForColumn(
				$target,
				'cite_ip_hex'
			);
			if ( $targetIPConditions === null ) {
				// Invalid IPs are treated as usernames so we should only ever reach
				// this condition if the IP range is out of limits
				throw new LogicException(
					"Attempted IP range lookup with a range outside of the limit: $target\n
					Check if your RangeContributionsCIDRLimit and CheckUserCIDRLimit configs are compatible."
				);
			}
			$activeWikis = $cuciDb->newSelectQueryBuilder()
				->select( 'ciwm_wiki' )
				->from( 'cuci_temp_edit' )
				->distinct()
				->where( $targetIPConditions )
				->join( 'cuci_wiki_map', null, 'cite_ciwm_id = ciwm_id' )
				->orderBy( 'cite_timestamp', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchFieldValues();
		} else {
			$centralId = $this->centralIdLookup->centralIdFromName( $target, $authority );
			if ( !$centralId ) {
				throw new InvalidArgumentException( "No central id found for $target" );
			}
			$activeWikis = $cuciDb->newSelectQueryBuilder()
				->select( 'ciwm_wiki' )
				->from( 'cuci_user' )
				->distinct()
				->where( [ 'ciu_central_id' => $centralId ] )
				->join( 'cuci_wiki_map', null, 'ciu_ciwm_id = ciwm_id' )
				->orderBy( 'ciu_timestamp', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchFieldValues();
		}

		return $activeWikis;
	}

	/**
	 * Get the global contributions count of the target by looping through the
	 * active wikis and querying for revisions. This only deals with IPs,
	 * as registered accounts are handled by CentralAuthUser->getGlobalEditCount
	 *
	 * @param string $target
	 * @param string[] $activeWikis
	 * @return int global contribution count
	 */
	public function getAnonymousUserGlobalContributionCount( string $target, array $activeWikis ) {
		$ipConditions = $this->checkUserLookupUtils->getIPTargetExpr(
			$target,
			false,
			self::CHANGES_TABLE
		);
		if ( $ipConditions === null ) {
			// Invalid IPs are treated as usernames so we should only ever reach
			// this condition if the IP range is out of limits
			throw new LogicException(
				"Attempted IP range lookup with a range outside of the limit: $target\n
				Check if your RangeContributionsCIDRLimit and CheckUserCIDRLimit configs are compatible."
			);
		}

		$revisionCount = 0;
		foreach ( $activeWikis as $wikiId ) {
			$dbr = $this->dbProvider->getReplicaDatabase( $wikiId );
			// Get results; no need to check access permissions. See T386186#10595606
			$countResult = $dbr->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( self::CHANGES_TABLE )
				->where( $ipConditions )
				->andWhere( $dbr->expr( 'cuc_this_oldid', '!=', 0 ) )
				->caller( __METHOD__ )
				->fetchField();

			$revisionCount += $countResult;
		}

		return $revisionCount;
	}

	/**
	 * Return a count of contributions across wikis. IPs are limited to 90 days of data
	 * but registered accounts have a total count through CentralAuth.
	 *
	 * @param string $target
	 * @param Authority $authority
	 * @return int global contribution count
	 */
	public function getGlobalContributionsCount( string $target, Authority $authority ) {
		$this->checkCentralAuthEnabled();

		if ( $this->isValidIPOrQueryableRange( $target, $this->config ) ) {
			$activeWikis = $this->getActiveWikis( $target, $authority );
			return $this->getAnonymousUserGlobalContributionCount( $target, $activeWikis );
		} else {
			$centralUser = CentralAuthUser::getInstanceByName( $target );
			return $centralUser->getGlobalEditCount();
		}
	}
}
