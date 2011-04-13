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

require_once(dirname(__FILE__) . '/fragment.class.php');
require_once(dirname(__FILE__) . '/result.class.php');

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
		$fragment = new DB_Fragment(DB_FRAGMENT_ANY, func_get_args());
		return $fragment;
	}


	/**
	 * Create a new DB class and connect to a database
	 *
	 * @param string $uri DB connection string
	 * @return DB_Base Database object
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

		if (! preg_match('/^[a-z0-9]+$/', $components['scheme'])) {
			throw new Exception('Invalid DB type: ' . $components['scheme']);
		}

		// Fatally error if the support files do not exist
		$filenameBase = __DIR__ . '/' . $components['scheme'];
		$className = 'DB_' . ucfirst($components['scheme']);
		require_once($filenameBase . '.class.php');
		require_once($filenameBase . '-result.class.php');

		if (! class_exists($className)) {
			throw new Exception('Unloaded DB class: ' . $className);
		}

		return new $className($components);
	}


	/**
	 * Return the NOW database fragment
	 *
	 * @return DB_Fragment NOW
	 */
	static public function now() {
		$fragment = new DB_Fragment(DB_FRAGMENT_NOW);
		return $fragment;
	}
}
