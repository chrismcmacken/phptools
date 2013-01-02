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

require_once(__DIR__ . '/../skeleton/Skeleton.php');

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
			return $method->invokeArgs($this->object, $arguments);
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
     * @return mixed
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
     * @return mixed
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
				$method = new ReflectionMethod($extendedClassName, $name);
				$method->setAccessible(true);
			}
		} catch (Exception $e) {
			return null;
		}

		return $method;
	}


	/**
	 * Gets a static class name for a method to work around a PHP bug with
	 * reflection and late static binding calling down to descendents.
	 *
	 * @return string
	 */
	protected function getStaticClassName() {
		$originalName = get_class($this->object);

		if (! empty(static::$staticClassNames[$originalName])) {
			return static::$staticClassNames[$originalName];
		}

		$skeleton = new Skeleton($originalName);
		$newClassName = $skeleton->create();  // Returns new class name
		static::$staticClassNames[$originalName] = $newClassName;
		return $newClassName;
	}


	/**
	 * Helper method that returns a ReflectionProperty that's been made public.
	 *
	 * Needs to walk up the ReflectionClass parents in order to search for
	 * a private property in any of the parents.
	 *
	 * @param  $name
	 * @return ReflectionProperty
	 */
    protected function getProperty($name){
		$reflectionClass = new ReflectionClass($this->object);

		while ($reflectionClass) {
			try {
				// This throws if the property does not exist
				$property = $reflectionClass->getProperty($name);
				$property->setAccessible(true);
				return $property;
			} catch (Exception $e) {
				// Do nothing, continue
			}

			// Not found, go higher
			$reflectionClass = $reflectionClass->getParentClass();
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
