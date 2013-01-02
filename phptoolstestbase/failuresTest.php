<?php

// All of these tests *should fail* for a specific reason

require_once(__DIR__ . '/PHPToolsTestBase.php');

class A {
	public function B($c) {
		return 'D';
	}
}

class PHPToolsTestBaseTest extends PHPToolsTestBase {
	// Parameter didn't match the mock
	public function testStubWithMockFailsExpectation() {
		$this->stubWithMock('A', function ($test, $mock, $args) {
			$mock->expects($test->once())->method('B')->with($test->equalTo('FAILURE'))->will($test->returnValue(7));
		}, array(), false);

		$x = new A();
		$this->assertEquals(7, $x->B('abc'));
	}

	// Mocked method was never called
	public function testStubWithMockMethodNotCalled() {
		$this->stubWithMock('A', function ($test, $mock, $args) {
			$mock->expects($test->once())->method('B')->with($test->equalTo('abc'))->will($test->returnValue(7));
		}, array(), false);

		$x = new A();
	}
}
