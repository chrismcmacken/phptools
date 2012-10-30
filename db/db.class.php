<?php
/*
 Copyright (c) 2011 individual committers of the code
 
 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
 
 Except as contained in this notice, the name(s) of the above copyright holders 
 shall not be used in advertising or otherwise to promote the sale, use or other
 dealings in this Software without prior written authorization.
 
 The end-user documentation included with the redistribution, if any, must 
 include the following acknowledgment: "This product includes software developed 
 by contributors", in the same place and form as other third-party
 acknowledgments. Alternately, this acknowledgment may appear in the software
 itself, in the same form and location as other such third-party
 acknowledgments.
 */

require_once(__DIR__ . '/db/base.class.php');
require_once(__DIR__ . '/db/fluent.class.php');
require_once(__DIR__ . '/db/fragment.class.php');
require_once(__DIR__ . '/db/result.class.php');

/**
 * Connect to a database and create chunks for use in DB classes
 *
 * Usage (only a very brief example):
 *    $dbObject = DB::connect('mysql://user:pass@host/Database');
 *    $resultObject = $dbObject->select('*', 'TableName');
 *    $rowArray = $resultObject->fetch();
 */
class DB {
	/**
	 * Return the ANY database fragment
	 *
	 * @return DB_Fragment ANY
	 */
	static public function any() {
		$fragment = new DB_Fragment(DB_Fragment::ANY, func_get_args());
		return $fragment;
	}


	/**
	 * Create a new DB class and connect to a database
	 *
	 * @param string $uri DB connection string
	 * @return DB_Base Database object
	 * @throws Exception No schema specified
	 * @throws Exception Invalid schema specified
	 * @throws Exception Handle class was not loaded
	 * @throws Exception Result class was not loaded
	 */
	static public function connect($uri) {
		$components = parse_url($uri);

		// Set up default values
		$defaults = array(
			'scheme' => 'undefined',
			'host' => 'localhost',
			'user' => false,
			'pass' => false,
			'query' => '',
			'fragment' => '',
		);

		foreach ($defaults as $key => $defaultValue) {
			if (! isset($components[$key])) {
				$components[$key] = $defaultValue;
			}
		}

		if ('undefined' === $components['scheme']) {
			throw new Exception('No DB type defined in DSN: ' . $uri);
		}

		if (! preg_match('/^[a-z0-9]+$/', $components['scheme'])) {
			throw new Exception('Invalid DB type: ' . $components['scheme']);
		}

		// Fatally error if the support files do not exist
		$filenameBase = __DIR__ . '/db/' . $components['scheme'] . '/';
		$classNameBase = 'DB_' . ucfirst($components['scheme']) . '_';
		require_once($filenameBase . 'handle.class.php');
		require_once($filenameBase . 'result.class.php');

		if (! class_exists($classNameBase . 'Handle')) {
			throw new Exception('Unloaded DB class: ' . $classNameBase . 'Handle');
		}
		if (! class_exists($classNameBase . 'Result')) {
			throw new Exception('Unloaded DB class: ' . $classNameBase . 'Result');
		}

		$handleClass = $classNameBase . 'Handle';

		return new $handleClass($components);
	}


	/**
	 * Return the "greater than" database fragment
	 *
	 * @param mixed $value
	 * @return DB_Fragment GT
	 */
	static public function gt($value) {
		$fragment = new DB_Fragment(DB_Fragment::GT, $value);
		return $fragment;
	}


	/**
	 * Return the "greater than or equal to" database fragment
	 *
	 * @param mixed $value
	 * @return DB_Fragment GTE
	 */
	static public function gte($value) {
		$fragment = new DB_Fragment(DB_Fragment::GTE, $value);
		return $fragment;
	}


	/**
	 * Return the "like" database fragment
	 *
	 * @param mixed $value
	 * @return DB_Fragment LIKE
	 */
	static public function like($value) {
		$fragment = new DB_Fragment(DB_Fragment::LIKE, $value);
		return $fragment;
	}


	/**
	 * Return the "less than" database fragment
	 *
	 * @param mixed $value
	 * @return DB_Fragment LT
	 */
	static public function lt($value) {
		$fragment = new DB_Fragment(DB_Fragment::LT, $value);
		return $fragment;
	}


	/**
	 * Return the "less than or equal to" database fragment
	 *
	 * @param mixed $value
	 * @return DB_Fragment LTE
	 */
	static public function lte($value) {
		$fragment = new DB_Fragment(DB_Fragment::LTE, $value);
		return $fragment;
	}


	/**
	 * Return the NOT database fragment
	 *
	 * @return DB_Fragment NOT
	 */
	static public function not() {
		$fragment = new DB_Fragment(DB_Fragment::NOT);
		return $fragment;
	}


	/**
	 * Return the NOW database fragment
	 *
	 * @return DB_Fragment NOW
	 */
	static public function now() {
		$fragment = new DB_Fragment(DB_Fragment::NOW);
		return $fragment;
	}
}
