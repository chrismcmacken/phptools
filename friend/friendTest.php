<?php

require_once(__DIR__ . '/Friend.php');
require_once(__DIR__ . '/test_one.php');
require_once(__DIR__ . '/test_two.php');

class FriendTest extends PHPUnit_Framework_TestCase {
	public function dataAccess() {
		return array(
			'public' => array('public'),
			'protected' => array('protected'),
			'private' => array('private'),
		);
	}


	public function dataMagic() {
		return array(
			'call' => array('__call'),
			'get' => array('__get'),
			'isset' => array('__isset'),
			'set' => array('__set'),
			'unset' => array('__unset'),
		);
	}


	public function dataStatic() {
		return array(
			'Test_One::staticOne' => array('testOneStatic', 'Test_One', 'staticOne'),
			'Test_One::staticTwo' => array('testOneStatic', 'Test_One', 'staticTwo'),
			'Test_One::staticThree' => array('testOneStatic', 'Test_One', 'staticThree'),
			'Test_Two::staticOne' => array('testTwoStatic', 'Test_Two', 'staticOne'),
			'Test_Two::staticTwo' => array('testOneStatic', 'Test_Two', 'staticTwo'),
			'Test_Two::staticThree' => array('testTwoStatic', 'Test_Two', 'staticThree'),
		);
	}


	/**
	 * Will we properly try to call the right magic method?
	 *
	 * @dataProvider dataMagic
	 * @param string $method
	 */
	public function testMagic($method) {
		$testOne = new Test_One();
		$friend = new Friend($testOne);
		$badMethod = false;
		$exception = null;

		try {
			switch ($method) {
				case '__call':
					$friend->someMethod();
					break;

				case '__get':
					$friend->someProperty;
					break;

				case '__isset':
					isset($friend->someProperty);
					break;

				case '__set':
					$friend->someProperty = 12;
					break;

				case '__unset':
					unset($friend->someProperty);
					break;

				default:
					$badMethod = true;
			}
		} catch (Exception $ex) {
			$exception = $ex;
		}

		if ($badMethod) {
			$this->fail('Unexpected magic method: ' . $method);
		}

		if (is_null($exception)) {
			$this->fail('Method did not throw an exception');
		}

		$message = $exception->getMessage();

		$this->assertEquals('magic ' . $method, $message, 'Method did not throw expected message: ' . $message);
	}


	/**
	 * Can we get the right method?
	 *
	 * @dataProvider dataAccess
	 * @param $access
	 */
	public function testPrivateMethod($access) {
		$testOne = new Test_One();
		$friend = new Friend($testOne);
		$expected = 'testOne' . ucfirst($access);
		$methodName = $access . 'Method';
		$actual = $friend->$methodName();
		$this->assertEquals($expected, $actual);
	}


	/**
	 * Can we get the right property?
	 *
	 * @dataProvider dataAccess
	 * @param $access
	 */
	public function testPrivateProperty($access) {
		$testOne = new Test_One();
		$friend = new Friend($testOne);
		$expected = 'testOne' . ucfirst($access);
		$propertyName = $access . 'Property';
		$actual = $friend->$propertyName;
		$this->assertEquals($expected, $actual);
	}


	/**
	 * How about static calls, especially with late static binding?
	 *
	 * @dataProvider dataStatic
	 * @param string $expected
	 * @param string $className
	 * @param string $methodName
	 */
	public function testStatic($expected, $className, $methodName) {
		$object = new $className();
		$friend = new Friend($object);
		$actual = $friend->$methodName();
		$this->assertEquals($expected, $actual);
	}
}
