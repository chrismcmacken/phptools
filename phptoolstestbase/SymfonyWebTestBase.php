<?php

require_once(__DIR__ . '/PHPToolsTestUtil.php');

abstract class SymfonyWebTestBase extends Symfony\Bundle\FrameworkBundle\Test\WebTestCase {
	/**
	 * Force a database reset so data providers can hit a clean database
	 */
	public function __construct($name = null, array $data = array(), $dataName = '') {
		PHPToolsTestUtil::initialize();
		parent::__construct($name, $data, $dataName);
		PHPToolsTestUtil::resetState();
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
		$actualFiltered = PHPToolsTestUtil::arrayFilterKeys($actual, array_keys($expected));
		$this->assertEquals($expected, $actual, $message);
	}
	
	
	/**
	 * Reports an error if $array does not have all of the specified keys.
	 * 
	 * @param array $expectedKeys
	 * @param array $actual
	 * @param string $message
	 * @param boolean $strict When true, confirm array only has these keys
	 */
	protected function assertArrayHasKeys(array $expectedKeys, array $actual, $message = '', $strict = false) {
		$extraKeys = PHPToolsTestUtil::arrayHasKeys($actual, $expectedKeys);
		$this->assertEquals(array(), $extraKeys, $message);
		
		if ($strict) {
			$actualKeys = array_keys($actual);
			sort($expectedKeys);
			sort($actualKeys);
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
	 */
	protected function assertException($expectedType, Exception $actualException = null, $expectedMessagePatterns = null) {
		if (! PHPToolsTestUtil::exceptionMatches($actualException, $expectedType)) {
			if ($actualException) {
				throw $actualException;
			}

			$this->fail('Expected ' . $expectedType . ' and did not get an Exception');
		}

		if (! PHPToolsTestUtil::exceptionMatches($actualException, $expectedType, $expectedMessagePatterns)) {
			$this->fail('Exception message "' . $actualException->getMessage() . '" did not match any supplied pattern');
		}
	}
	
	
	protected function bufferStart() {
		return PHPToolsTestUtil::bufferStart();
	}
	
	
	protected function bufferStop() {
		return PHPToolsTestUtil::bufferStart();
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
		PHPToolsTestUtil::renameFunction($name,
			
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
		$args[1] = PHPToolsTestUtil::getMockExceptMethodsHelper($className, $methodsToKeep);
		
		// return the mock
		return call_user_func_array(array(
				$this,
				'getMock'
			), $args);
	}
	
	
	/**
	 * Pull a value from an internal PHPUnit private variable
	 *
	 * @param string $name
	 * @return mixed
	 */
	protected function getTestCasePrivate($name) {
		return PHPToolsTestUtil::getPrivateVariable($this, 'PHPUnit_Framework_TestCase', $variableName);
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
		return PHPToolsTestUtil::loadPage($uri, $fullPath);
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
		return PHPUnitTestBase::renameFunction($originalFunction, $overrideFunction);
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
			$profile = PHPToolsTestUtil::startProfile();
		}
		
		if ($doCoverage) {
			$coverage = PHPToolsTestUtil::startCoverage($this);
		}
		
		try {
			$result = parent::runTest();
		} catch (Exception $ex) {
			$thrownException = $ex;
		}
		
		if ($doCoverage) {
			PHPToolsTestUtil::stopCoverage($coverage);
		}
		
		if ($profileMemory) {
			PHPToolsTestUtil::stopProfile($this, $this->getTestCasePrivate('name'), $this->getTestCasePrivate('dataName'), $profile);
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
		PHPToolsTestUtil::resetState();
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
		$myself = $this;

		// The test needs to be friended to have access to methods
		$testFriend = new Friend($myself);
		$mocker = function () use ($className, $methodsToKeep, $myself) {
			return $myself->getMockExceptMethods($className, $methodsToKeep, array(), '', false);
		};
		$mockDef = PHPToolsTestUtil::stubWithMock($mocker, $className, $methodsToKeep, $callParent);

		$mockDef->callback = function ($mock, $args) use ($callback, $testFriend) {
			// Register this mock with the array of mock objects.
			// Due to the Friend's magic getter/setter, we can not use
			// $testFriend->mockObjects[] = $mock;
			$mocks = $testFriend->mockObjects;
			$mocks[] = $mock;
			$testFriend->mockObjects = $mocks;

			// Call the setup function
			if ($callback) {
				call_user_func($callback, $testFriend, $mock, $args);
			}
		};

		return $mockDef->className;
	}


	/**
	 * Return to a known good state after tests
	 */
	public function tearDown() {
		PHPToolsTestUtil::resetState();
		parent::tearDown();  // PHPUnit
	}
}

