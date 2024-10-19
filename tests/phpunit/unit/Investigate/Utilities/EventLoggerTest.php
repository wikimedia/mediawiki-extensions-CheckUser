<?php

namespace MediaWiki\CheckUser\Tests\Unit\Investigate\Utilities;

use MediaWiki\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Utilities\EventLogger
 */
class EventLoggerTest extends MediaWikiUnitTestCase {
	public function testGetTime() {
		ConvertibleTimestamp::setFakeTime( 1711617525 );
		$eventLogger = new EventLogger( $this->createMock( ExtensionRegistry::class ) );
		$time = $eventLogger->getTime();
		$this->assertSame(
			1711617525000,
			$time,
			'The timestamp returned by ::getTime was not as expected.'
		);
	}
}
