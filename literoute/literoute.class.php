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
 * LiteRoute is a very simplistic hiearchical front-end controller.  It's
 * so light that it is just basically a router to get the request to the
 * right portion of your code.  If you are looking for more, try out
 * Symfony, Konstrukt, or maybe Phabricator.
 */
abstract class LiteRoute {
	protected $request = null;  // WebRequest object
	protected $uri = '';  // Not the full URI, just the one for this component


	/**
	 * Create a new controller
	 *
	 * @param string $uri URI that's left for processing - you shouldn't need it
	 * @param WebRequest $request Where one can get POST/GET/etc
	 */
	public function __construct($uri = null, $request = null) {
		if (is_null($context)) {
			$request = new WebRequest();
		}

		if (is_null($uri)) {
			$uri = $request->uri(false);
		}

		$this->uri = $uri;
		$this->request = $request;
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
		$method = $request->method();
		$method = ucfirst(strtolower($method));
		$methodName = 'handle' . $method;

		if (method_exists($controller, $methodName)) {
			$controller->$methodName();
		}

		$controller->render();
	}


	/**
	 * Find the right controller to use.  Return that object.
	 *
	 * It might be this one.  It might be some child.  Who knows?
	 *
	 * @return LiteRoute
	 */
	public function getController() {
		$component = $this->nextComponent();

		if (! is_null($component)) {
			$target = $this->map($component);

			if (is_string($target)) {
				$uri = substr($this->uri, 1 + strlen($component));
				$this->uri = '/' . $component;
				$class = new $className($uri, $request);
				return $class->getController();
			}
		}

		return $this;
	}


	/**
	 * Check if there is a child controller we should use.
	 * $target is from the next portion of the URL that we should process.
	 *
	 * @param string $target Next portion from the URI
	 * @return string|null Class name of a child controller
	 */
	protected function map($target) {
		throw new Exception('No map defined');
	}


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
	 * We've handled our action and now can render the page.
	 *
	 * @return mixed Varies based on your implementation.
	 */
	protected function render() {
		throw new Exception('This page did not render content.');
	}
}
