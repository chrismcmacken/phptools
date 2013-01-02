<?php

require_once(__DIR__ . '/../friend/Friend.php');
require_once(__DIR__ . '/../renamer/Renamer.php');
require_once(__DIR__ . '/../skeleton/Skeleton.php');

abstract class PHPToolsTestBase extends PHPUnit_Framework_TestCase {
	protected $preservedCwd = null;
	static protected $bufferLevel = null;
	protected $renamer = null;  // Renamer from phptools
	static protected $stubWithMockCache = array();
	
	
	/**
	 * Force a database reset so data providers can hit a clean database
	 */
	public function __construct($name = null, array $data = array(), $dataName = '') {
		$this->renamer = new Renamer();
		parent::__construct($name, $data, $dataName);
		$this->resetState();
	}
	
	
	/**
	 * Confirms that all of the key/value pairs in $expected are the same
	 * in $actual.
	 * 
	 * @param array  $expected The expected values.
	 * @param array  $actual   The actual values.
	 * @param string $message  The message to display on failure.
	 */
	protected function assertArrayContains($expected, $actual, $message = '') {
		$actualFiltered = array();

		foreach ($expected as $key => $value) {
			if (array_key_exists($key, $actual)) {
				$actualFiltered[$key] = $actual[$key];
			}
		}

		$this->assertEquals($expected, $actual, $message);
	}
	
	
	/**
	 * Reports an error if $array does not have all of the specified keys.
	 * 
	 * @param array $keys
	 * @param array $array
	 * @param string $message
	 * @param boolean $strict When true, confirm array only has these keys
	 */
	protected function assertArrayHasKeys(array $keys, array $array, $message = '', $strict = false) {
		if (! $strict) {
			foreach ($keys as $key) {
				$this->assertArrayHasKey($key, $array, $message);
			}
		} else {
			$actualKeys = array_keys($array);
			sort($actualKeys);
			sort($expectedKeys);
			$this->assertEquals($expectedKeys, $actualKeys, $message);
		}
	}
	
	
	/**
	 * Asserts the specified data about an exception
	 * 
	 * If an exception was found and you were not expecting one
	 * ( $exceptionType must equal null ) then it
	 * will throw that exception
	 * 
	 * @param string $expectedType
	 * @param Exception $actualException
	 * @param string $expectedMessagePattern
	 * @return bool true if an exception was found, false if not
	 */
	protected function assertException($expectedType, Exception $actualException = null, $expectedMessagePatterns = null) {
		if (null !== $expectedType) {
			if (false === $actualException instanceof Exception) {
				$this->assertInstanceOf($expectedType, $actualException);
			} elseif (false === $actualException instanceof $expectedType) {
				throw $actualException;
			}
			
			if (null !== $expectedMessagePatterns) {
				if (is_array($expectedMessagePatterns)) {
					foreach ($expectedMessagePatterns as $pattern) {
						$this->assertRegExp($pattern, $actualException->getMessage(), 'Exception message did not match expected pattern.  Actual:' . $actualException->getMessage());
					}
				} else {
					$this->assertRegExp($expectedMessagePatterns, $actualException->getMessage(), 'Exception message did not match expected pattern.  Actual:' . $actualException->getMessage());
				}
			}
			
			return true;
		} elseif (null !== $actualException) {
			throw $actualException;
		}
		
		return false;
	}
	
	
	protected function bufferReset() {
		if (is_null(static::$bufferLevel)) {
			static::$bufferLevel = 0;
		}
		
		while (static::$bufferLevel) {
			ob_end_flush();
			static::$bufferLevel --;
		}
	}
	
	
	protected function bufferStart() {
		static::$bufferLevel ++;
		ob_start();
	}
	
	
	protected function bufferStop() {
		static::$bufferLevel --;
		return ob_get_clean();
	}
	
	
	protected function callCounter($mock, $functionNames, $expectation = null) {
		$counter = array();
		$functionNames = (array) $functionNames;
		
		if (is_null($expectation)) {
			$expectation = $this->any();
		}
		
		foreach ($functionNames as $name) {
			$counter[$name] = array();
			$c = &$counter[$name];
			$mock->expects($expectation)
				->method($name)
				->will($this->returnCallback(
					
					function ()
					use (&$c) {
						$c[] = func_get_args();
					}
					
					));
		}
		
		return $counter;
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
	protected function getCoverageFiles($coverage) {
		static $coversCache = array();
		$testFile = $coverage->getClassFilename($this);
		
		if (! isset($coversCache[$testFile])) {
			$files = array();
			$reflect = new ReflectionClass($this);
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
	 * Generates code and creates a mock object that can be used to do
	 * assertions as you would a normal mock object.
	 * 
	 * NOTE: Does not currently handle parameters passed by reference.
	 * 
	 * @param string $name
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	public function getFunctionMock($name) {
		if (! class_exists('FunctionMock', false)) {
			eval('class FunctionMock { function invoke() {} }');
		}
		
		$mockClass = $this->getMock('FunctionMock');
		$this->renameFunction($name,
			
			function ()
			use ($mockClass) {
				return call_user_func_array(array(
						$mockClass,
						'invoke'
					), func_get_args());
			}
			
			);
		return $mockClass;
	}
	
	
	/**
	 * This method is a mirror of PHPUnit's $this->getMock(), except the
	 * $methods array is a list of methods you do NOT want to mock.
	 * It is useful if you want to just test a single method in your
	 * class and it should call methods X, Y, and Z, but not anything else.
	 * 
	 * @param string $className Class to mock
	 * @param array $methodsToKeep Methods you don't want to mock
	 * @param array $arguments Arguments to pass to the constructor
	 * @param string $mockClassName Desired mocked class or empty string
	 * @param boolean $callConstructor If true, call original constructor
	 * @param boolean $callOriginalClone If true, call original clone method
	 * @param boolean $callAutoload If true, use autoload for original class
	 * @return PHPUnit_Framework_MockObject_MockObject  The mock object.
	 * @throws PHPUnit_Framework_Exception
	 * @throws InvalidArgumentException
	 */
	public function getMockExceptMethods($className, $methodsToKeep = array()) {
		// get the arguments to pass to getMock
		$args = func_get_args();
		
		// set the list of methods to be mocked
		$args[1] = $this->getMockExceptMethodsHelper($className, $methodsToKeep);
		
		// return the mock
		return call_user_func_array(array(
				$this,
				'getMock'
			), $args);
	}
	
	
	/**
	 * Helper for the getMockExceptMethods method.
	 * 
	 * @param string $className Name of the class to mock
	 * @param array $methodsToKeep Methods you don't want to mock
	 * @return array Methods that are to be mocked
	 */
	protected function getMockExceptMethodsHelper($className, $methodsToKeep) {
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
	 * Pull a value from an internal PHPUnit private variable
	 *
	 * @param string $name
	 * @return mixed
	 */
	protected function getTestCasePrivate($name) {
		$testCase = (array) $this;

		foreach ($testCase as $k => $v) {
			$name = explode("\0", $k);
			
			if (count($name) == 2) {
				if ($name[1] === '*' || $name === 'PHPUnit_Framework_TestCase') {
					if ($name[2] === $name) {
						return $v;
					}
				}
			}
		}

		return null;
	}
	

	/**
	 * Create a constraint to match any number of values
	 *
	 * $mock = $this->getMock('someClass', array('setName', 'setId'));
	 * $mock->expects($this->once())
	 *     ->method('setName')
	 *     ->with($this->in('Joe', 'Timmy', 'Al'));
	 * $mock->expects($this->once())
	 *     ->method('setId')
	 *     ->with($this->in(array(1, 2, 3)));
	 *
	 * @param mixed Array of values or a single value
	 * @param mixed ... can pass multiple
	 * @return PHPUnit_Framework_Constraint_Or
	 */
	protected function in() {
		$args = func_get_args();
		
		if (count($args) == 1 && is_array($args[0])) {
			$args = $args[0];
		}
		
		$constraints = array();
		
		foreach ($args as $value) {
			$constraints[] = $this->equalTo($value);
		}
		
		$constraint = call_user_func_array(array(
				$this,
				'logicalOr',
			), $constraints);
		return $constraint;
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
	public function loadPage($uri, $fullPath) {
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
	 * The opposite of ->in()
	 *
	 * @return PHPUnit_Framework_Constraint_Not
	 */
	protected function notIn() {
		$args = func_get_args();
		$constraint = call_user_func_array(array(
				$this,
				'in',
			), $args);
		return $this->logicalNot($constraint);
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
	protected function renameFunction($originalFunction, $overrideFunction) {
		return $this->renamer->renameFunction($originalFunction, $overrideFunction);
	}
	
	
	/**
	 * Force a consistent, clean, unmodified state.  This should reset
	 * any modified environment back to the way it was before any test
	 * was executed.
	 */
	public function resetState() {
		// cwd = where phpunit was executed
		if (! is_null($this->preservedCwd)) {
			chdir($this->preservedCwd);
		} else {
			$this->preservedCwd = getcwd();
		}

		// Stop the session
		if (session_id() !== "") {
			session_write_close();
		}
		
		// libxml can keep errors around - remove them if we had any
		libxml_clear_errors();  // Clear XML errors
		
		// Swap replaced things back to the normal ones
		$this->renamer->resetFunction();  // Reset function names
		$this->renamer->resetClass();  // Reset class names
		
		// Set up superglobals, static references, etc
		$this->resetSuperglobals();
		
		// Reset error handlers
		restore_error_handler();  // Some code sets its own handlers
		restore_exception_handler();  // We remove them and add our own
		
		// Clear any output buffers
		$this->bufferReset();
	}
	

	/**
	 * Clear the superglobals to ensure a consistent state
	 */
	protected function resetSuperglobals() {
		$_COOKIE = array();
		$_FILES = array();
		$_GET = array();
		$_POST = array();
		$_REQUEST = array();
		$_SESSION = array();
	}
	
	
	/**
	 * Overload the running of this test in case we only want to run
	 * specific data sets
	 *
	 * Usage from command-line:
	 *
	 *    TEST="someTest" phpunit suiteTest.php
	 *
	 *    TEST="1|2|3" phpunit threeDataProviderTest.php
	 * 
	 * @param PHPUnit_Framework_TestResult $result
	 * @throws InvalidArgumentException
	 */
	public function run(PHPUnit_Framework_TestResult $result = null) {
		set_time_limit(0);
		
		// Limit our data sets
		$env = getenv('TEST');
		
		if (is_string($env) && strlen($env) > 0) {
			$env = explode('|', $env);
			$dataProviderElement = $this->getDataTestCasePrivate('dataName');

			if (empty($dataProviderElement)) {
				$dataProviderElement = 0;
			}
			
			if (! in_array($dataProviderElement, $env)) {
				return $result;
			}
		}
		
		return parent::run($result);
	}
	
	
	/**
	 * Collect code coverage statistics and profile memory.
	 *
	 * If your code is running out of memory, you can use this to see which
	 * tests are consuming lots.  This can often happen with poorly
	 * created @dataProvider methods.  You can extend the profiling to do
	 * any sort of thing you'd like.  See startProfile() and stopProfile().
	 *
	 *    PROFILE=1 phpunit myTest.php
	 *
	 * Code coverage statistics can be generated automatically too.  This
	 * uses some custom code coverage tools, but you can start any coverage
	 * collection code you'd like by modifying startCoverage() and
	 * stopCoverage().
	 *
	 *    COVERAGE=1 phpunit myTest.php
	 */
	public function runTest() {
		$profileMemory = getenv('PROFILE_MEMORY');
		$doCoverage = getenv('COVERAGE');
		$thrownException = null;
		
		if ($profileMemory) {
			$profile = $this->startProfile();
		}
		
		if ($doCoverage) {
			$coverage = $this->startCoverage();
		}
		
		try {
			$result = parent::runTest();
		} catch (Exception $ex) {
			$thrownException = $ex;
		}
		
		if ($doCoverage) {
			$this->stopCoverage($coverage);
		}
		
		if ($profileMemory) {
			$this->stopProfile($profile);
		}
		
		if (! is_null($thrownException)) {
			throw $thrownException;
		}
		
		return $result;
	}
	

	/**
	 * For a mocked method, return the given result if we are given specific
	 * input.  If given something that does not match, the test will fail.
	 *
	 * $inputs = array(
	 *     array('one string passed'),
	 *     array('string and number', 7867),
	 *     array(),
	 * );
	 * $outputs = array(
	 *     'one string returned',
	 *     'string and number were passed and both matched',
	 *     'no parameters were passed',
	 * );
	 * $mock = $this->getMock('someClass', 'mockedMethod');
	 * $expectation = $mock->expects($this->any())->method('mockedMethod');
	 * $this->setMultipleMatching($expectation, $inputs, $outputs);
	 *
	 * @param PHPUnit_Framework_Expectation $expectation
	 * @param array $inputs Arrays of inputs to try to match
	 * @param array $outputs Array of outputs to return
	 */
	public function setMultipleMatching($expectation, $inputs, $outputs) {
		foreach ($inputs as $k => $v) {
			if (! is_array($v)) {
				$inputs[$k] = array(
					$v
				);
			}
		}
		
		$testCase = $this;
		$closure =
		
		function ()
		use ($inputs, $outputs, $testCase) {
			$args = func_get_args();
			
			foreach ($inputs as $index => $inputSet) {
				if ($inputSet == $args) {
					return $outputs[$index];
				}
			}
			
			$testCase->fail('Unexpected data passed to method: ' . var_export($args, true));
		};
		$expectation->will($this->returnCallback($closure));
	}
	
	
	/**
	 * Force the test into a "known good" state before running any test.
	 */
	public function setUp() {
		parent::setUp();  // PHPUnit's setUp() method
		$this->resetState();
	}
	

	/**
	 * Turn on coverage.  When you implement this in your own code,
	 * you will probably copy this method and override the paths to
	 * various things.
	 *
	 * @return PHPCoverage You can return anything you like
	 */
	public function startCoverage() {
		$coverage = PHPCoverage::getInstance();
		$topDir = dirname(__DIR__);  // You may need to change this
		$cacheDir = $topDir . '/coverageCache';  // Also change this
		$coverage->setBaseDir($topDir . '/app');  // And change this too
			
		if (! is_dir($cacheDir)) {
			mkdir($cacheDir, 0777, true);
		}
			
		$coverage->setCoverageFiles($this->getCoverageFiles($coverage));
		$testFilename = $coverage->getClassFilename($this);
		$coverage->start($testFilename, $cacheDir);
		return $coverage;
	}

	
	/**
	 * Enable some sort of profiling.  This default profiling only tracks
	 * memory consumption by a given test.
	 *
	 * @return integer Memory consumption before test
	 */
	public function startProfile() {
		$memoryBefore = memory_get_usage();
		return $memoryBefore;
	}


	/**
	 * Finish up the code coverage
	 *
	 * @param PHPCoverage $coverage This was returned from startCoverage()
	 */
	public function stopCoverage($coverage) {
		$coverage->stop();
	}


	/**
	 * Finalize the profiling and report information
	 *
	 * @param $memoryBefore This was returned from startProfile()
	 */
	public function stopProfile($memoryBefore) {
		$memoryAfter = memory_get_usage();
		$memoryDiff = $memoryAfter - $memoryBefore;
		$className = get_class($this);
		$testName = $this->getTestCasePrivate('name');
		echo "\nMEM " . $className . '::' . $testName;
		$dataProviderElement = $this->getTestCasePrivate('dataName');

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
	public function stubWithMock($className, $callback = null, $methodsToKeep = array(), $callParent = false) {
		$methodsToKeep = (array) $methodsToKeep;

		// Build unique information about this mock for building a key
		$mockInfo = array();
		$mockInfo['class'] = strtolower($className);
		$mockInfo['methodsToKeep'] = array_map('strtolower', $methodsToKeep);
		$mockInfo['callParent'] = $callParent;

		// Generate a key so we don't make the same sort of mock twice, then
		// check that key to see if we need to build the object
		$mockKey = serialize($mockInfo);

		if (!isset(self::$stubWithMockCache[$mockKey])) {
			// First, we will need to generate or reuse a mock object as our base class
			$mockObject = $this->getMockExceptMethods($className, $methodsToKeep, array(), '', false);
			$skeleton = new Skeleton($mockObject);
			$php = 'PHPToolsTestBase::stubWithMockConstructor("' . addslashes($mockKey) . '", $this, func_get_args());';

			if ($callParent) {
				$reflection = $skeleton->reflect()->getConstructor();
				$php .= $skeleton->chainMethod($reflection);
			}

			$skeleton->setConstructor($php);
			$skeletonClass = $skeleton->create();
			self::$stubWithMockCache[$mockKey] = array(
				'class' => $skeletonClass
			);
		}

		// The test needs to be friended to have access to methods
		$mockClassName = self::$stubWithMockCache[$mockKey]['class'];
		require_once(__DIR__ . '/../dump/Dump.php');
		self::$stubWithMockCache[$mockKey]['testFriend'] = new Friend($this);
		self::$stubWithMockCache[$mockKey]['callback'] = $callback;
		$this->renamer->renameClass($className, $mockClassName);

		return $mockClassName;
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
		$testFriend = $mockDef['testFriend'];
		$callback = $mockDef['callback'];

		// Register this mock with the array of mock objects
		// Can't just use "$testFriend->mockObjects[] = $thisRef" due to how
		// magic getters and setters work.
		$mockArray = $testFriend->mockObjects;
		$mockArray[] = $thisRef;
		$testFriend->mockObjects = $mockArray;

		// Call the setup function
		if ($callback) {
			call_user_func($callback, $testFriend, $thisRef, $arguments);
		}
	}
	

	/**
	 * Return to a known good state after tests
	 */
	public function tearDown() {
		$this->bufferReset();
		$this->resetState();
		parent::tearDown();  // PHPUnit
	}
}

