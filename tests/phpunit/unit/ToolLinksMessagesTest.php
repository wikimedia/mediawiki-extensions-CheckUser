<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use HashConfig;
use MediaWiki\CheckUser\ToolLinksMessages;
use MediaWiki\ResourceLoader\Context;
use MediaWikiUnitTestCase;
use Message;

/**
 * @author DannyS712
 * @group CheckUser
 * @coversDefaultClass \MediaWiki\CheckUser\ToolLinksMessages
 */
class ToolLinksMessagesTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getParsedMessage
	 */
	public function testGetParsedMessage() {
		$msg = $this->createMock( Message::class );
		$msg->method( 'parse' )->willReturn( 'Parsed result' );

		$context = $this->createMock( Context::class );
		$context->method( 'msg' )
			->with( 'message key' )
			->willReturn( $msg );

		$res = ToolLinksMessages::getParsedMessage(
			$context,
			new HashConfig( [] ),
			'message key'
		);
		$this->assertEquals(
			[ 'message key' => 'Parsed result' ],
			$res
		);
	}

}
