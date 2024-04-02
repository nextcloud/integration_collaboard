<?php

namespace OCA\Collaboard\Tests;

use OCA\Collaboard\AppInfo\Application;

class CollaboardAPIServiceTest extends \PHPUnit\Framework\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('integration_collaboard', $app::APP_ID);
	}
}
