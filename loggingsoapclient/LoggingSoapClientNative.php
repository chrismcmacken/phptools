<?php

/**
 * Use the PHP built-in SoapClient for things.
 *
 * Does not support lastResponseHttpCode, lastResponseHttpHeaders,
 * a timeout, custom HTTP Headers nor HTTP version.
 *
 * Possible errors and errors returned by PHP 5's SoapClient:
 *    WSDL, 404 error - throws SoapFault
 *    WSDL, not listening - throws SoapFault
 *    WSDL, connect timeout - throws SoapFault
 *    WSDL, weird XML - throws SoapFault
 *    WSDL, SOAP error XML - throws SoapFault
 *    WSDL, empty document - throws SoapFault
 *    SOAP, 404 error - throws SoapFault
 *    SOAP, not listening - throws SoapFault
 *    SOAP, connect timeout - throws SoapFault
 *    SOAP, weird XML - throws SoapFault
 *    SOAP, empty document - call returns null
 *    SOAP, SOAP error XML - throws SoapFault
 *    SOAP, Incorrect call parameters - throws SoapFault
 * 
 * To test the various conditions, you may need to ...
 *    404 error:  Point to a wrong page
 *    not listening:  Point to a wrong port on a valid machine
 *    connect timeout:  Point to an invalid machine (192.168.100.99)
 *    weird XML:  Point to some wacky XML
 *    SOAP error XML:  Point to a pregenerated SOAP error
 *    incorrect call parameters:  Purposely screw up a SOAP request
 */

require_once(__DIR__ . '/LoggingSoapClientBase.php');

class LoggingSoapClientNative extends LoggingSoapClientBase {
	// Timeout for the request, in seconds.  0 means no timeout.
	public $_timeout = 0;


	/**
	 * Override __call so we can set a timeout
	 *
	 * @param string $method
	 * @param array $arguments
	 */
	public function __call($method, $args) {
		$exception = null;
		$oldTimeout = null;
		$arguments = func_get_args();
		
		if ($this->timeoutSocket) {
			$oldTimeout = ini_set('default_socket_timeout', $this->timeoutSocket);
		}

		try {
			$result = call_user_func(array('parent', '__call'), $method, $args);
		} catch (Exception $e) {
			$exception = $e;
		}

		if ($this->timeoutSocket) {
			ini_set('default_socket_timeout', $oldTimeout);
		}

		if (! is_null($exception)) {
			throw $exception;
		}

		return $result;
	}


	public function transport($request, $location, $action, $version, $oneWay = 0) {
		$response = SoapClient::__doRequest($request, $location, $action, $version, $oneWay);
		return $response;
	}

	public function __getLastResponseHttpHeaders() {
		$headers = call_user_func(array('SoapClient', '__getLastResponseHttpHeaders'));
		return $headers;
	}
}

