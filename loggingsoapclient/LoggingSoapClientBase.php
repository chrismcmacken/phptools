<?php

class LoggingSoapClientBase extends SoapClient {
	protected $closeConnection = null;

	// Additional headers to send, when possible
	protected $httpHeaders = array();
	protected $httpVersion = '1.1';


	// String containing response HTTP headers
	protected $lastResponseHttpHeaders = null;
	protected $lastResponseHttpCode = null;


	// Logging callback
	protected $logger = null;
	

	// Timeout for the request, in seconds.  0 means no timeout.
	protected $timeoutConnection = null;
	protected $timeoutSocket = null;
	protected $timeoutWsdl = null;


	/**
	 * Grab the timeout from the SOAP options
	 */
	public function __construct($wsdl, $options = array()) {
		$exception = null;
		$oldTimeout = null;

		if (is_array($options)) {
			$this->timeoutConnection = $this->getOptionInteger($options, 'connection_timeout');
			$this->timeoutSocket = $this->getOptionInteger($options, 'socket_timeout');
			$this->timeoutWsdl = $this->getOptionInteger($options, 'wsdl_timeout', $this->timeoutSocket);
			$this->closeConnection = $this->getOptionBoolean($options, 'connection_close', false);
		}

		if ($this->timeoutWsdl) {
			$oldTimeout = ini_set('default_socket_timeout', $this->timeoutWsdl);
		}

		try {
			parent::__construct($wsdl, $options);
		} catch (Exception $e) {
			$exception = $e;
		}

		if ($this->timeoutWsdl) {
			ini_set('default_socket_timeout', $oldTimeout);
		}

		if (! is_null($exception)) {
			throw $exception;
		}
	}

	/**
	 * Override SoapClient's native HTTP request method so that we can
	 * a) Log request/response
	 * b) Modify HTTP headers, grab cookies, and other stuff
	 */
	public function __doRequest($request, $location, $action, $version, $oneWay = 0) {
		$this->initialize();
		$this->logCallback("Request $action $version on $location", $request);
		$response = $this->transport($request, $location, $action, $version, $oneWay);
		$this->logCallback("Response $action $version on $location", $response);
		return $response;
	}
	
	
	public function __getLastResponseHttpCode() {
		return $this->lastResponseHttpCode;
	}


	public function __getLastResponseHttpHeaders() {
		return $this->lastResponseHttpHeaders;
	}


	/**
	 * Sets or removes custom HTTP header(s) to the request.
	 * 
	 * $headers can be ...
	 *    a string, "Content-type: text/html"
	 *    an array, array("Content-type: text/html", "Accept: text/html")
	 *    null, and this erases the headers
	 *
	 * @param string|array|null $headers 'Content-type: text/html'
	 * @return $this
	 */
	public function __setHttpHeaders($headers = null) {
		if (is_null($headers)) {
			$this->httpHeaders = array();
		} else {
			$this->httpHeaders = (array) $headers;
		}
	}

	
	/**
	 * Sets the HTTP version to use for the request
	 *
	 * @param string $version
	 * @return $this
	 */
	public function __setHttpVersion($version) {
		$this->httpVersion = $version;
		return $this;
	}
	
	
	/**
	 * Provide a Log object to log requests/responses
	 * 
	 * @param xallable|null $callabck
	 * @return $this
	 */
	public function __setLogger($callback = null) {
		if (is_callable($callback)) {
			$this->logger = $callback;
		} else {
			$this->logger = null;
		}

		return $this;
	}


	protected function initialize() {
		$this->lastResponseHttpHeaders= null;
		$this->lastResponseHttpCode = null;
	}
	
	
	protected function getOptionBoolean($arr, $key, $default = null) {
		if (! array_key_exists($key, $arr)) {
			return $default;
		}

		$val = $arr[$key];
		settype($val, 'boolean');

		return $val;
	}


	protected function getOptionInteger($arr, $key, $default = null) {
		if (! array_key_exists($key, $arr)) {
			return $default;
		}

		$val = $arr[$key];
		settype($val, 'integer');
		
		if ($val < 1) {
			return $default;
		}

		return $val;
	}

	protected function getOptionString($arr, $key, $default = null) {
		if (! array_key_exists($key, $arr)) {
                        return $default;
                }

                $val = $arr[$key];
                settype($val, 'string');

                return $val;
	}


	/**
	 * Log some XML
	 *
	 * If no logger is set up, use trigger_error with E_USER_NOTICE instead.
	 * 
	 * @param string $request
	 * @param string $location
	 * @param string $action
	 * @param string $version
	 * @return void
	 */
	protected function logCallback($message, $detail) {
		if (is_null($this->logger)) {
			trigger_error($message, E_USER_NOTICE);
		} else {
			call_user_func($this->logger, $message, $detail);
		}
	}


	/**
	 * Make a string containing the cookies
	 *
	 * @param array $cookies
	 */
	protected function makeCookieString(array $cookies) {
		$out = array();

		foreach ($cookies as $key => $val) {
			if (is_array($val)) {
				$val = reset($val);
			}

			$out[] = $key . '=' . $val;
		}

		return implode('; ', $out);
	}


	protected function makeHttpHeaders($request, $location, $action) {
		$urlParts = parse_url($location);
		$headers = array();
		$contentTypeSet = false;

		// Required for HTTP/1.1, acceptable for HTTP/1.0
		$headers[] = "Host: " . $urlParts['host'];
		$headers[] = "SoapAction: $action";

		foreach ($this->httpHeaders as $header) {
			$line = explode(':', $header);

			if (strtolower($line[0]) == 'content-type') {
				$contentTypeSet = true;
			}

			// Strip content length header here
			if (strtolower($line[0]) == 'content-length') {
				$headers[] = $header;
			}
		}

		if (! $contentTypeSet) {
			$headers[] = 'Content-Type: text/xml; charset=utf-8';
		}

		if (! empty($this->_cookies)) {
			$headers[] = "Cookie: " . $this->makeCookieString($this->_cookies);
		}

		$headers[] = 'Content-Length: ' . strlen($request);

		if ($this->closeConnection) {
			$headers[] = "Connection: close";  // Solves a lot of HTTP/1.1 problems
		}
		
		return $headers;
	}


	protected function processHttpStatus($status) {
		// The first line contains the HTTP version, status code, message
		$status = explode(" ", $status);
		
		if (count($status) > 1) {
			$this->lastResponseHttpCode = $status[1];
		}
	}
	

	/**
	 * Turns an HTTP response into an array
	 *
	 *     statusCode: HTTP status code (optionally)
	 *     headers: HTTP headers
	 *     body: The real content of the response
	 *
	 * This should be where an HTTP/1.1 response that has
	 * "Content-Transfer-Encoding: chunked" is handled.
	 *
	 * @param string $response Full, unmanaged response
	 * @param boolean $hasStatus True if this has the HTTP status line
	 */
	protected function processTextResponse($response, $hasStatus = true) {
		$out = array(
			'status' => null,
			'headers' => null,
			'body' => null
		);
		$lines = explode("\r\n", $response);

		if ($hasStatus && count($lines)) {
			$statusLine = array_shift($lines);
			$statusLine = explode(" ", $statusLine);

			if (count($statusLine) > 1) {
				$out['status'] = $statusLine[1];
			}
		}

		$headers = array();

		while (count($lines) && strlen(trim($lines[0])) > 0) {
			$headers[] = array_shift($lines);
		}
	
		if (count($lines)) {
			array_shift($lines);  // Remove the blank line
		}

		$out['headers'] = implode("\r\n", $headers);
		$out['body'] = implode("\r\n", $lines);

		return $out;
	}
}

