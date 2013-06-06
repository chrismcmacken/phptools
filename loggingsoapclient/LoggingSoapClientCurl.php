<?php

require_once(__DIR__ . '/LoggingSoapClientBase.php');

class LoggingSoapClientCurl extends LoggingSoapClientBase {

	protected function addCurlHeaders(array $headers) {
		$headers[] = 'Expect:';
		return $headers;
	}

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
		$headers = $this->addCurlHeaders($headers);
		$curlHandle = curl_init($location);
		$curlOpts = array(
			/* "This option is not thread-safe and is enabled by default"
			 * (php.net) */
			CURLOPT_DNS_USE_GLOBAL_CACHE => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => $request,
			CURLOPT_ENCODING => '',  // Sets to all supported types
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,  // Get response header as well
		);
		if ($this->timeoutConnection) {
			$curlOpts[CURLOPT_CONNECTTIMEOUT] = $this->timeoutConnection;
		}
		if ($this->timeoutSocket) {
			$curlOpts[CURLOPT_TIMEOUT] = $this->timeoutSocket;
		}

		$curlOpts[CURLOPT_SSL_VERIFYPEER] = ! $this->sslAllowSelfSigned;

		if(true !== $this->sslAllowSelfSigned) {
			if($this->sslCaPath) {
				$curlOpts[CURLOPT_CAPATH] = $this->sslCaPath;
			}
			if($this->sslCaFile) {
				$curlOpts[CURLOPT_CAINFO] = $this->sslCaPath . '/' . $this->sslCaFile;
			}
		}

		if($this->sslLocalCert) {
			$curlOpts[CURLOPT_SSLCERT] = $this->sslLocalCert;
		}
		curl_setopt_array($curlHandle, $curlOpts);
		return $curlHandle;
	}
}

