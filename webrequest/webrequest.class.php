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
 * Somewhat safer than using superglobals.  When you use this, the default
 * behavior is to destroy the current superglobals so programmers are forced
 * to use this object.
 *
 * @throws ErrorException Unsafe file found
 * @throws ErrorException Form data isn't an array nor a string
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
	 */
	public function __construct($destroySuperglobals = true) {
		$this->cookies = $_COOKIE ?: array();
		$this->files = $_FILES ?: array();
		$this->get = $_GET ?: array();
		$this->post = $_POST ?: array();

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
	 * @return string|array
	 */
	public function file($name = null) {
		if (is_null($name)) {
			return $this->file;
		}

		if (array_key_exists($name, $this->file)) {
			return $this->file[$name];
		}

		return $default;
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
	public function request($name, $default = null) {
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
	protected function santizeFiles($files) {
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
}
