<?php

require_once(__DIR__ . '/../friend/Friend.php');
require_once(__DIR__ . '/../renamer/Renamer.php');
require_once(__DIR__ . '/../skeleton/Skeleton.php');

class PHPToolsTestUtil {
	static protected $preservedCwd = null;
	static protected $bufferLevel = null;
	static protected $renamer = null;  // Renamer from phptools
	static protected $stubWithMockCache = array();
	

	/**
	 * Filter an array to only contain the listed keys if they already exist
	 *
	 * @param array $source
	 * @param array $keys
	 * @return array
	 */
	static public function arrayFilterKeys($source, $keys) {
		$result = array();

		foreach ($keys as $key) {
			if (array_key_exists($key, $source)) {
				$result[$key] = $source[$key];
			}
		}

		return $result;
	}


	/**
	 * Determine if the listed keys are in an array
	 *
	 * @param array $source
	 * @param array $keys
	 * @return array Missing keys
	 */
	static public function arrayHasKeys($source, $keys, $strict = false) {
		$missing = array();

		foreach ($keys as $key) {
			if (! array_key_exists($key, $source)) {
				$missing[] = $key;
			}
		}

		return $missing;
	}

	/**
	 * Lazy-load it to prevent errors in those blessed environments that
	 * use or need the test_helpers extension.
	 *
	 * @return Renamer
	 */
	static public function getRenamer() {
		if (! self::$renamer) {
			self::$renamer = new Renamer();
		}
	}

	/**
	 * Exception matches what we expect
	 *
	 * @param Exception|null $exception Exception or null for no exception
	 * @param string|null $type String for instanceOf or null for no exception expected
	 * @param string|array|null $messagePatterns
	 * @return boolean True if it matches
	 */
	static public function exceptionMatches(Exception $exception, $type, $messagePatterns = null) {
		if (is_null($type)) {
			if (is_null($exception)) {
				return true;
			}

			return false;
		}

		if (is_null($exception)) {
			return false;
		}

		if (! $exception instanceof $type) {
			return false;
		}

		if (! is_null($messagePatterns)) {
			$messagePatterns = (array) $messagePatterns;

			foreach ($messagePatterns as $pattern) {
				if (! preg_match($pattern, $exception->getMessage())) {
					return false;
				}
			}
		}

		return true;
	}
	
	
	static public function bufferReset() {
		if (is_null(static::$bufferLevel)) {
			static::$bufferLevel = 0;
		}
		
		while (static::$bufferLevel) {
			ob_end_flush();
			static::$bufferLevel --;
		}
	}
	
	
	static public function bufferStart() {
		static::$bufferLevel ++;
		ob_start();
	}
	
	
	static public function bufferStop() {
		static::$bufferLevel --;
		return ob_get_clean();
	}
	
	
	/**
	 * Using the doc comment for the test class, determines the full
	 * filenames for the classes and the included files that are tested.
	 * 
	 * The doc comment should have things like this:
	 *  @covers ClassName
	 *  @coversFile filename.php
	 * 
	 * For "@covers ClassName", a reflection class is made first, and then
	 * the full filename is returned from that.
	 * 
	 * For "@coversFile filename.php", the include path is scanned and all
	 * matches will be added to the list of files we are covering.
	 * 
	 * @param PHPCoverage $coverage
	 * @return array List of full filenames that are covered by the test
	 */
	static public function getCoverageFiles($object, $coverage) {
		static $coversCache = array();
		$testFile = $coverage->getClassFilename($object);
		
		if (! isset($coversCache[$testFile])) {
			$files = array();
			$reflect = new ReflectionClass($object);
			$comment = $reflect->getDocComment();
			$paths = explode(':', ini_get('include_path'));
			
			// Look for @covers annotation
			if (preg_match_all('~@covers\s+([^\s]*)~m', $comment, $matches)) {
				foreach ($matches[1] as $coveredClass) {
					$coveredClass = trim($coveredClass);
					$files[] = $coverage->getClassFilename($coveredClass);
				}
			}
			
			// Look for @coversFile annotation
			if (preg_match_all('~@coversFile\s+([^\s]*)~m', $comment, $matches)) {
				foreach ($matches[1] as $coveredFile) {
					$coveredFile = trim($coveredFile);
					
					foreach ($paths as $path) {
						$fullPath = realpath($path . '/' . $coveredFile);
						
						if ($fullPath) {
							$files[] = $fullPath;
						}
					}
				}
			}
			
			$coversCache[$testFile] = $files;
		}
		
		return $coversCache[$testFile];
	}

	
	/**
	 * Helper for the getMockExceptMethods method.
	 * 
	 * @param string $className Name of the class to mock
	 * @param array $methodsToKeep Methods you don't want to mock
	 * @return array Methods that are to be mocked
	 */
	static public function getMockExceptMethodsHelper($className, $methodsToKeep) {
		$methodsToKeep = (array) $methodsToKeep;

		// get all the methods for the class to be mocked
		$reflection = new ReflectionClass($className);
		$allMethods = $reflection->getMethods();
		
		// create a lookup table of the methods to keep
		$methodsToKeep = array_flip(array_map('strtolower', $methodsToKeep));
		
		// initialize the list of methods to be mocked
		$methodsToMock = array();
		
		// build the list of methods to be mocked
		foreach ($allMethods as $method) {
			$method = $method->name;
			
			if (! isset($methodsToKeep[strtolower($method)])) {
				$methodsToMock[] = $method;
			}
		}
		
		// return the list of methods to be mocked
		return $methodsToMock;
	}
	
	
	/**
	 * Locates a private variable for a class
	 *
	 * @param object $object
	 * @param string $className
	 * @param string $variableName
	 * @return mixed Value or null
	 */
	static public function getPrivateVariable($object, $className, $variableName) {
		$array = (array) $object;

		foreach ($array as $k => $v) {
			$name = explode("\0", $k);

			if (count($name) == 2) {
				if ($name[1] === '*' || $name[1] === $className) {
					if ($name[2] === $variableName) {
						return $v;
					}
				}
			}
		}

		return null;
	}

	
	/**
	 * Sets up the various static properties
	 */
	static public function initialize() {
	}


	/**
	 * Loads a page after setting up the environment to appear to be a web
	 * page request.  No actual HTTP traffic will be generated.
	 * 
	 * Set $_POST to be posted variables and the request will automatically
	 * be sent as a POST request.
	 * 
	 * @param string $uri URI for a browser ("http://mysite.com/my/page.php")
	 * @param string $fullPath Script to invoke ("/home/user/app/my/page.php")
	 */
	static public function loadPage($uri, $fullPath) {
		$exception = null;
		$pageDir = dirname($fullPath);
		$oldCwd = getcwd();
		chdir($pageDir);
		$oldServer = $_SERVER;
		$_SERVER['SCRIPT_NAME'] = '/' . parse_url($uri, PHP_URL_PATH);
		$_SERVER['HTTP_HOST'] = parse_url($uri, PHP_URL_HOST);
		$_SERVER['REMOTE_ADDR'] = '255.255.255.255';

		if (parse_url($uri, PHP_URL_SCHEME) === 'https') {
			$_SERVER['HTTPS'] = 'on';
		}
		
		if (count($_POST)) {
			$_SERVER['REQUEST_METHOD'] = 'POST';
		} else {
			$_SERVER['REQUEST_METHOD'] = 'GET';
		}
		
		try {
			require($fullPath);
		} catch (Exception $ex) {
			$exception = $ex;
		}
		
		$_SERVER = $oldServer;
		chdir($oldCwd);

		if ($exception) {
			throw $exception;
		}
	}
	
	
	/**
	 * Renames a function.
	 * 
	 * It simply swaps the function names.  This method merely exposes the
	 * renamer to the test class.
	 * 
	 * @param string $originalFunction
	 * @param string $overrideFunction
	 */
	static public function renameFunction($originalFunction, $overrideFunction) {
		return static::getRenamer()>renameFunction($originalFunction, $overrideFunction);
	}

	/**
	 * Resets Renamer if it's been used
	 *
	 * @return void
	 */
	static protected function resetRenamer() {
		if (self::$renamer) {
			self::$renamer->resetFunction();  // Reset function names
			self::$renamer->resetClass();  // Reset class names
		}
	}
	
	/**
	 * Force a consistent, clean, unmodified state.  This should reset
	 * any modified environment back to the way it was before any test
	 * was executed.
	 */
	static public function resetState() {
		// First, clear any buffers so we can display error messages
		static::bufferReset();

		// cwd = where phpunit was executed
		if (! is_null(self::$preservedCwd)) {
			chdir(self::$preservedCwd);
		} else {
			self::$preservedCwd = getcwd();
		}

		// Stop the session
		if (session_id() !== "") {
			session_write_close();
		}
		
		// libxml can keep errors around - remove them if we had any
		libxml_clear_errors();  // Clear XML errors
		
		// Swap replaced things back to the normal ones if needed
		self::resetRenamer();

		// Set up superglobals, static references, etc
		self::resetSuperglobals();
		
		// Reset error handlers
		restore_error_handler();  // Some code sets its own handlers
		restore_exception_handler();  // We remove them and add our own
	}
	

	/**
	 * Clear the superglobals to ensure a consistent state
	 */
	static public function resetSuperglobals() {
		$_COOKIE = array();
		$_FILES = array();
		$_GET = array();
		$_POST = array();
		$_REQUEST = array();
		$_SESSION = array();
	}
	
	
	/**
	 * Turn on coverage.  When you implement this in your own code,
	 * you will probably copy this method and override the paths to
	 * various things.
	 *
	 * @return PHPCoverage You can return anything you like
	 */
	static public function startCoverage($object) {
		$coverage = PHPCoverage::getInstance();
		$topDir = dirname(__DIR__);  // You may need to change this
		$cacheDir = $topDir . '/coverageCache';  // Also change this
		$coverage->setBaseDir($topDir . '/app');  // And change this too
			
		if (! is_dir($cacheDir)) {
			mkdir($cacheDir, 0777, true);
		}
			
		$coverage->setCoverageFiles(static::getCoverageFiles($object, $coverage));
		$testFilename = $coverage->getClassFilename($object);
		$coverage->start($testFilename, $cacheDir);
		return $coverage;
	}

	
	/**
	 * Enable some sort of profiling.  This default profiling only tracks
	 * memory consumption by a given test.
	 *
	 * @return integer Memory consumption before test
	 */
	static public function startProfile() {
		$memoryBefore = memory_get_usage();
		return $memoryBefore;
	}


	/**
	 * Finish up the code coverage
	 *
	 * @param PHPCoverage $coverage This was returned from startCoverage()
	 */
	static public function stopCoverage($coverage) {
		$coverage->stop();
	}


	/**
	 * Finalize the profiling and report information
	 *
	 * @param $memoryBefore This was returned from startProfile()
	 */
	static public function stopProfile($object, $testName, $dataProviderElement, $memoryBefore) {
		$memoryAfter = memory_get_usage();
		$memoryDiff = $memoryAfter - $memoryBefore;
		$className = get_class($object);
		echo "\nMEM " . $className . '::' . $testName;

		if (! empty($dataProviderElement)) {
			echo ' (' . $dataProviderElement . ')';
		}

		echo "\tDiff " . $memoryDiff;
		echo "\tTotal " . $memoryAfter;
		echo "\tPeak " . memory_get_peak_usage(true);
		echo "\n";
	}


	/**
	 * Build a fake class dynamically that extends the desired class,
	 * but will act more like a Friend class on a dynamically created mock.
	 * This lets you use the mock methods in order to validate inputs and
	 * return good, simulated values.
	 *
	 * By using the class renaming functionality of Renamer, the mock is
	 * generated whenever you use the "new" keyword on the class we're
	 * changing.  Please consider restructuring your code to use some sort
	 * of dependency injection since overriding "new" is an icky hack.
	 * 
	 * Arguments for the callback, when used, should be like this:
	 *    function ($testObject, $newMockObject, $constructorArguments)
	 * 
	 * @param string $type Name of class to mock
	 * @param callback $callback Constructor callback for mock set up
	 * @param array $methodsToKeep array to pass to getMockExceptMethods
	 * @param boolean $callParent should the mock call the parent constructor
	 */
	public function stubWithMock($mocker, $className, $methodsToKeep = array(), $callParent = false) {
		$methodsToKeep = (array) $methodsToKeep;

		// Build unique information about this mock for building a key
		$mockInfo = array();
		$mockInfo['class'] = strtolower($className);
		$methodsToKeep = array_map('strtolower', $methodsToKeep);
		sort($methodsToKeep);
		$mockInfo['methodsToKeep'] = $methodsToKeep;
		$mockInfo['callParent'] = $callParent;

		// Generate a key so we don't make the same sort of mock twice, then
		// check that key to see if we need to build the object
		$mockKey = serialize($mockInfo);

		if (!isset(self::$stubWithMockCache[$mockKey])) {
			// First, we will need to generate or reuse a mock object as our base class
			$mockObject = $mocker();
			$skeleton = new Skeleton($mockObject);
			$php = 'PHPToolsTestUtil::stubWithMockConstructor("' . addslashes($mockKey) . '", $this, func_get_args());';

			if ($callParent) {
				$reflection = $skeleton->reflect()->getConstructor();
				$php .= $skeleton->chainMethod($reflection);
			}

			$skeleton->setConstructor($php);
			$skeletonClass = $skeleton->create();
			self::$stubWithMockCache[$mockKey] = (object) array(
				'className' => $skeletonClass,
				'key' => $mockKey
			);
		}

		$mockDef = self::$stubWithMockCache[$mockKey];
		$mockDef->callback = null;
		self::getRenamer()>renameClass($className, $mockDef->className);
		return $mockDef;
	}


	/**
	 * Call some callback in order to set up the mock that was stubbed
	 * into your code.
	 *
	 * @param string $key Key in $stubWithMockCache
	 * @param Object $thisRef
	 * @param array $arguments
	 */
	static public function stubWithMockConstructor($key, $thisRef, $arguments) {
		$mockDef = self::$stubWithMockCache[$key];
		$callback = $mockDef->callback;

		// Call the setup function
		if ($callback) {
			call_user_func($callback, $thisRef, $arguments);
		}
	}
	

	/**
	 * Return to a known good state after tests
	 */
	public function tearDown() {
		static::bufferReset();
		static::resetState();
		parent::tearDown();  // PHPUnit
	}
}

