<?php

/*
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
 * SlimRoute is a very simplistic hiearchical front-end controller.  It's
 * so light that it is just basically a router to get the request to the
 * right portion of your code.  If you are looking for more, try out
 * Symfony, Konstrukt, or maybe Phabricator.
 */
abstract class SlimRoute {
	protected $controller = null;
	protected $request = null;  // WebRequest object
	protected $parent = null;
	protected $uri = '';  // Not the full URI, just the one for this component
	protected $magicVariables = null;


	/**
	 * Create a new controller
	 *
	 * @param string $uri URI that's left for processing - you shouldn't need it
	 * @param WebRequest $request Where one can get POST/GET/etc
	 */
	public function __construct($uri = null, $parent = null) {
		if (is_null($parent)) {
			$this->request = new WebRequest();
			$this->magicVariables = new stdClass();
		} else {
			$this->parent = $parent;
			$this->request = $parent->request;
			$this->magicVariables = $parent->magicVariables;
		}

		// Do this second so $this->request is set
		if (is_null($uri)) {
			$this->uri = $this->request->uri(false);
		} else {
			$this->uri = $uri;
		}
	}


	/**
	 * Get a magic variable from a shared storage object
	 *
	 * @param string name
	 * @return mixed Null if it is not yet set
	 */
	public function __get($name) {
		if (property_exists($this->magicVariables, $name)) {
			return $this->magicVariables->$name;
		}
		
		return null;
	}


	/**
	 * Set a magic variable into a shared storage object
	 *
	 * @param string name
	 * @param mixed value
	 */
	public function __set($name, $value) {
		$this->magicVariables->$name = $value;
	}


	/**
	 * Handle an incoming request
	 *
	 * Look up the right controller, then call handle* method (where
	 * GET requests use handleGet, etc) if it exists, then call render().
	 *
	 * @param $request Request object
	 * @return mixed
	 */
	public function handle() {
		$controller = $this->getController();
		$method = $this->request->method();
		$method = ucfirst(strtolower($method));
		$methodName = 'handle' . $method;
		$controller->$methodName();
		return $controller->render();
	}


	/**
	 * Null functions that you are supposed to override when needed
	 */
	protected function handleDelete() {}
	protected function handleGet() {}
	protected function handleHead() {}
	protected function handlePost() {}
	protected function handlePut() {}


	/**
	 * Find the right controller to use.  Return that object.
	 *
	 * It might be this one.  It might be some child.  Who knows?
	 *
	 * @return SlimRoute
	 */
	public function getController() {
		if (! is_null($this->controller)) {
			return $this->controller;
		}

		$component = $this->nextComponent();

		if (! is_null($component)) {
			$target = $this->map($component);

			if (is_string($target)) {
				$uri = substr($this->uri, 1 + strlen($component));
				$this->uri = '/' . $component;
				$class = new $target($uri, $this);
				$this->controller = $class->getController();
				return $this->controller;
			}
		}

		$this->controller = $this;
		return $this;
	}


	/**
	 * Return the current URI that got us up to this controller
	 *
	 * @return string
	 */
	public function getUri() {
		if ($this->parent) {
			return $this->parent->getUri() . $this->uri;
		}

		return $this->uri;
	}


	/**
	 * Check if there is a child controller we should use.
	 * $target is from the next portion of the URL that we should process.
	 *
	 * @param string $target Next portion from the URI
	 * @return string|null Class name of a child controller
	 */
	protected function map($target) {}


	/**
	 * Get the next component piece from the URI if there is any.
	 *
	 * @return string|null
	 */
	protected function nextComponent() {
		if (preg_match('~^/([^/]+)~', $this->uri, $matches)) {
			return $matches[1];
		}

		return null;
	}


	/**
	 * Sends out the 302 Temporary Redirect header and exits.
	 *
	 * @param string $uri Relative URI on this site
	 */
	protected function redirect($uri = '') {
		$fullUri = $this->url($uri);
		@header('Location: ' . $fullUri);
		exit();
	}


	/**
	 * We've handled our action and now can render the page.
	 *
	 * @return mixed Varies based on your implementation.
	 */
	protected function render() {
		throw new Exception('This page did not render content.');
	}


	/**
	 * Create a URL
	 *
	 * @param string $relativePath
	 * @return string Absolute URL
	 */
	public function url($relativePath) {
		// Make the link absolute
		if (substr($relativePath, 0, 1) != '/') {
			$controller = $this->getController();
			$parent = $controller->parent;

			if ($parent) {
				$currentUri = $parent->getUri();
				$uri = $currentUri . '/' . $relativePath;
			}
		} else {
			$uri = $relativePath;
		}

		$uri = preg_replace('~[^/]*/\\.\\./~', '/', $uri);
		$uri = preg_replace('~/\\./~', '/', $uri);
		return $uri;
	}
}
