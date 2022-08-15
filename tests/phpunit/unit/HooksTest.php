<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use MediaWiki\CheckUser\Hooks;
use MediaWikiUnitTestCase;

/**
 * @author DannyS712
 * @group CheckUser
 * @coversDefaultClass \MediaWiki\CheckUser\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::onSpecialPage_initList
	 */
	public function testOnSpecialPageInitList_withInvestigate() {
		// Need to manipulate the globals, can't use setMwGlobals since its a unit test,
		// but other than that no integration is needed so its not worth putting this in an
		// integration test
		global $wgCheckUserEnableSpecialInvestigate;
		$oldEnableInvestigate = $wgCheckUserEnableSpecialInvestigate;

		// Case #1: $wgCheckUserEnableSpecialInvestigate is true
		$list = [];
		$wgCheckUserEnableSpecialInvestigate = true;
		( new Hooks() )->onSpecialPage_initList( $list );

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

		// Restore old value
		$wgCheckUserEnableSpecialInvestigate = $oldEnableInvestigate;
	}

	/**
	 * @covers ::onSpecialPage_initList
	 */
	public function testOnSpecialPageInitList_noInvestigate() {
		// See explanation above for using globals
		global $wgCheckUserEnableSpecialInvestigate;
		$oldEnableInvestigate = $wgCheckUserEnableSpecialInvestigate;

		// Case #2: $wgCheckUserEnableSpecialInvestigate is false
		$list = [];
		$wgCheckUserEnableSpecialInvestigate = false;
		( new Hooks() )->onSpecialPage_initList( $list );

		$this->assertEquals(
			[],
			$list,
			'If wgCheckUserEnableSpecialInvestigate is false, no extra special pages added'
		);

		// Restore old value
		$wgCheckUserEnableSpecialInvestigate = $oldEnableInvestigate;
	}
}
