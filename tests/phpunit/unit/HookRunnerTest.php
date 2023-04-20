<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;
use ReflectionClass;

/**
 * @covers \MediaWiki\CheckUser\Hook\HookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	public function provideHookRunners() {
		yield HookRunner::class => [ HookRunner::class ];
	}

	/**
	 * @dataProvider provideHookRunners
	 */
	public function testHookInterfacesNamingConvention( string $hookRunnerClass ) {
		$hookRunnerReflectionClass = new ReflectionClass( $hookRunnerClass );

		// T334689, T334813: Skip known violations until they're fixed.
		$ignoreList = [
			'MediaWiki\CheckUser\Hook\CheckUserInsertChangesRow',
			'MediaWiki\CheckUser\Hook\CheckUserInsertLogEventRow',
			'MediaWiki\CheckUser\Hook\CheckUserInsertPrivateEventRow'
		];

		foreach ( $hookRunnerReflectionClass->getInterfaces() as $interface ) {
			$name = $interface->getName();

			if ( in_array( $name, $ignoreList ) ) {
				continue;
			}

			$this->assertStringEndsWith( 'Hook', $name,
				"Interface name '$name' must have the suffix 'Hook'." );

		}
	}
}
