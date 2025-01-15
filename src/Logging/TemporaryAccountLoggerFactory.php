<?php

namespace MediaWiki\CheckUser\Logging;

use MediaWiki\User\ActorStore;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

class TemporaryAccountLoggerFactory {

	/**
	 * The default amount of time after which a duplicate log entry can be inserted.
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	private ActorStore $actorStore;
	private LoggerInterface $logger;
	private IDatabase $dbw;

	public function __construct(
		ActorStore $actorStore,
		LoggerInterface $logger,
		IConnectionProvider $dbProvider
	) {
		$this->actorStore = $actorStore;
		$this->logger = $logger;
		$this->dbw = $dbProvider->getPrimaryDatabase();
	}

	/**
	 * @param int $delay
	 * @return TemporaryAccountLogger
	 */
	public function getLogger(
		int $delay = self::DEFAULT_DEBOUNCE_DELAY
	) {
		return new TemporaryAccountLogger(
			$this->actorStore,
			$this->logger,
			$this->dbw,
			$delay
		);
	}
}
