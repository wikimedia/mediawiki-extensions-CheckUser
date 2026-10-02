<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;

/**
 * Inserts rows into the CheckUser result tables for the tests of the shared accounts lookups.
 *
 * The test class must be a database integration test class.
 */
trait SharedAccountsLookupTestTrait {

	private function acquireActorId( UserIdentity $user ): int {
		return $this->getServiceContainer()->getActorStore()
			->acquireActorId( $user, $this->getDb() );
	}

	/**
	 * @return int The cuc_id of the new row
	 */
	private function insertCuChangesRow( ?int $actorId, ?string $ip, int $timestamp = 0 ): int {
		return $this->insertCheckUserRow( 'cu_changes', [
			'cuc_comment_id' => 0,
			'cuc_timestamp' => $this->getDb()->timestamp( $timestamp ),
			'cuc_actor' => $actorId,
			'cuc_ip_hex' => $ip ? IPUtils::toHex( $ip ) : null,
		] );
	}

	/**
	 * @return int The cule_id of the new row
	 */
	private function insertCuLogEventRow( ?int $actorId, ?string $ip, int $timestamp = 0 ): int {
		return $this->insertCheckUserRow( 'cu_log_event', [
			'cule_timestamp' => $this->getDb()->timestamp( $timestamp ),
			'cule_actor' => $actorId,
			'cule_ip_hex' => $ip ? IPUtils::toHex( $ip ) : null,
		] );
	}

	/**
	 * @return int The cupe_id of the new row
	 */
	private function insertCuPrivateEventRow( ?int $actorId, ?string $ip, int $timestamp = 0 ): int {
		return $this->insertCheckUserRow( 'cu_private_event', [
			'cupe_params' => '',
			'cupe_timestamp' => $this->getDb()->timestamp( $timestamp ),
			'cupe_actor' => $actorId,
			'cupe_ip_hex' => $ip ? IPUtils::toHex( $ip ) : null,
		] );
	}

	private function insertCheckUserRow( string $table, array $row ): int {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( $table )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
		return $this->getDb()->insertId();
	}
}
