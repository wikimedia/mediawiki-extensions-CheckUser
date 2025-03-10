<?php

namespace MediaWiki\CheckUser\Logging;

use MediaWiki\User\ActorStore;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IConnectionProvider;

class TemporaryAccountLoggerFactory {

	/**
	 * The default amount of time after which a duplicate log entry can be inserted.
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	private ActorStore $actorStore;
	private LoggerInterface $logger;
	private IConnectionProvider $dbProvider;

	public function __construct(
		ActorStore $actorStore,
		LoggerInterface $logger,
		IConnectionProvider $dbProvider
	) {
		$this->actorStore = $actorStore;
		$this->logger = $logger;
		$this->dbProvider = $dbProvider;
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
			$this->dbProvider,
			$delay
		);
	}
}
