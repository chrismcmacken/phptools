<?php
/**
 * Copyright (c) 2012 individual committers of the code
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * Except as contained in this notice, the name(s) of the above copyright
 * holders shall not be used in advertising or otherwise to promote the
 * sale, use or other dealings in this Software without prior written
 * authorization.
 * 
 * The end-user documentation included with the redistribution, if any, must 
 * include the following acknowledgment: "This product includes software
 * developed by contributors", in the same place and form as other third-party
 * acknowledgments. Alternately, this acknowledgment may appear in the software
 * itself, in the same form and location as other such third-party
 * acknowledgments.
 */

/**
 * Using Sebastian Bergmann's test_helpers extension for PHP or the
 * runkit extension, we can rename functions and classes.  This class
 * is a helper to make things easier for you.
 *
 * test_helpers:  https://github.com/sebastianbergmann/php-test-helpers
 * runkit:  https://github.com/zenovich/runkit
 */

class Renamer {
	static public $callables = array();
	static protected $renamedClasses = array();
	static protected $renamedFunctions = array();


	/**
	 * Make sure that the test_helpers extension is loaded, otherwise
	 * this stuff just doesn't work.
	 */
	public function __construct() {
		if (! extension_loaded('test_helpers') && ! extension_loaded('runkit')) {
			die('test_helpers extension and runkit extensions are not found - you need one of them');
		}
	}


	/**
	 * Ensure a function exists and is actually a function
	 *
	 * @param string $functionName
	 * @throws ErrorException
	 */
	protected function confirmFunction($functionName) {
		if (! function_exists($functionName)) {
			throw new ErrorException('Function ' . $functionName . ' does not exist');
		}
	}


	/**
	 * Perform actual function renaming.
	 *
	 * It actually swaps the two functions with each other, which is why
	 * there are three calls to rename the function.
	 *
	 * @param string $originalName
	 * @param string $replacement
	 */
	protected function doFunctionRename($originalName, $replacement) {
		if (! extension_loaded('test_helpers') && ! extension_loaded('runkit')) {
			die('test_helpers extension and runkit extensions are not found - you need one of them');
		}

		$this->confirmFunction($originalName);
		$this->confirmFunction($replacement);
		$tempName = $originalName . '_TEMP';

		while (function_exists($tempName)) {
			$tempName .= substr(uniqid(), -4);
		}

		// Runkit's function renaming works better in PHP 5.4
		if (function_exists('runkit_function_rename')) {
			runkit_function_rename($originalName, $tempName);
			runkit_function_rename($replacement, $originalName);
			runkit_function_rename($tempName, $replacement);
		} else {
			rename_function($originalName, $tempName);
			rename_function($replacement, $originalName);
			rename_function($tempName, $replacement);
		}
	}

	
	/**
	 * Return a class name.  If the class was renamed, return the new
	 * name instead.
	 *
	 * Static because it is called by an overloaded 'new' operator.
	 *
	 * @param string $className
	 * @return string Class name to really use
	 */
	static public function getClassName($className) {
		// Class names are case insensitive
		$key = strtolower($className);
		if (empty(self::$renamedClasses[$key])) {
			return $className;
		}
		
		if (! is_array(self::$renamedClasses[$key])) {
			return self::$renamedClasses[$key];
		}
	
		$newName = array_shift(self::$renamedClasses[$key]);
		self::$renamedClasses[$key][] = $newName;
		return $newName;
	}


	/**
	 * Rename a class
	 *
	 * If no replacement is specified, it will rename $className to
	 * $className . 'Stub'.
	 *
	 * When you pass in an array, then the first time the object is created,
	 * it will use the first name.  The second instantiation would use
	 * the second class, and so forth.  When at the end, it will start again
	 * from the beginning.
	 *
	 * @param string $originalName Original class name
	 * @param string|array $replacement Replacement name or names
	 */
	public function renameClass($originalName, $replacement = null) {
		if (! extension_loaded('test_helpers')) {
			die('test_helpers extension is required');
		}

		if (is_null($replacement)) {
			$replacement = $originalName . 'Stub';
		}

		if (count(static::$renamedClasses) === 0) {
			// Reset, just in case
			unset_new_overload();
			set_new_overload(array('Renamer', 'getClassName'));
		}

		// Class names are case insensitive
		$key = strtolower($originalName);
		static::$renamedClasses[$key] = $replacement;
	}


	/**
	 * Rename a function
	 *
	 * Can pass a closure or any valid callback.  If omitted, will swap the
	 * function with one with 'Stub' at the end.
	 *
	 * @param string $originalName Original function name
	 * @param mixed $replacement
	 */
	public function renameFunction($originalName, $replacement = null) {
		$originalName = strtolower($originalName);

		if (is_null($replacement)) {
			$replacement = $originalName . 'Stub';
		}

		if (isset(static::$renamedFunctions[$originalName])) {
			throw new ErrorException($originalName . ' was already renamed.');
		}

		if (! is_string($replacement)) {
			static::$callables[$originalName] = $replacement;
			$newName = create_function('', "return call_user_func_array(Renamer::\$callables['" . $originalName . "'], func_get_args());");
			$replacement = $newName;
		}

		$replacement = strtolower($replacement);
		$this->doFunctionRename($originalName, $replacement);
		static::$renamedFunctions[$originalName] = $replacement;
	}


	/**
	 * Reset one or all class renames
	 *
	 * @param string|null $className Class's name to reset, null to reset all
	 */
	public function resetClass($className = null) {
		if (! extension_loaded('test_helpers')) {
			die('test_helpers extension is required');
		}

		if ($className) {
			unset_new_overload();
			static::$renamedClasses = array();
			return;
		}

		$key = strtolower($className);
		
		if (! empty(static::$renamedClasses[$key])) {
			unset(static::$renamedClasses[$key]);
		}

		if (! count(static::$renamedClasses)) {
			unset_new_overload();
		}
	}


	/**
	 * Reset one or all function renames
	 *
	 * @param string|null $functionName Function to reset, null to reset all
	 */
	public function resetFunction($functionName = null) {
		if ($functionName) {
			$key = strtolower($functionName);

			if (! empty(static::$renamedFunctions[$key])) {
				$this->doFunctionRename($key, static::$renamedFunctions[$key]);
				unset(static::$renamedFunctions[$key]);
			}

			if (! empty(static::$callables[$functionName])) {
				unset(static::$callables[$functionName]);
			}
			
			return;
		}

		foreach (static::$renamedFunctions as $original => $renamed) {
			$this->doFunctionRename($original, $renamed);
		}

		static::$renamedFunctions = array();
		static::$callables = array();
	}
}
