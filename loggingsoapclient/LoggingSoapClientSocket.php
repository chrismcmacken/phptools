<?php

/**
 * Use a socket directly
 */

require_once(__DIR__ . '/LoggingSoapClientBase.php');

class LoggingSoapClientSocket extends LoggingSoapClientBase {
	protected function transportSocket($request, $location, $action, $version, $oneWay) {
		$socket = $this->transportSocketRequest($request, $location, $action);
		$response = '';
		$stop = null;

		if ($this->timeoutSocket) {
			stream_set_blocking($socket, false);
			$stop = microtime(true) + $this->timeoutSocket;
		}

		while (! feof($socket)) {
			$response .= fread($socket, 2000);

			if ($stop && microtime(true) > $stop) {
				$this->logCallback("Timeout");
				return '';
			}
		}

		fclose($socket);
		$response = $this->processTextResponse($response);
		$this->lastResponseHttpCode = $response['statusCode'];
		$this->lastResponseHttpHeaders = $response['headers'];
		return $response['body'];
	}


	protected function transportSocketRequest($request, $location, $action) {
		$urlParts = parse_url($location);
		$host = $urlParts['host'];
		$httpReq = "POST $location HTTP/" . $this->httpVersion . "\r\n";
		$headers = $this->makeHttpHeaders($request, $location, $action);
		$httpReq .= implode("\r\n", $headers) . "\r\n";
		$httpReq .= "\r\n";
		$httpReq .= $request;

		if (! $urlParts['port']) {
			if ($urlParts['scheme'] == 'https') {
				$port = 443;
				$host = 'ssl://' . $host;
			} else {
				$urlParts['port'] = 80;
			}
		}

		$errNo = null;
		$errStr = null;
		$socket = fsockopen($host, $urlParts['port'], $errNo, $errStr, $this->timeoutConnection);
		fwrite($socket, $httpReq);
		return $socket;
	}
}

