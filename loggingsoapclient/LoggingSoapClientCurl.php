<?php

require_once(__DIR__ . '/LoggingSoapClientBase.php');

class LoggingSoapClientCurl extends LoggingSoapClientBase {
	protected function transport($request, $location, $action, $version, $oneWay) {
		$curlHandle = $this->transportCurlSetup($request, $location, $action);
		$rawResponse = curl_exec($curlHandle);
		$response = $this->processTextResponse($rawResponse, true);
		
		$this->lastResponseHttpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		$this->lastResposneHeaders = $response['headers'];
		return $response['body'];
	}


	protected function transportCurlSetup($request, $location, $action) {
		$headers = $this->makeHttpHeaders($request, $location, $action);
		$curlHandle = curl_init($location);
		$curlOpts = array(
			/* "This option is not thread-safe and is enabled by default"
			 * (php.net) */
			CURLOPT_DNS_USE_GLOBAL_CACHE => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => $request,
			CURLOPT_ENCODING => '',  // Sets to all supported types
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,  // Get response header as well
		);

		if ($this->timeoutConnect) {
			$curlOpts[CURLOPT_CONNECTTIMEOUT] = $this->timeoutConnect;
		}

		if ($this->timeoutSocket) {
			$curlOpts[CURLOPT_TIMEOUT] = $this->timeoutSocket;
		}
		
		curl_setopt_array($curlHandle, $curlOpts);
		return $curlHandle;
	}
}

