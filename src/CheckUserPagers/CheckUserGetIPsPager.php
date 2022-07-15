<?php

namespace MediaWiki\CheckUser\CheckUserPagers;

use Html;
use MediaWiki\Block\DatabaseBlock;
use SpecialPage;
use Wikimedia\IPUtils;

class CheckUserGetIPsPager extends AbstractCheckUserPager {

	/** @inheritDoc */
	public function formatRow( $row ): string {
		$lang = $this->getLanguage();
		$ip = $row->cuc_ip;
		$s = '<li>';
		$s .= $this->getSelfLink( $ip,
			[
				'user' => $ip,
				'reason' => $this->opts->getValue( 'reason' ),
			]
		);
		if ( IPUtils::isValidIPv6( $ip ) ) {
			$s .= ' ' . Html::rawElement(
					'span',
					[ 'class' => 'mw-changeslist-links' ],
					$this->getSelfLink( '/64',
						[
							'user' => $ip . '/64',
							'reason' => $this->opts->getValue( 'reason' ),
						]
					)
				);
		}
		$s .= ' ' . Html::rawElement(
				'span',
				[ 'class' => 'mw-changeslist-links' ],
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Block', $ip ),
					$this->msg( 'blocklink' )->text()
				)
			);
		$s .= ' ' . $this->getTimeRangeString( $row->first, $row->last ) . ' ';
		$s .= ' ' . Html::rawElement(
				'strong',
				[ 'class' => 'mw-checkuser-edits-count' ],
				htmlspecialchars( $lang->formatNum( $row->count ) )
			);

		// If we get some results, it helps to know if the IP in general
		// has a lot more edits, e.g. "tip of the iceberg"...
		$ipedits = $this->getCountForIPedits( $row->cuc_ip_hex );
		if ( $ipedits > $row->count ) {
			$s .= ' ' . Html::rawElement(
				'i',
				[ 'class' => 'mw-changeslist-links' ],
				$this->msg( 'checkuser-ipeditcount' )->numParams( $ipedits )->escaped()
			);
		}

		// If this IP is blocked, give a link to the block log
		$s .= $this->getIPBlockInfo( $ip );
		$s .= '<div style="margin-left:5%">';
		$s .= '<small>' . $this->msg( 'checkuser-toollinks', urlencode( $ip ) )->parse() .
			'</small>';
		$s .= '</div>';
		$s .= "</li>\n";
		return $s;
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
		$ipedits = $query->estimateRowCount();
		// If small enough, get a more accurate count
		if ( $ipedits <= 1000 ) {
			$ipedits = $query->fetchRowCount();
		}

		return $ipedits;
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$this->mExtraSortFields = [ 'last' ];
		return [
			'fields' => [
				'cuc_ip',
				'cuc_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cuc_timestamp)',
				'last' => 'MAX(cuc_timestamp)',
			],
			'tables' => [ 'cu_changes' ],
			'conds' => [ 'cuc_user' => $this->target->getId() ],
			'options' => [ 'GROUP BY' => [ 'cuc_ip', 'cuc_ip_hex' ], 'USE INDEX' => 'cuc_user_ip_time' ],
		];
	}

	/** @inheritDoc */
	public function getIndexField() {
		return 'last';
	}

	/** @inheritDoc */
	protected function getEmptyBody(): string {
		return $this->noMatchesMessage( $this->target->getName() ) . "\n";
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		return parent::getStartBody() . '<ul>';
	}

	/** @inheritDoc */
	protected function getEndBody(): string {
		return '</ul></div>' . parent::getEndBody();
	}
}
