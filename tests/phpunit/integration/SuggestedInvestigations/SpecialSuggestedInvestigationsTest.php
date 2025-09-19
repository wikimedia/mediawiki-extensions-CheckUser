<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations;

use MediaWiki\CheckUser\SuggestedInvestigations\SpecialSuggestedInvestigations;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Request\FauxRequest;
use PHPUnit\Framework\ExpectationFailedException;
use SpecialPageTestBase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\SpecialSuggestedInvestigations
 * @group Database
 */
class SpecialSuggestedInvestigationsTest extends SpecialPageTestBase {
	use SuggestedInvestigationsTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
		$this->unhideSuggestedInvestigations();
	}

	protected function newSpecialPage(): SpecialSuggestedInvestigations {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'SuggestedInvestigations' );
		$this->assertInstanceOf( SpecialSuggestedInvestigations::class, $page );
		return $page;
	}

	public function testLoadSpecialPageWhenMissingRequiredRight() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage();
	}

	public function testLoadSpecialPageWithRequiredRight() {
		$checkuser = $this->getTestUser( [ 'checkuser' ] )->getUser();

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $checkuser, true );
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-summary',
			$html
		);
	}

	/** @dataProvider provideUnavailableSpecialPage */
	public function testUnavailableSpecialPage( bool $enabled, bool $hidden ) {
		if ( $enabled ) {
			$this->enableSuggestedInvestigations();
		} else {
			$this->disableSuggestedInvestigations();
		}
		if ( $hidden ) {
			$this->hideSuggestedInvestigations();
		} else {
			$this->unhideSuggestedInvestigations();
		}

		// This exception is thrown in `newSpecialPage` when the assertion fails
		$this->expectException( ExpectationFailedException::class );
		$this->executeSpecialPage();
	}

	public static function provideUnavailableSpecialPage() {
		return [
			'Feature disabled, not hidden' => [
				'enabled' => false,
				'hidden' => false,
			],
			'Feature disabled, hidden' => [
				'enabled' => false,
				'hidden' => true,
			],
			'Feature enabled, hidden' => [
				'enabled' => true,
				'hidden' => true,
			],
		];
	}
}
