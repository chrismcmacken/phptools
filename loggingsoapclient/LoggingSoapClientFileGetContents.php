<?php

require_once(__DIR__ . '/LoggingSoapClientBase.php');

class LoggingSoapClientFileGetContents extends LoggingSoapClientBase {
	protected function transport($request, $location, $action, $version, $oneWay) {
		$options = array(
			'http' => array(
				'method' => 'POST',
				'protocol_version' => $this->httpVersion,
				'content' => $request,
				'header' => $this->makeHttpHeaders($request, $location, $action)
			)
		);

		if ($this->timeoutSocket) {
			$options['http']['timeout'] = $this->timeoutSocket;
		}

		$context = stream_context_create($options);
		$errorMessage = '';

		if ($this->timeoutSocket) {
			$originalTimeout = ini_set('default_socket_timeout', $this->timeoutSocket);
		}

		try {
			$contents = file_get_contents($location, false, $context);

			if (! empty($http_response_header)) {
				$this->lastResponseHeaders = implode("\r\n", $http_response_header);
			} else {
				$this->lastResposneHeaders = '';
			}
		} catch (Exception $e) {
			$this->logCallback('Transport Error: ' . $e->getMessage());
			$contents = '';
		}

		if ($this->timeoutSocket) {
			ini_set('default_socket_timeout', $originalTimeout);
		}

		return $contents;
	}
}

