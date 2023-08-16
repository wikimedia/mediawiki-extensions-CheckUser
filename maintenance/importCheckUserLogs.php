<?php

use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\IPUtils;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * CheckUser old log file importer.
 * If cu_log table has been manually added, can be used to import old data.
 * https://phabricator.wikimedia.org/T29807
 */
class ImportCheckUserLogs extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'dry-run', 'Parse and do local lookups, but don\'t perform inserts' );
		$this->addOption( 'test', 'Test log parser without doing local lookups' );
		$this->addArg( 'file', 'Log file containing import data', true );

		$this->requireExtension( 'CheckUser' );
	}

	public function execute() {
		$log = $this->getArg( 0 );
		$file = fopen( $log, 'r' );
		if ( $file === false ) {
			$this->error( "Could not open file: {$log}" );
			return;
		}

		if ( $this->hasOption( 'test' ) ) {
			$this->testLog( $file );
		} else {
			if ( $this->hasOption( 'dry-run' ) ) {
				$this->output( "Dry run; no actual imports will be made...\n" );
			}
			$this->importLog( $file );
		}

		fclose( $file );
	}

	/**
	 * @param string $line
	 *
	 * @return array|null
	 */
	protected function parseLogLine( $line ) {
		$rxTimestamp = '(?P<timestamp>\d+:\d+, \d+ \w+ \d+)';
		$rxUser = '(?P<user>.*?)';
		$rxTarget = '(?P<target>.*?)';
		$rxWiki = '(?P<wiki>[^)]*?)';
		$rxReason = '(?: \("(?P<reason>.*)"\))?';

		// Strip nulls due to NFS write collisions
		$line = str_replace( "\0", '', $line );

		$regexes = [
			'ipedits-xff' => "!^<li>$rxTimestamp, $rxUser got edits for XFF " .
				"$rxTarget on $rxWiki$rxReason</li>!",
			'ipedits'     => "!^<li>$rxTimestamp, $rxUser got edits for" .
				" $rxTarget on $rxWiki$rxReason</li>!",
			'ipusers-xff' => "!^<li>$rxTimestamp, $rxUser got users for XFF " .
				"$rxTarget on $rxWiki$rxReason</li>!",
			'ipusers'     => "!^<li>$rxTimestamp, $rxUser got users for" .
				" $rxTarget on $rxWiki$rxReason</li>!",
			'userips'     => "!^<li>$rxTimestamp, $rxUser got IPs for" .
				" $rxTarget on $rxWiki$rxReason</li>!",
		];

		foreach ( $regexes as $type => $regex ) {
			$m = [];
			if ( preg_match( $regex, $line, $m ) ) {
				$data = [
					'timestamp' => strtotime( $m['timestamp'] ),
					'user' => $m['user'],
					'reason' => $m['reason'] ?? '',
					'type' => $type,
					'wiki' => $m['wiki'],
					'target' => $m['target'] ];

				return $data;
			}
		}

		return null;
	}

	/**
	 * @param resource $file
	 */
	protected function importLog( $file ) {
		global $wgDBname;

		$matched = 0;
		$unmatched = 0;

		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();
		$commentStore = $services->getCommentStore();
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $services->get( 'CheckUserLogService' );

		while ( !feof( $file ) ) {
			$line = fgets( $file );
			$data = $this->parseLogLine( $line );
			if ( $data ) {
				if ( $data['wiki'] != WikiMap::getCurrentWikiId() && $data['wiki'] != $wgDBname ) {
					$unmatched++;
					continue;
				}

				// Local wiki lookups...
				$user = $userFactory->newFromName( $data['user'] );

				list( $start, $end ) = IPUtils::parseRange( $data['target'] );
				if ( $start === false ) {
					$targetUser = $userFactory->newFromName( $data['target'] );
					$targetID = $targetUser ? $targetUser->getId() : 0;
					$start = $end = $hex = '';
				} else {
					$hex = $start;
					if ( $start == $end ) {
						$start = $end = '';
					}
					$targetID = 0;
				}

				if ( !$this->hasOption( 'dry-run' ) ) {
					$dbw = $this->getDB( DB_PRIMARY );
					$fields = [
						'cul_actor' => $user->getActorId(),
						'cul_timestamp' => $dbw->timestamp( $data['timestamp'] ),
						'cul_type' => $data['type'],
						'cul_target_id' => $targetID,
						'cul_target_text' => $data['target'],
						'cul_target_hex' => $hex,
						'cul_range_start' => $start,
						'cul_range_end' => $end
					];
					$fields += $commentStore->insert( $dbw, 'cul_reason', $data['reason'] );
					$fields += $commentStore->insert(
						$dbw, 'cul_reason_plaintext',
						$checkUserLogService->getPlaintextReason( $data['reason'] )
					);
					$dbw->newInsertQueryBuilder()
						->table( 'cu_log' )
						->row( $fields )
						->caller( __METHOD__ )
						->execute();
				}

				$matched++;
			}
			$unmatched++;
		}

		$this->output(
			"...cu_log table populated: $matched matched rows, $unmatched discarded rows\n"
		);
	}

	/**
	 * @param resource $file
	 */
	protected function testLog( $file ) {
		$matched = 0;
		$unmatched = 0;
		$badtime = 0;

		while ( !feof( $file ) ) {
			$line = fgets( $file );
			$data = $this->parseLogLine( $line );
			if ( $data ) {
				$matched++;
				if ( !$data['timestamp'] ) {
					$this->output( "[bad timestamp] $line" );
					$badtime++;
				}
			} else {
				$this->output( "[bad format] $line" );
				$unmatched++;
			}
		}
		$this->output(
			"\n$matched matched, $badtime matched with bad time, $unmatched unprocessed\n"
		);
	}
}

$maintClass = ImportCheckUserLogs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
