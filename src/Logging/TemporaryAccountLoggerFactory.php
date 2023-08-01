<?php

namespace MediaWiki\CheckUser\Logging;

use MediaWiki\User\ActorStore;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class TemporaryAccountLoggerFactory {

	/**
	 * The default amount of time after which a duplicate log entry can be inserted.
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	/** @var ActorStore */
	private $actorStore;

	/** @var LoggerInterface */
	private $logger;

	/** @var IDatabase */
	private $dbw;

	/**
	 * @param ActorStore $actorStore
	 * @param LoggerInterface $logger
	 * @param ILoadBalancer $lb
	 */
	public function __construct(
		ActorStore $actorStore,
		LoggerInterface $logger,
		ILoadBalancer $lb
	) {
		$this->actorStore = $actorStore;
		$this->logger = $logger;
		$this->dbw = $lb->getConnection( ILoadBalancer::DB_PRIMARY );
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
