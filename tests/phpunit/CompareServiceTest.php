<?php

use MediaWiki\CheckUser\CompareService;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * @group CheckUser
 * @group Database
 * @coversDefaultClass \MediaWiki\CheckUser\CompareService
 */
class CompareServiceTest extends MediaWikiTestCase {

	/** @var CompareService */
	private $service;

	/**
	 * Lazy load CompareService
	 *
	 * @return CompareService
	 */
	private function getCompareService(): CompareService {
		if ( !$this->service ) {
			$this->service = MediaWikiServices::getInstance()->get( 'CheckUserCompareService' );
		}

		return $this->service;
	}

	/**
	 * @covers ::getTotalEditsFromIp
	 * @dataProvider provideCompareData
	 */
	public function testGetCompareData( $users, $expected ) {
		$result = $this->getCompareData( $users );
		$this->assertEquals( $result->numRows(), $expected );
	}

	public function provideCompareData() {
		return [
			[ [ 'User1' ], 2 ],
			[ [ 'User1', 'InvalidUser', '1.2.3.9/120' ], 2 ],
			[ [ 'User1', '' ], 2 ],
			[ [ 'User2' ], 1 ],
			[ [ '1.2.3.4' ], 4 ],
			[ [ '1.2.3.0/24' ], 7 ],
			[ [ 'User1','User2' ], 3 ],
			[ [ 'User1','User2', '1.2.3.4' ], 5 ],
		];
	}

	private function getCompareData( array $users ): IResultWrapper {
		[
			'tables' => $tables,
			'fields' => $fields,
			'conds' => $conds,
			'options' => $options
		] = $this->getCompareService()->getQueryInfo( $users );

		return $this->db->select( $tables, $fields, $conds, __METHOD__, $options );
	}

	/**
	 * @covers ::getTotalEditsFromIp
	 * @dataProvider provideTotalEditsFromIp()
	 */
	public function testGetTotalEditsFromIp( $data, $expected ) {
		$result = $this->getCompareService()->getTotalEditsFromIp(
			$data['ip'], $data['userAgent'], $data['excludeUser'] ?? null
		);

		$this->assertEquals( $expected, $result );
	}

	public function provideTotalEditsFromIp() {
		return [
			[
				[
					'ip' => '1.2.3.5',
					'userAgent' => 'bar user agent',
				], [
					'total_edits' => 3,
					'total_users' => 2,
				],
			],
			[
				[
					'ip' => '1.2.3.4',
					'userAgent' => 'foo user agent',
					'excludeUser' => 'User1'
				], [
					'total_edits' => 5,
					'total_users' => 3,
				],
			],
			[
				[
					'ip' => '1.2.3.5',
					'userAgent' => 'foo user agent',
					'excludeUser' => 'User1'
				], [
					'total_edits' => 3,
					'total_users' => 2,
 ],
			],
		];
	}

	public function addDBDataOnce() {
		$testData = [
			[
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.4',
				'cuc_type'       => RC_NEW,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.4',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.4',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'bar user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.5',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'bar user agent',
			], [
				'cuc_user'       => 0,
				'cuc_user_text'  => '1.2.3.5',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 1,
				'cuc_user_text'  => 'User1',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 2,
				'cuc_user_text'  => 'User2',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_user'       => 1,
				'cuc_user_text'  => 'User1',
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IP::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'foo user agent',
			],
		];

		$commonData = [
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_timestamp'  => '',
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_actiontext' => '',
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
		];

		foreach ( $testData as $row ) {
			$this->db->insert( 'cu_changes', $row + $commonData );
		}
	}
}
