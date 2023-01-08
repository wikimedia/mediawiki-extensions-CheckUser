<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\CheckUserActorMigration;
use SpecialPage;
use Wikimedia\IPUtils;

class CheckUserGetIPsPager extends AbstractCheckUserPager {

	/** @inheritDoc */
	public function formatRow( $row ): string {
		$lang = $this->getLanguage();
		$ip = $row->cuc_ip;
		$templateParams = [];
		$templateParams['ipLink'] = $this->getSelfLink( $ip,
			[
				'user' => $ip,
				'reason' => $this->opts->getValue( 'reason' ),
			]
		);
		if ( IPUtils::isValidIPv6( $ip ) ) {
			$templateParams['ip64Link'] = $this->getSelfLink( '/64',
				[
					'user' => $ip . '/64',
					'reason' => $this->opts->getValue( 'reason' ),
				]
			);
		}
		$templateParams['blockLink'] = $this->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'Block', $ip ),
			$this->msg( 'blocklink' )->text()
		);
		$templateParams['timeRange'] = $this->getTimeRangeString( $row->first, $row->last );
		$templateParams['editCount'] = $lang->formatNum( $row->count );

		// If we get some results, it helps to know if the IP in general
		// has a lot more edits, e.g. "tip of the iceberg"...
		$ipedits = $this->getCountForIPedits( $row->cuc_ip_hex );
		if ( $ipedits > $row->count ) {
			$templateParams['ipEditCount'] =
				$this->msg( 'checkuser-ipeditcount' )->numParams( $ipedits )->escaped();
		}

		// If this IP is blocked, give a link to the block log
		$templateParams['blockInfo'] = $this->getIPBlockInfo( $ip );
		$templateParams['toolLinks'] = $this->msg( 'checkuser-toollinks', urlencode( $ip ) )->parse();
		return $this->templateParser->processTemplate( 'GetIPsLine', $templateParams );
	}

	/**
	 * Get information about any active blocks on a IP.
	 *
	 * @param string $ip the IP to get block info on.
	 * @return string
	 */
	protected function getIPBlockInfo( string $ip ): string {
		$block = DatabaseBlock::newFromTarget( null, $ip );
		if ( $block instanceof DatabaseBlock ) {
			return $this->getBlockFlag( $block );
		}
		return '';
	}

	/**
	 * Return "checkuser-ipeditcount" number
	 *
	 * @param string $ip_hex
	 * @return int
	 */
	protected function getCountForIPedits( string $ip_hex ): int {
		$conds = array_merge( [ 'cuc_ip_hex' => $ip_hex ], $this->rangeConds );

		$query = $this->mDb->newSelectQueryBuilder()
			->table( 'cu_changes' )
			->conds( $conds )
			->caller( __METHOD__ );
		$ipEdits = $query->estimateRowCount();
		// If small enough, get a more accurate count
		if ( $ipEdits <= 1000 ) {
			$ipEdits = $query->fetchRowCount();
		}

		return $ipEdits;
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$this->mExtraSortFields = [ 'last' ];
		$actorQuery = CheckUserActorMigration::newMigration()->getJoin( 'cuc_user' );

		if ( $this->getConfig()->get( 'CheckUserActorMigrationStage' ) & SCHEMA_COMPAT_READ_NEW ) {
			$index = 'cuc_actor_ip_time';
			$cond_field = 'actor_user';
		} else {
			$index = 'cuc_user_ip_time';
			$cond_field = 'cuc_user';
		}

		return [
			'fields' => [
				'cuc_ip',
				'cuc_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cuc_timestamp)',
				'last' => 'MAX(cuc_timestamp)',
			],
			'tables' => [ 'cu_changes' ] + $actorQuery['tables'],
			'conds' => [ $cond_field => $this->target->getId() ],
			'join_conds' => $actorQuery['joins'],
			'options' => [
				'GROUP BY' => [ 'cuc_ip', 'cuc_ip_hex' ],
				'USE INDEX' => [ 'cu_changes' => $index ]
			],
		];
	}

	/** @inheritDoc */
	public function getIndexField() {
		return 'last';
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		return $this->getNavigationBar()
			. '<div id="checkuserresults" class="mw-checkuser-get-ips-results"><ul>';
	}

	/**
	 * Temporary measure until Get IPs query is fixed for pagination.
	 *
	 * @return bool
	 */
	protected function isNavigationBarShown() {
		return false;
	}
}
