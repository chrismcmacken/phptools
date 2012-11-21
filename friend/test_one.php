<?php

class Test_One {
	private $privateProperty = 'testOnePrivate';
	protected $protectedProperty = 'testOneProtected';
	public $publicProperty = 'testOnePublic';

	public function __call($var, $arguments) {
		throw new ErrorException('magic ' . __FUNCTION__);
	}

	public function __get($var) {
		throw new ErrorException('magic ' . __FUNCTION__);
	}

	public function __isset($var) {
		throw new ErrorException('magic ' . __FUNCTION__);
	}

	public function __set($var, $value) {
		throw new ErrorException('magic ' . __FUNCTION__);
	}

	public function __unset($var) {
		throw new ErrorException('magic ' . __FUNCTION__);
	}

	private function privateMethod() {
		return 'testOnePrivate';
	}

	protected function protectedMethod() {
		return 'testOneProtected';
	}

	public function publicMethod() {
		return 'testOnePublic';
	}

	static public function staticOne() {
		return 'testOneStatic';
	}

	static public function staticTwo() {
		return self::staticOne();
	}

	static public function staticThree() {
		return static::staticOne();
	}
}
