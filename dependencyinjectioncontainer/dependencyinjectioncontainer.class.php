<?php
/*
 * Copyright (c) 2011 individual committers of the code
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
 * holders shall not be used in advertising or otherwise to promote the sale,
 * use or other dealings in this Software without prior written authorization.
 * 
 * The end-user documentation included with the redistribution, if any, must 
 * include the following acknowledgment: "This product includes software
 * developed by contributors", in the same place and form as other third-party
 * acknowledgments. Alternately, this acknowledgment may appear in the software
 * itself, in the same form and location as other such third-party
 * acknowledgments.
 */

/**
 * Dependency Injection Container
 *
 * Thank you Fabian Potencier for showing me this at your presentation.
 *
 * Usage:
 *   $di = new DependencyInjectionContainer();
 *   $di->prop = 'Something';  // Set
 *   $value = $di->prop;  // Get
 *
 * If you assign a closure, it will call that function for the getter:
 *   $di = new DependencyInjectionContainer();
 *   $di->prefix = 'banana-';
 *   $di->monkey = function ($container, $key) {
 *      return $container->prefix . $key;
 *   });
 *   echo $di->monkey;  // "banana-monkey"
 *
 * If you have a function or chunk of code that you'd use to initialize
 * something, like perhaps a database connection, then you can use
 * the asShared() method to run an initializer:
 *   $di = new DependencyInjectionContainer();
 *   $di->dbDSN = 'mysql://localhost/SampleSchema';
 *   $di->db = $di->asShared(function ($container, $key) {
 *      $db = new DatabaseConnection($container->dbDSN);
 *      return $db;
 *   });
 * 
 * Can not use arrays like so:
 *   $di = new DependencyInjectionContainer();
 *   $di->arr = array();
 *   $di->arr[] = 'something';  // Does NOT get added to the array
 */
class DependencyInjectionContainer {
	protected $values = array();  // Values in the container

	/**
	 * Sets a new value into the container
	 *
	 * @param string $id
	 * @param mixed $value
	 */
	public function __set($id, $value) {
		$this->values[$id] = $value;
	}

	/**
	 * Gets a value from the container.  If the value is a function, we
	 * call it and return the value from the function.
	 *
	 * @param string $id
	 * @return mixed
	 */
	public function __get($id) {
		if (! isset($this->values[$id])) {
			throw new InvalidArgumentException('Value "' . $id . '" is not defined');
		}

		$return = $this->values[$id];
		
		if (is_callable($return)) {
			return $return($this, $id);
		}
		
		return $return;
	}
	

	/**
	 * Helper function to create closures that will initialize a static
	 * value the first time they are executed, and then keep returning
	 * the same static value.  Something like a singleton, but it isn't one.
	 *
	 * Use:
	 *   $c = new DependencyInjectionContainer();
	 *   $c->db = $c->asShared(function () {
	 *      return new DB::Connection('somewhere');
	 *   });
	 *
	 * @param callback $callable
	 * @return callback
	 */
	public function asShared($callable) {
		return function ($c, $id) use ($callable) {
			$obj = $callable($c, $id);
			$c->$id = $obj;
			return $obj;
		};
	}
}

