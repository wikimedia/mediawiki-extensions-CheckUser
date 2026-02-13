<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Tests\Unit\SuggestedInvestigations\BlockChecks;

use MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\CentralAuthLockCheck;
use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilderFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\CentralAuthLockCheck
 * @group CheckUser
 */
class CentralAuthLockCheckTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( GlobalUserSelectQueryBuilderFactory::class ) ) {
			$this->markTestSkipped( 'CentralAuth not available' );
		}
	}

	public function testEmptyInput(): void {
		$factory = $this->createMock( GlobalUserSelectQueryBuilderFactory::class );
		$factory->expects( $this->never() )
			->method( 'newGlobalUserSelectQueryBuilder' );

		$lookup = $this->createMock( UserIdentityLookup::class );
		$lookup->expects( $this->never() )
			->method( 'newSelectQueryBuilder' );

		$check = new CentralAuthLockCheck( $factory, $lookup );

		$this->assertSame( [], $check->getIndefinitelyBlockedUserIds( [] ) );
	}
}
