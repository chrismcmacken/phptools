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
 * This class grants access to all of an object's private and protected properties and methods from
 * outside of the class for testing purposes.
 * 
 * @throws ErrorException
 */
class Friend {

    /**
     * @var Object
     */
    private $object;

    
    /**
     * @param  $object Object the object to create a friend from
     */
    public function __construct($object) {

        if(! is_object($object)) {
            throw new ErrorException("Friend::__construct() requires an object");
        }

        $this->object = $object;
    }


    /**
     * Passes method execution off to $this->object and makes that method public
     *
     * @param  $name
     * @param  $arguments
     * @return mixed The result of the invoked method
     */
    public function __call($name, $arguments) {
        $reflectionMethod = new ReflectionMethod($this->object, $name);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($this->object, $arguments);
    }


    /**
     * Gets $this->object->$name and makes the property public so we can fetch it
     *
     * @param  $name
     * @return mixed The property value
     */
    public function __get($name) {
        $property = $this->getProperty($name);
        return $property->getValue($this->object);
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
        $property->setValue($this->object, $value);
    }


    /**
     * Forwards the isset() call on to $this->object->$name and makes that property public
     *
     * @param  $name
     * @return bool True if $name isset, false otherwise
     */
    public function __isset($name){
        $property = $this->getProperty($name);
        $value = $property->getValue($this->object);
        return isset($value);
    }


    /**
     * Tries to unset $this->object->$name, not quite right
     *
     * @param  $name
     * @return void
     */
    public function __unset($name) {
        $property = $this->getProperty($name);
        $property->setValue($this->object, null);
    }


    /**
     * Helper method that returns a ReflectionProperty that's been made public
     *
     * @param  $name
     * @return ReflectionProperty
     */
    protected function getProperty($name){
        $property = new ReflectionProperty($this->object, $name);
        $property->setAccessible(true);
        return $property;
    }
}
