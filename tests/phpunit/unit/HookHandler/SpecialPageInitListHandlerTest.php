<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler;
use MediaWiki\Config\Config;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler
 */
class SpecialPageInitListHandlerTest extends MediaWikiUnitTestCase {

	public function testOnSpecialPageInitList_withInvestigate() {
		// Case #1: $wgCheckUserEnableSpecialInvestigate is true
		$list = [];
		$mockConfig = $this->createMock( Config::class );
		$mockConfig->method( 'get' )
			->with( 'CheckUserEnableSpecialInvestigate' )
			->willReturn( true );
		( new SpecialPageInitListHandler( $mockConfig ) )->onSpecialPage_initList( $list );

		$this->assertArrayHasKey(
			'Investigate',
			$list,
			'Hooks added Special:Investigate to the array of special pages passed by reference'
		);
		$this->assertArrayHasKey(
			'InvestigateBlock',
			$list,
			'Hooks added Special:Investigate to the array of special pages passed by reference'
		);
	}

	public function testOnSpecialPageInitList_noInvestigate() {
		// Case #2: $wgCheckUserEnableSpecialInvestigate is false
		$list = [];
		$mockConfig = $this->createMock( Config::class );
		$mockConfig->method( 'get' )
			->with( 'CheckUserEnableSpecialInvestigate' )
			->willReturn( false );
		( new SpecialPageInitListHandler( $mockConfig ) )->onSpecialPage_initList( $list );

		$this->assertEquals(
			[],
			$list,
			'If wgCheckUserEnableSpecialInvestigate is false, no extra special pages added'
		);
	}
}
