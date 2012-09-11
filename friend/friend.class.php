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

/**
 * This class grants access to all of an object's private and protected
 * properties and methods from outside of the class for testing purposes.
 * 
 * @throws ErrorException
 */
class Friend {

    /**
     * @var Object
     */
    protected $object;

	protected $reflectionClass = null;  // For static properties

	/**
	 * @var array Class names for static friends to work around a PHP bug
	 */
	static protected $staticClassNames = array();

    
    /**
     * @param  $object Object the object to create a friend from
	 * @throws ErrorException Indicated method can not be called
     */
    public function __construct($object) {

        if(! is_object($object)) {
            throw new ErrorException(__CLASS__ . "::__construct() requires an object");
        }

        $this->object = $object;
    }


    /**
     * Passes method execution off to $this->object and makes that method public
     *
     * @param  $name
     * @param  $arguments
     * @return mixed The result of the invoked method
	 * @throws ErrorException Indicated method can not be called
     */
    public function __call($name, $arguments) {
		$method = $this->getMethod($name);

		if (! is_null($method)) {
			// Don't put this in the try/catch since it could rightfully throw
			return $reflectionMethod->invokeArgs($this->object, $arguments);
		}

		return $this->callMagic('__call', array($name, $arguments), 'Method ' . $name . ' does not exist');
    }

	
	/**
	 * Returns the original object
	 *
	 * @return Object
	 */
	public function __friend_object() {
		return $this->object;
	}


	/**
	 * Returns a static property
	 *
	 * @return mixed
	 */
	public function __friend_get_static($name) {
		$property = $this->getStaticPropertyReflection($name);
		return $property->getValue();
	}


	/**
	 * Sets a static property
	 *
	 * @return mixed
	 */
	public function __friend_set_static($name, $value) {
		$property = $this->getStaticPropertyReflection($name);
		$property->setValue($value);
	}


    /**
     * Gets $this->object->$name and makes the property public so we can fetch it
     *
     * @param  $name
     * @return mixed The property value
     */
    public function __get($name) {
        $property = $this->getProperty($name);

		if (! is_null($property)) {
			return $property->getValue($this->object);
		}

		return $this->callMagic('__get', array($name), 'Property ' . $name . ' does not exist');
    }


    /**
     * Forwards the isset() call on to $this->object->$name and makes that property public
     *
     * @param  $name
     * @return bool True if $name isset, false otherwise
     */
    public function __isset($name){
        $property = $this->getProperty($name);

		if (! is_null($property)) {
			$value = $property->getValue($this->object);
			return isset($value);
		}

		return $this->callMagic('__isset', array($name), 'Property ' . $name . ' does not exist');
    }


    /**
     * Sets $this->object->$name
     *
     * @param  $name
     * @param  $value
     * @return void
     */
    public function __set($name, $value) {
        $property = $this->getProperty($name);

		if (! is_null($property)) {
			return $property->setValue($this->object, $value);
		}

		return $this->callMagic('__set', array($name, $value), 'Property ' . $name . ' does not exist');
    }


    /**
     * Tries to unset $this->object->$name, not quite right
     *
     * @param  $name
     * @return void
     */
    public function __unset($name) {
        $property = $this->getProperty($name);

		if (! is_null($property)) {
			$property->setValue($this->object, null);
			return;
		}

		$this->callMagic('__unset', array($name), 'Property ' . $name . ' does not exist');
    }


	/**
	 * Call a "magic method" on the targeted class, if possible
	 *
	 * @param string $method
	 * @param mixed $arguments
	 * @param string $errorMessage Used in ErrorException
	 * @return mixed Value from called function
	 * @throws ErrorException Method does not exist
	 */
	 protected function callMagic($method, $arguments, $errorMessage) {
		 if (! method_exists($this->object, $method)) {
			 throw new ErrorException($errorMessage);
		 }

		 return call_user_func_array(array($this->object, $method), (array) $arguments);
	 }


	/**
	 * Helper method to get a method and make sure it is accessible.  Also
	 * handles static method calls and calls them staticly.
	 *
	 * @param string $name
	 * @return null|ReflectionMethod
	 */
	protected function getMethod($name) {
		try {
			$method = new ReflectionMethod($this->object, $name);
			$method->setAccessible(true);

			if ($method->isStatic()) {
				$extendedClassName = $this->getStaticClassName();
				$method = new ReflectionMethod($extendedClassname, $name);
				$method->setAccessible(true);
			}
		} catch (Exception $e) {
			return null;
		}

		return $method;
	}


	/**
	 * Build a new class to extend our friended object so we can call a
	 * static method somewhere in our ancestor tree and let calls go back
	 * down the tree properly.
	 *
	 * @param string $originalName
	 * @return string Static class name
	 */
	protected function getStaticCassDefinition($originalName) {
		// Create the class and save it
		$newClassName = uniqid('Friend_' . $originalName . '_');
		$classDef = 'class ' . $newClassName . ' extends ' . $originalName . "{\n";
		$refl = new ReflectionClass($originalName);

		// Build each static method
		foreach ($refl->getMethods(ReflectionMethod::IS_STATIC) as $method) {
			if (! $method->isFinal()) {
				$classDef .= $this->getStaticMethodDefinition($method);
			}
		}

		// Finish the class
		$classDef .= "}\n";
		eval($classDef);
		return $newClassName;
	}

	
	/**
	 * Gets a static class name for a method to work around a PHP bug with
	 * reflection and late static binding calling down to descendents.
	 *
	 * @return string
	 */
	protected function getStaticClassName() {
		$originalname = get_class($this->object);

		if (! empty(static::$staticClassnames[$originalName])) {
			return static::$staticClassNames[$originalName];
		}

		$newClassName = $this->getStaticClassDefinition($originalName);
		static::$staticClassNames[$originalName] = $newClassName;
		return $newClassName;
	}


	/**
	 * Gets a method definition for a static method so you can create a
	 * class on the fly.
	 *
	 * FIXME:  This won't work if you have a static method expecting
	 * arguments that aren't listed in the function declaration.
	 *
	 * FIXME:  Should probably pull this static class generation stuff
	 * out into its own class
	 *
	 * @param ReflectionMethod $method
	 * @return string
	 */
	protected function getStaticMethodDefinition($method) {
		$out = '';

		// There's no reason it shouldn't be static, but to be generic ...
		if ($method->isStatic()) {
			$out .= 'static ';
		}

		if ($method->isPrivate()) {
			$out .= 'private ';
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
		$out .= $this->getStaticMethodParameters($method, true);
		$out .= ') { return parent::' . $method->getName() . '(';
		$out .= $this->getStaticMethodParameters($method, false);
		$out .= "); }\n";
		return $out;
	}


	/**
	 * Return a list of parameters, possibly type hinted, for a given
	 * reflection method.
	 *
	 * @param ReflectionMethod $method
	 * @param boolean asDeclaration
	 * @return string
	 */
	protected function getStaticMethodParameters($method, $asDeclaration) {
		$param = array();

		if (! $asDeclaration) {
			foreach ($method->getParameters() as $parameter) {
				$param[] = '$' . $parameter->getName();
			}

			return implode(', ', $param);
		}

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
     * Helper method that returns a ReflectionProperty that's been made public
     *
     * @param  $name
     * @return ReflectionProperty
     */
    protected function getProperty($name){
		try {
			$property = new ReflectionProperty($this->object, $name);
			$property->setAccessible(true);
			return $property;
		} catch (Exception $e) {
			// Do nothing
		}
		return null;
    }


	/**
	 * Helper to get a value of a reflection of a static property
	 *
	 * @return ReflectionProperty
	 */
	protected function getStaticPropertyReflection($name) {
		if (is_null($this->reflectionClass)) {
			$this->reflectionClass = new ReflectionClass(get_class($this->object));
		}

		$property = $this->reflectionClass->getProperty($name);
		$property->setAccessible(true);
		return $property;
	}
}
