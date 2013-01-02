<?php

/**
 * Create a "bare bones" class, similar to this:
 *
 *   class SomethingGoesHere extends SomeRealClass {
 *       public __construct($x, $y, $z) { }
 *   }
 *
 * Has additional functionality for extending for use as a mock, friend, etc.
 * Works around a PHP bug with late static binding when calling a reflection
 * method (https://bugs.php.net/bug.php?id=53742).
 *
 * Summary:
 *
 * $skeleton = new Skeleton('MyOtherClass');
 * $skeleton->setName('SkeletonOfMyOtherClass');  // Suggest the class's name
 * echo $skeleton->getName(); // Get the class's name (unique)
 * var_export($skeleton->getMethods());  // Array of method names
 * var_export($skeleton->getMethodsExcept('__construct'));  // Like above
 * $skeleton->setConstructor('return true;');  // PHP code in constructor
 * $skeleton->setConstructor(function ($skeleton, $method) {
 *    return $skeleton->chainMethod($method);  // Chain to parent
 * });
 * var_export($skeleton->getConstructor());
 * echo $skeleton->generate();  // Creates PHP code
 * $reflection = $skeleton->reflect();  // Gets original class's reflection
 * $skeleton->create();  // Create the class
 */

class Skeleton {
	public $className = null;  // Name of skeleton to create
	public $constructorGuts = null;  // Constructor of skeleton
	public $originalClass = null;  // Name of original class
	static protected $reflections = array();  // Only reflect a class once

	/**
	 * Create a new skeleton from another class.  If no $newName is passed,
	 * creates a random and unique name based on the original class's name.
	 *
	 * @param Object|string $original Original object or class name
	 */
	public function __construct($original) {
		if (is_object($original)) {
			$this->originalClass = get_class($original);
		} else {
			$this->originalClass = $original;
		}

		$this->setName(null);
	}


	/**
	 * Write out PHP code that would call the parent method properly
	 *
	 * @param ReflectionMethod $method
	 * @return string PHP code
	 */
	public function chainMethod($method) {
		if (! $method) {
			return '';
		}

		$safeName = addslashes($method->getName());
		$safeOrig = addslashes($this->originalClass);

		// Call the parent method with all of the arguments.
		// This is a little tricky, especially with regard to static methods.
		$callable = 'array($this, "parent::' . $safeName . '")';

		if ($method->isStatic()) {
			$callable = 'array("' . $safeOrig . '", "' . $safeName . '")';
		} elseif (version_compare(PHP_VERSION, "5.3.0", "<")) {
			$callable = 'array("parent", "' . $safeName . '")';
		}

		$out = "\t\$args = func_get_args();\n";
		$out .= "\treturn call_user_func_array(" . $callable . ", \$args);\n";
		return $out;
	}


	/**
	 * Write out the constructor's code
	 *
	 * @param ReflectionMethod|null $method
	 * @return string PHP code
	 */
	public function constructorMethod($method) {
		if (is_null($this->constructorGuts)) {
			return '';
		}

		if (is_callable($this->constructorGuts)) {
			return call_user_func($this->constructorGuts, $this, $method);
		}

		return (string) $this->constructorGuts;
	}


	/**
	 * Creates the class
	 */
	public function create() {
		$php = $this->generate();
		eval($php);
		return $this->className;
	}


	/**
	 * Generate PHP code
	 *
	 * Make sure that static methods always get set so Friend can use
	 * Skeleton to ensure static calls work to avoid a PHP bug.
	 */
	public function generate() {
		$out = 'class ' . $this->className . ' extends ' . $this->originalClass . " {\n";
		$reflect = $this->reflect();

		$constructor = $reflect->getConstructor();

		// Build the constructor specially
		if ($constructor) {
			if (! $constructor->isFinal()) {
				$out .= $this->methodDeclaration($constructor);
				$out .= " {\n";
				$out .= $this->constructorMethod($constructor);
				$out .= "}\n";
			}
		} else {
			$out .= "public function __construct() {\n";
			$out .= $this->constructorMethod(null);
			$out .= "}\n";
		}

		// Always build static methods
		foreach ($reflect->getMethods(ReflectionMethod::IS_STATIC) as $method) {
			// Skip final, skip the constructor
			if (! $method->isFinal() && $method !== $constructor) {
				$out .= $this->methodDeclaration($method);
				$out .= " {\n";
				$out .= $this->chainMethod($method);
				$out .= "}\n";
			}
		}

		$out .= "}\n";
		return $out;
	}


	/**
	 * Returns all method names, including non-public ones.
	 *
	 * @return array Method names
	 */
	public function getMethods() {
		$out = array();
		$reflect = $this->reflect();

		foreach ($reflect->getMethods() as $method) {
			$out[] = $method->name;
		}

		return $out;
	}


	/**
	 * Returns all method names (including non-public methods) except
	 * for the names you specified.
	 *
	 * @param string|array Methods to exclude
	 * @return array Method names
	 */
	public function getMethodsExcept($exclusions) {
		$exclusions = (array) $exclusions;
		$lowerExclusions = array();
		$out = array();
		$reflect = $this->reflect();

		foreach ($exclusions as $exclude) {
			$lowerExclusions[strtolower($exclude)] = true;
		}

		foreach ($reflect->getMethods() as $method) {
			$name = strtolower($method->name);

			if (empty($lowerExclusions[$name])) {
				$out[] = $method->name;
			}
		}

		return $out;
	}


	/**
	 * Returns what we will use to build the constructor
	 *
	 * @return mixed
	 */
	public function getConstructor() {
		return $this->constructorGuts;
	}


	/**
	 * Returns the name of the object that will be generated
	 *
	 * @return string
	 */
	public function getName() {
		return $this->className;
	}


	/**
	 * Gets the declaration for a method.
	 *
	 * @param ReflectionMethod $method
	 * @return string
	 */
	public function methodDeclaration($method) {
		$out = '';

		// There's no reason it shouldn't be static, but to be generic ...
		if ($method->isStatic()) {
			$out .= 'static ';
		}

		if ($method->isPrivate()) {
			$out .= 'private ';  // Does not mock/stub well
		} elseif ($method->isProtected()) {
			$out .= 'protected ';
		} else {
			$out .= 'public ';
		}

		$out .= 'function ';

		if ($method->returnsReference()) {
			$out .= '&';
		}

		$out .= $method->getName();
		$out .= '(';
		$out .= $this->methodParameters($method);
		$out .= ")";
		return $out;
	}


	/**
	 * Return a list of parameters, possibly type hinted, for a given
	 * reflection method.
	 *
	 * Generates something like this:
	 *    ObjectName $object, array $arr, $hi = 'hi', $optional = null
	 *
	 * @param ReflectionMethod $method
	 * @return string
	 */
	public function methodParameters($method) {
		$param = array();

		foreach ($method->getParameters() as $parameter) {
			$out = '';

			if ($parameter->isArray()) {
				$out .= 'array ';
			} else {
				try {
					$class = $parameter->getClass();

					if ($class) {
						$out .= $class->getName() . ' ';
					}
				} catch (ReflectionException $e) {
					// Do nothing
				}
			}

			if ($parameter->isPassedByReference()) {
				$out .= '&';
			}

			$out .= '$' . $parameter->getName();

			if ($parameter->isDefaultValueAvailable()) {
				$value = $parameter->getDefaultValue();
				$out .= ' = ' . var_export($value, true);
			} elseif ($parameter->isOptional()) {
				$out .= ' = null';
			}

			$param[] = $out;
		}

		return implode(', ', $param);
	}


	/**
	 * Get a reflection of the original
	 *
	 * @return ReflectionClass
	 */
	public function reflect() {
		$key = strtolower($this->originalClass);
		
		if (empty(static::$reflections[$key])) {
			static::$reflections[$key] = new ReflectionClass($this->originalClass);
		}

		return static::$reflections[$key];
	}


	/**
	 * Set the constructor's code as a string of PHP code or as a callable
	 * thing that generates a string of PHP code.
	 *
	 * Passing null will generate an empty constructor; this would not
	 * call the parent nor do any other action.  Null is the default value
	 * for generated skeletons.
	 *
	 * Otherwise, if the passed value is_callable(), then we'll run that
	 * callable thing.  Lastly, if it is not is_callable(), we will just
	 * return the string version of whatever you passed.
	 *
	 * For callable things, they are passed $Skeleton (this object) and
	 * $method (the ReflectionMethod object or null).
	 *
	 * See constructorMethod() for the implementation details.
	 *
	 * @param mixed $guts
	 * @return Skeleton $this
	 */
	public function setConstructor($guts) {
		$this->constructorGuts = $guts;
		return $this;
	}


	/**
	 * Sets the name of the class to generate
	 *
	 * May add something unique to the end in order to generate a unique
	 * class name.
	 *
	 * @param string $name
	 * @return Skeleton $this
	 */
	public function setName($name) {
		if (is_null($name)) {
			$name = 'Sk_' . $this->originalClass;
		}

		if (class_exists($name)) {
			$testName = uniqid($name . '_');

			while (class_exists($testName)) {
				$testName = uniqid($name . '_');
			}

			$name = $testName;
		}

		$this->className = $name;
	}
}
