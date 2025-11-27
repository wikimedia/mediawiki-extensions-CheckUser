<?php

namespace MediaWiki\CheckUser\Tests\Unit\SuggestedInvestigations;

use LogicException;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult
 */
class SuggestedInvestigationsSignalMatchResultTest extends MediaWikiUnitTestCase {
	public function testNewNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->assertFalse( $sut->isMatch() );
		$this->assertSame( 'test-abc', $sut->getName() );
	}

	public function testGetValueThrowsForNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->getValue();
	}

	public function testValueMatchAllowsMergingThrowsForNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->valueMatchAllowsMerging();
	}

	public function testGetEquivalentNamesForMergingThrowsForNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->getEquivalentNamesForMerging();
	}

	public function testGetTriggerIdThrowsForNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->getTriggerId();
	}

	public function testGetTriggerIdTableThrowsForNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->getTriggerIdTable();
	}

	/** @dataProvider provideNewPositiveResult */
	public function testNewPositiveResult(
		bool $valueMatchAllowsMerging, array $equivalentNamesForMerging, ?int $triggerId, ?string $triggerIdTable
	): void {
		// null for $triggerId means to use the default value
		if ( $triggerId === null || $triggerIdTable === null ) {
			$sut = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
				'name-abc', 'value-abc', $valueMatchAllowsMerging,
				equivalentNamesForMerging: $equivalentNamesForMerging
			);
		} else {
			$sut = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
				'name-abc', 'value-abc', $valueMatchAllowsMerging, $triggerId, $triggerIdTable,
				$equivalentNamesForMerging
			);
		}

		$this->assertTrue( $sut->isMatch() );
		$this->assertSame( 'name-abc', $sut->getName() );
		$this->assertSame( 'value-abc', $sut->getValue() );
		$this->assertSame( $valueMatchAllowsMerging, $sut->valueMatchAllowsMerging() );
		$this->assertArrayEquals( $equivalentNamesForMerging, $sut->getEquivalentNamesForMerging(), false, true );
		if ( $triggerId === null || $triggerIdTable === null ) {
			$this->assertSame( 0, $sut->getTriggerId() );
			$this->assertSame( '', $sut->getTriggerIdTable() );
		} else {
			$this->assertSame( $triggerId, $sut->getTriggerId() );
			$this->assertSame( $triggerIdTable, $sut->getTriggerIdTable() );
		}
	}

	public static function provideNewPositiveResult(): array {
		return [
			'Value match allows merging' => [
				'valueMatchAllowsMerging' => true,
				'equivalentNamesForMerging' => [],
				'triggerId' => null,
				'triggerIdTable' => null,
			],
			'Value match allows merging with equivalent signal names specified' => [
				'valueMatchAllowsMerging' => true,
				'equivalentNamesForMerging' => [ 'name-abc-2' ],
				'triggerId' => null,
				'triggerIdTable' => null,
			],
			'Value match does not allow merging' => [
				'valueMatchAllowsMerging' => false,
				'equivalentNamesForMerging' => [],
				'triggerId' => null,
				'triggerIdTable' => null,
			],
			'Trigger ID is set' => [
				'valueMatchAllowsMerging' => false,
				'equivalentNamesForMerging' => [],
				'triggerId' => 1,
				'triggerIdTable' => 'revision',
			],
		];
	}
}
