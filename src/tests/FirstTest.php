<?php

error_reporting(E_ALL);

class FirstTest extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {

	}

	function test_true() {
		$this->assertTrue(true);
		$this->assertFalse(false);
	}

	protected function tearDown(): void {

	}

}
