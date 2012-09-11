<?php

/**
 * Using Sebastian Bergmann's test_helpers extension for PHP, we can
 * rename functions and classes.  This class is a helper to make things
 * easier for you.
 *
 * test_helpers:  https://github.com/sebastianbergmann/php-test-helpers
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
		if (! extension_loaded('test_helpers')) {
			die('test_helpers extension not found');
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
	 * there are three calls to rename_function().
	 *
	 * @param string $originalName
	 * @param string $replacement
	 */
	protected function doFunctionRename($originalName, $replacement) {
		$this->confirmFunction($originalName);
		$this->confirmFunction($replacement);
		$tempName = $originalName . '_TEMP';

		while (function_exists($tempName)) {
			$tempName .= substr(uniqid(), -4);
		}

		rename_function($originalName, $tempName);
		rename_function($replacement, $originalName);
		rename_function($tempName, $replacement);
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
		if (empty($this->renamedClasses[$key])) {
			return $className;
		}
		
		if (! is_array($this->renamedClasses[$key])) {
			return $this->renamedClasses[$key];
		}
	
		$newName = array_shift($this->renamedClasses[$key]);
		$this->renamedClasses[$key][] = $newName;
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
		if (is_null($replacement)) {
			$replacement = $originalName . 'Stub';
		}

		if (count($this->renamedClasses) === 0) {
			// Reset, just in case
			unset_new_overload();
			set_new_overload(array('Renamer', 'getClassName'));
		}

		// Class names are case insensitive
		$key = strtolower($classname);
		$this->renamedClasses[$key] = $replacement;
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

		if (isset($this->renamedFunctions[$originalName])) {
			throw new ErrorException($originalName . ' was already renamed.');
		}

		if (! is_string($replacement)) {
			$this->callables[$originalName] = $replacement;
			$newName = create_function('', "return call_user_func_array(Renamer::\$callables['" . $originalName . "'], func_get_args());");
			$replacement = $newName;
		}

		$replacement = strtolower($replacement);
		$this->doFunctionRename($originalName, $replacement);
		$this->renamedFunctions[$originalName] = $replacement;
	}


	/**
	 * Reset one or all class renames
	 *
	 * @param string|null $className Class's name to reset, null to reset all
	 */
	public function resetClass($className = null) {
		if ($className) {
			unset_new_overload();
			$this->renamedClasses = array();
			return;
		}

		$key = strtolower($className);
		
		if (! empty($this->renamedClasses[$key])) {
			unset($this->renamedClasses[$key]);
		}

		if (! count($this->renamedClasses)) {
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

			if (! empty($this->renamedFunctions[$key])) {
				$this->doFunctionRename($key, $this->renamedFunctions[$key]);
				unset($this->renamedFunctions[$key]);
			}

			if (! empty($this->callables[$functionName])) {
				unset($this->callables[$functionName]);
			}
			
			return;
		}

		foreach ($this->renamedFunctions as $original => $renamed) {
			$this->doFunctionRename($original, $renamed);
		}

		$this->renamedFunctions = array();
		$this->callables = array();
	}
}
