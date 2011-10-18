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
 * PHP's built-in session handling has a few areas that need improvement.
 * Here's a class that helps overcome a couple.
 * TODO:  Session encryption
 * TODO:  Extend to allow DB-based reads and writes
 * TODO:  Extend to have an alternate file-based approach
 * TODO:  It would be good if we didn't override PHP's session and instead
 *        did our own.  Then we could have two Session objects open at the
 *        same time, just in case we wished to copy data from one to another,
 *        or for testing purposes.
 */
class Session2 {
	protected $id;  // The session ID
	protected $open = false;  // If true, the session is open

	/**
	 * Create a new session or load an existing session
	 *
	 * @param string $sessionId
	 */
	public function __construct($sessionId = null) {
		if (is_null($sessionId)) {
			$sessionId = $this->findSessionId();
		}

		$this->id = $sessionId;

		if (is_null($this->id) || ! $this->validSession($this->id)) {
			// New session or invalid ID - avoid session fixation attacks
			$this->newId();
		}

		session_id($this->id);

		// For security, we mark the cookie as HTTPS only if we are on HTTPS
		if ('on' === getenv('HTTPS')) {
			ini_set('session.cookie_secure', true);
		}

		// Also, there's no reason JavaScript should access our cookie
		ini_set('session.cookie_httponly', true);

		@session_start();
		$this->open = true;
	}

	public function __destruct() {
		$this->close();
	}

	/**
	 * Erases the session
	 */
	public function clear() {
		$_SESSION = array();
	}

	/**
	 * Closes the session if it is still open
	 */
	public function close() {
		if (! $this->open) {
			return;
		}

		session_write_close();
		$this->open = false;
	}

	/**
	 * Unsets a value from the session
	 *
	 * @param string $key
	 */
	public function delete($key) {
		if (isset($_SESSION[$key])) {
			unset($_SESSION[$key]);
		}
	}

	/**
	 * Destroys the session thoroughly.
	 */
	public function destroy() {
		session_unset();
		session_destroy();
		$this->removeHeaders();
	}

	/**
	 * Looks for the session ID in a few places
	 *
	 * @return null|string Session ID or null if not found
	 */
	protected function findSessionId() {
		$name = session_name();

		if (isset($_COOKIE[$name])) {
			return $_COOKIE[$name];
		}

		if (isset($_POST[$name])) {
			return $_POST[$name];
		}

		if (isset($_GET[$name])) {
			return $_GET[$name];
		}

		return null;
	}

	/**
	 * Gets a value from the session
	 *
	 * @param string $key
	 * @param mixed $default What to return if key isn't found
	 * @return mixed
	 */
	public function get($key, $default = null) {
		if (! array_key_exists($key, $_SESSION)) {
			return $default;
		}

		return $_SESSION[$key];
	}

	/**
	 * Returns all key => value pairs in the session.
	 *
	 * @return array
	 */
	public function getAll() {
		return $_SESSION;
	}

	/**
	 * Returns the ID of the session if the session is open
	 *
	 * @return null|string
	 */
	public function getId() {
		if ($this->open) {
			return $this->id;
		}

		return null;
	}

	/**
	 * Generate a new ID and switch the session to use this ID
	 *
	 * Use of this function at the right times will eliminate (or at least
	 * mitigate) session fixation attacks.
	 */
	public function newId() {
		$newId = hash('sha512', time() . mt_rand() . uniqid());
		$sessionData = array();
		
		if ($this->open) {
			// Preserve session data
			$sessionData = $this->getAll();
			session_unset();
			session_destroy();
			$this->removeHeaders();
		}

		$this->id = $newId;
		session_id($this->id);

		if ($this->open) {
			@session_start();
		}

		foreach ($sessionData as $k => $v) {
			$this->set($k, $v);
		}
	}

	/**
	 * PHP will always write out a "Set-Cookie" header every time it
	 * starts a session.  This is bad if you use $this->regenerate()
	 * to make the sessions more secure.  This function scans the headers
	 * that are about to be sent and removes the one that contains the
	 * PHP session ID.  Then, the next session_start() will write out
	 * the right Set-Cookie header and the client will only receive one
	 * of them.
	 */
	protected function removeHeaders() {
		if (headers_sent()) {
			return;
		}

		$headers = headers_list();
		header_remove('Set-Cookie');
		$cookieHeader = 'Set-Cookie: ';
		$sessionCookieHeader = 'Set-Cookie: ' . session_name() . '=';

		foreach ($headers as $header) {
			if (substr($header, strlen($cookieHeader)) == $cookieHeader) {
				if (substr($header, 0, strlen($sessionCookieHeader)) !== $sessionCookieHeader) {
					header($header);
				}
			}
		}
	}

	/**
	 * Sets a value in the session
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value) {
		$_SESSION[$key] = $value;
	}

	/**
	 * Before starting the session, we make sure that the session already
	 * exists.  If it does not exist, we generate a random ID.  This way,
	 * one cannot force the use of a particular session ID unless it really
	 * exists on our system.
	 *
	 * @param string $id
	 * @return boolean True if the ID is valid
	 */
	protected function validSession($id) {
		if (empty($id)) {
			return false;
		}

		$sessionFile = "/sess_{$id}";
		$fileArray = array(
			session_save_path() . $sessionFile,
			"/private/var/tmp/{$sessionFile}"
		);
		
		$fileExists = false;
		foreach($fileArray as $fileName) {
			if(file_exists($fileName)) {
				$fileExists = true;
			}
		}

		return $fileExists;
	}
}
