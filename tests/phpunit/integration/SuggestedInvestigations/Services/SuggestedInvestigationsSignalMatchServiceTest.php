<?php

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService
 */
class SuggestedInvestigationsSignalMatchServiceTest extends MediaWikiIntegrationTestCase {

	public function testMatchSignalsAgainstUserWhenFeatureDisabled() {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsEnabled', false );

		$this->expectNotToPerformAssertions();
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			function () {
				$this->fail( 'Did not expect call to CheckUserSuggestedInvestigationsSignalMatch hook' );
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser(
			$this->createMock( UserIdentity::class ), 'test-event'
		);
	}

	public function testMatchSignalsAgainstUserWhenFeatureEnabled() {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsEnabled', true );

		$mockUserIdentity = $this->createMock( UserIdentity::class );
		$eventType = 'test-event';

		$hookCalled = false;
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity, string $eventType, array &$hookProvidedSignalMatchResults
			) use ( &$hookCalled ) {
				$hookProvidedSignalMatchResults[] = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
					'test-signal', 'test-value', false
				);
				$hookCalled = true;
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $mockUserIdentity, $eventType );
		$this->assertTrue( $hookCalled );
	}

	private function getObjectUnderTest(): SuggestedInvestigationsSignalMatchService {
		return $this->getServiceContainer()->get( 'SuggestedInvestigationsSignalMatchService' );
	}
}
