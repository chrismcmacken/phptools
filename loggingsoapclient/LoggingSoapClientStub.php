<?php

require_once(__DIR__ . '/LoggingSoapClientBase.php');

class LoggingSoapClientStub extends LoggingSoapClientBase {
	/**
	 * Configure this fake SOAP client to scan the right directory for
	 * the appropriate response for this request.
	 *
	 * Configuration array has the following keys:
	 *
	 *     path: string, path to scan for request/response files
	 *     swaps: array, keys are replacement patterns, values are swapped in
	 *
	 * For further explanation of the swaps, see convertSwapsToPatterns()
	 *
	 * @param string $location
	 * @param string $action
	 * @param string $version
	 * @param boolean $oneWay
	 * @return array Configuration
	 */
	protected function configureForRequest($request, $location, $action, $version) {
		// You should override this!
		return array(
			'path' => __DIR__ . '/' . $action,
			'swaps' => array()
		);
	}


	protected function formatXml($str) {
		$xml = new DOMDocument();
		$xml->preserveWhitespace = false;
		$xml->formatOutput = true;
		@$xml->loadXML($str);
		$result = @$xml->saveXML();
		return $result;
	}
	

	/**
	 * Convert an array of swap pattern/value into another set of
	 * patterns/replacements for use in modifying the response.
	 *
	 * $swaps is an array containing a pattern as the key and the
	 * desired data as the value.  This returns an array with a pattern
	 * as its key and the expected data in the response as the value.
	 *
	 * The incoming request is matched against the swap key.  If found,
	 * the request is modified (the replacement takes the place of the match).
	 * Also, when it is found, the pattern and the original value are
	 * put into the $patterns to be returned to the caller and later
	 * used in a similar way to modify the response.
	 *
	 * The returned value is an of patterns/response data
	 *
	 * @param string $request Request XML
	 * @param array $swaps Pattern / replacement
	 * @return array
	 */
	protected function convertSwapsToPatterns($request, $swaps) {
		$patterns = array();

		foreach ($swaps as $k => $v) {
			if (preg_match('/(' . $k . ')/Um', $request, $matches)) {
				$patterns[$k] = $matches[1];
			}
		}

		return $patterns;
	}



	protected function modifyTextWithPatterns($text, $swaps) {
		if (! is_array($swaps) || count($swaps) === 0) {
			return $text;
		}

		$patterns = array_keys($swaps);
		array_walk($patterns, function (&$pattern) {
			$pattern = '/' . $pattern . '/Um';
		});
		$replacements = array_values($swaps);
		$modified = preg_replace($patterns, $replacements, $text);
		return $modified;
	}


	/**
	 * No longer make any SOAP calls.  Only search the filesystem for
	 * matching requests and provide back the matching response.
	 *
	 * CAUTION:  When any errors happen, PHP has a tendency to do really
	 * bizarre things, especially when using a new overloader to do the
	 * stubbing (see the test_helpers extension).  Don't let errors happen.
	 *
	 * @param string $request Request payload - a bunch of XML
	 * @param string $location Endpoint URL
	 * @param string $action Action to call in SOAP request
	 * @param string $version
	 * @param boolean $oneWay If true, don't expect much of a response
	 */
	protected function transport($request, $location, $action, $version, $oneWay = 0) {
		$config = $this->configureForRequest($request, $location, $action, $version, $oneWay);
		$patterns = array();

		if (! empty($config['swaps'])) {
			$patterns = $this->convertSwapsToPatterns($request, $config['swaps']);
		}

		$modifiedRequest = $this->modifyTextWithPatterns($request, $config['swaps']);
		$response = $this->scanDirectory($config['path'], $modifiedRequest, $patterns);
		return $response;
	}


	protected function scanDirectory($dir, $request, $patterns) {
		$scanFiles = array();
		$request = trim($request);  // Extra whitespace makes matching tricky
		$misses = array();
		$requestXml = $this->formatXml($request);

		// Get a list of files and look for exact matches
		foreach (glob($dir . '/*_request.*') as $file) {
			$fileContents = file_get_contents($file);
			$outFile = str_replace('_request.', '_response.', $file);

			// Look for an exact match
			if (trim($fileContents) == $request) {
				$response = file_get_contents($outFile);
				$modifiedResponse = $this->modifyTextWithPatterns($response, $patterns);
				return $modifiedResponse;
			}

			if ($requestXml !== false) {
				$fileContentsXml = $this->formatXml($fileContents);

				if ($requestXml === $fileContentsXml) {
					$response = file_get_contents($outFile);
					$modifiedResponse = $this->modifyTextWithPatterns($response, $patterns);
					return $modifiedResponse;
				}
			}
		}

		return '';
	}
}

