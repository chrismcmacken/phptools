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
 * Somewhat safer than using superglobals.  When you use this, the default
 * behavior is to destroy the current superglobals so programmers are forced
 * to use this object.
 *
 * @throws ErrorException Unsafe file found
 * @throws ErrorException Form data isn't an array nor a string
 * @throws ErrorException Invalid method
 */
class WebRequest {
	protected $cookies;
	protected $files;
	protected $get;
	protected $post;

	/**
	 * Copy in the data from the superglobals and unset the originals so
	 * we force programmers to use this object instead.
	 *
	 * This default behavior can be toggled when you create the class.
	 *
	 * @param boolean $destroySuperglobals If true, erase them (default)
	 * @throws ErrorException Unsafe file found
	 * @throws ErrorException Form data isn't an array nor a string
	 * @throws ErrorException Invalid method
	 */
	public function __construct($destroySuperglobals = true) {
		$this->cookies = $_COOKIE ?: array();
		$this->files = $_FILES ?: array();
		$this->get = $_GET ?: array();

		// Only process POST variables if the request is a POST
		if ($this->isPost()) {
			$this->post = $_POST ?: array();
		} else {
			$this->post = array();
		}

		if ($destroySuperglobals) {
			$superglobalNames = array(
				'_COOKIE',
				'_FILES',
				'_GET',
				'_POST',
				'_REQUEST'
			);

			foreach ($superglobalNames as $name) {
				unset($$name);
				unset($GLOBALS[$name]);
				$$name = array();
				$GLOBALS[$name] = &$$name;
			}
		}

		// Remove quotes if applicable from $_GET and $_POST
		$this->get = $this->sanitizeFormData($this->get, 'GET');
		$this->post = $this->sanitizeFormData($this->post, 'POST');

		// Ensure files are uploaded files
		$this->files = $this->sanitizeFiles($this->files);

		// Validate the method
		$this->sanitizeMethod();
	}

	/**
	 * Return a single cookie or all cookies
	 *
	 * @param string $name optional
	 * @param mixed $default optional, defaults to null
	 * @return string|array
	 */
	public function cookie($name = null, $default = null) {
		if (is_null($name)) {
			return $this->cookies;
		}

		if (array_key_exists($name, $this->cookies)) {
			return $this->cookies[$name];
		}

		return $default;
	}

	/**
	 * Return a single file's information or all uploaded file information
	 *
	 * @param string $name optional
	 * @return array
	 */
	public function file($name = null) {
		if (is_null($name)) {
			return $this->file;
		}

		if (array_key_exists($name, $this->file)) {
			return $this->file[$name];
		}

		return null;
	}

	/**
	 * Return a single get value or all get values
	 *
	 * @param string $name optional
	 * @param mixed $default optional, defaults to null
	 * @return string|array
	 */
	public function get($name = null, $default = null) {
		if (is_null($name)) {
			return $this->get;
		}

		if (array_key_exists($name, $this->get)) {
			return $this->get[$name];
		}

		return $default;
	}

	/**
	 * Returns data that was sent in the request.  This may not work well for
	 * POST since PHP already parsed the input to provide the script with
	 * the $_POST superglobal.
	 *
	 * When dealing with large amounts of data, you may wish to use
	 * inputDataToFile() instead.
	 *
	 * @return string
	 */
	public function inputData() {
		return file_get_contents('php://input');
	}

	/**
	 * Copies the input data to a file
	 *
	 * The $file parameter can either be a file pointer or a filename.
	 * You can use fopen('...filename...', 'w') to get your own file pointer
	 * or let this do it for you.  When passing in a file pointer, this
	 * method does not close the file; it only writes data.
	 *
	 * This can work better than inputData() when you are handling large
	 * amounts of data.
	 *
	 * @param $file File pointer or filename
	 * @return string
	 */
	public function inputDataToFile($file) {
		$opened = false;

		if (is_string($file)) {
			$file = fopen($file, 'w');
			$opened = true;
		}

		$fp = fopen('php://input', 'r');

		while ($moreData = fread($fp, 8192)) {
			fwrite($fp, $moreData);
		}

		fclose($fp);

		if ($opened) {
			fclose($file);
		}

		return $data;
	}

	/**
	 * Returns true if the request is a GET
	 *
	 * @return boolean
	 */
	public function isGet() {
		return 'GET' == $this->method();
	}

	/**
	 * Returns true if the request is a POST
	 *
	 * @return boolean
	 */
	public function isPost() {
		return 'POST' == $this->method();
	}

	/**
	 * Returns true if the request is a PUT
	 *
	 * @return boolean
	 */
	public function isPut() {
		return 'PUT' == $this->method();
	}

	/**
	 * Return the request method, always in upper case
	 *
	 * It might be anything from this list:
	 *    GET, POST, PUT, DELETE, HEAD, OPTIONS, TRACE, CONNECT
	 * Most likely it will just be the first four.
	 *
	 * @return string
	 */
	public function method() {
		return strtoupper($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * Parses a query string, like what PHP's parse_str() method does.
	 *
	 * POST data is sent to the input (see inputData()) encoded this way,
	 * as is GET data on the URL.
	 *
	 * This method differs from parse_str() in that it always returns an
	 * array and that it does not convert spaces and dots in the names
	 * into underscores.
	 *
	 * @param string $query
	 * @return array
	 */
	public function parseQueryString($query) {
		$result = array();
		$pairs = explode('&', $query);

		foreach ($pairs as $pair) {
			$kv = explode('=', $pair, 2);
			$name = urldecode($kv[0]);

			if (count($kv) > 1) {
				$value = urldecode($kv[1]);
			} else {
				$value = '';  // Matches PHP's parsing
			}

			// PHP's "[]" notation on names indicating an array
			if (substr($name, -2) === '[]') {
				// Remove the notation
				$name = substr($name, 0, -2);
			
				// Force the result for this to be an array
				// If it already exists, it will change into an array
				// automatically just below
				if (! array_key_exists($name, $result)) {
					$result[$name] = array();
				}
			}

			// Standard CGI practice makes an array if we specify the same
			// name more than once
			if (array_key_exists($name, $result)) {
				if (! is_array($result[$name])) {
					$result[$name] = (array) $result[$name];
				}

				$result[$name][] = $value;
			} else {
				$result[$name] = $value;
			}
		}

		return $result;
	}

	/**
	 * Return a single post value or all posted values
	 *
	 * @param string $name optional
	 * @param mixed $default optional, defaults to null
	 * @return string|array
	 */
	public function post($name = null, $default = null) {
		if (is_null($name)) {
			return $this->post;
		}

		if (array_key_exists($name, $this->post)) {
			return $this->post[$name];
		}

		return $default;
	}

	/**
	 * This works a lot like PHP's $_REQUEST superglobal, but we specify
	 * the order ourselves.  Other than that, it lets you get anything
	 * that was sent in a get, posted value, or cookie.  If there are multiple
	 * methods that a parameter was sent, the order of priority is
	 * get, post, then cookie.
	 *
	 * @param string $name optional
	 * @param mixed $default optional, defaults to null
	 * @return string|array
	 */
	public function request($name = null, $default = null) {
		if (is_null($name)) {
			// Using + produces a union of two arrays.  Similar to
			// array_merge, but doesn't renumber numeric keys, and the left-
			// hand of the + has the higher priority.
			$out = $this->get;
			$out = $out + $this->post;
			$out = $out + $this->cookies;
			return $out;
		}

		if (array_key_exists($name, $this->get)) {
			return $this->get[$name];
		}

		if (array_key_exists($name, $this->post)) {
			return $this->post[$name];
		}

		if (array_key_exists($name, $this->cookies)) {
			return $this->cookies[$name];
		}

		return $default;
	}

	/**
	 * Perform sanity checks on files that were uploaded
	 *
	 * @param array $files
	 * @return array Cleansed files
	 * @throws ErrorException Unsafe file found
	 */
	protected function sanitizeFiles($files) {
		$out = array();

		foreach ($files as $formInputName => $fileInfo) {
			// PHP has to really screw up for this one
			if (! is_array($fileInfo)) {
				throw new ErrorException($formInputName . ' did not provide valid file information array');
			}

			// Again, PHP has to really be messed up for this
			if (empty($fileInfo['tmp_name']) || ! array_key_exists('size', $fileInfo) || ! array_key_exists('error', $fileInfo)) {
				throw new ErrorException($formInputName . ' missing required information from array');
			}

			// Upload error detected
			if ($fileInfo['error'] != UPLOAD_ERR_OK) {
				throw new ErrorException($formInputName . ' reported error during upload', $fileInfo['error']);
			}

			// Double-check the temp file exists
			if (! file_exists($fileInfo['tmp_name']) || ! is_file($fileInfo['tmp_name'])) {
				throw new ErrorException($formInputName . ' lists a temporary file that does not exist or is not a regular file');
			}

			// Double-check size
			if (filesize($fileInfo['tmp_name']) != $fileInfo['size']) {
				throw new ErrorException($formInputName . ' has a size mismatch');
			}

			// Make sure the file was uploaded OR make sure we're command-line
			if (PHP_SAPI !== 'cli' && ! is_uploaded_file($fileInfo['tmp_name'])) {
				throw new ErrorException($formInputName . ' is not an uploaded file');
			}

			// Sanitize the incoming filename a little
			$fileInfo['name'] = basename($fileInfo['name']);

			// Done with this one
			$out[$formInputName] = $fileInfo;
		}

		return $out;
	}

	/**
	 * Make sure everything is a string data type or an array.  Nothing
	 * should come from a form that isn't one of those two.  While we are at
	 * it, remove escaping from strings if magic quoting is enabled.
	 *
	 * @param array $input
	 * @param string $location
	 * @return array Cleansed input
	 * @throws ErrorException Form data isn't an array nor a string
	 */
	public function sanitizeFormData($input, $location) {
		static $gpc = null;

		if (is_null($gpc)) {
			$gpc = get_magic_quotes_gpc();
		}

		$out = array();

		foreach ($input as $k => $v) {
			if (is_array($v)) {
				$out[$k] = $this->sanitizeFormData($v, $location . '[' . $k . ']');
			} else {
				if (! is_string($v)) {
					throw new ErrorException($location . '[' . $k . ']' . ' is not a string');
				}
				if ($gpc) {
					$v = stripslashes($v);
				}
				$out[$k] = $v;
			}
		}

		return $out;
	}


	/**
	 * PHP should only be called for some method types
	 *
	 * @throws ErrorException Invalid method
	 */
	public function sanitizeMethod() {
		if (in_array($this->method(), array(
			'HEAD',
			'GET',
			'POST',
			'PUT',
			'DELETE'
		))) {
			return;
		}

		// This method should not be passed to PHP
		throw new ErrorException('Invalid method: ' + $this->method());
	}


	/**
	 * Returns the URI for the current script
	 *
	 * @return string
	 */
	public function uri($queryString = true) {
		$uri = null;

		if (is_null($uri) && array_key_exists('REQUEST_URI', $_SERVER)) {
			$uri = explode('?', $_SERVER['REQUEST_URI']);
		}

		if (is_null($uri)) {
			$uri = array('/');

			if (array_key_exists('QUERY_STRING', $_SERVER)) {
				$uri[] = $_SERVER['QUERY_STRING'];
			}
		}

		if (! $queryString) {
			return $uri[0];
		}

		return implode('?', $uri);
	}
}
