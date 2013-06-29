<?php

require_once(__DIR__ . '/LoggingSoapClientStub.php');

class LoggingSoapClientStubGenerator extends LoggingSoapClientStub {
	/**
	 * Whether or not we want to always generate a stub.
	 * 
	 * @var boolean
	 */
	protected $alwaysGenerate = false;
	
	
	/**
	 * Filename prefix to use.
	 * 
	 * @var string
	 */
	protected $filenamePrefix;
	
	
	/**
	 * Writes the request to the filesystem and logs a message.
	 * Does not modify the request since transport() does this for us.
	 * 
	 * @param string $request
	 * @param string $filename
	 * @return string
	 */
	protected function writeRequest($request, $filename) {
		$this->logCallback('Generating a request SOAP stub in: ' . $filename, $request);
		file_put_contents($filename, $request);
		return $request;
	}
	
	
	/**
	 * Writes the response to the filesystem and logs a message.
	 * 
	 * @param string $response
	 * @param string $filename
	 * @return string
	 */
	protected function writeResponse($response, $filename) {
		$this->logCallback('Generating a response SOAP stub in: ' . $filename, $response);
		file_put_contents($filename, $response);
		return $response;
	}
	
	
	/**
	 * Instructs the StubGenerator to always write a file.
	 * @param boolean $alwaysGenerate
	 * @return LoggingSoapClientStubGenerator
	 */
	public function setAlwaysGenerate($alwaysGenerate = true) {
		$this->alwaysGenerate = ! empty($alwaysGenerate);
		return $this;
	}
	
	
	/**
	 * Sets the filename prefix to use.
	 * @param string $prefix
	 * @return LoggingSoapClientStubGenerator
	 */
	public function setFilenamePrefix($filenamePrefix) {
		$this->filenamePrefix = $filenamePrefix;
		return $this;
	}
  
  
  	/**
  	 * Make the SOAP request if a matching stub does not exist.
	 *
	 * @param string $request Request payload - a bunch of XML
	 * @param string $location Endpoint URL
	 * @param string $action Action to call in SOAP request
	 * @param string $version
	 * @param boolean $oneWay If true, don't expect much of a response
	 * @return string
	 */
	protected function transport($request, $location, $action, $version, $oneWay = 0) {
		$config = $this->configureForRequest($request, $location, $action, $version, $oneWay);
		$patterns = array();
		
		if (! empty($config['swaps'])) {
			$patterns = $this->convertSwapsToPatterns($request, $config['swaps']);
		}
		
		$folder = $config['path'];
		$modifiedRequest = $this->modifyTextWithPatterns($request, $patterns);
		$modifiedResponse = '';
		
		$filenamePrefix = $this->filenamePrefix ? $this->filenamePrefix . '_' : '';
		$filenamePrefix = $folder . DIRECTORY_SEPARATOR . $filenamePrefix;
		$requestFilename = $filenamePrefix . 'request.xml';
		$responseFilename = $filenamePrefix . 'response.xml';
		$response = $this->scanDirectory($folder, $modifiedRequest, $patterns);
		
		if (! $response) {
			$response = parent::__doRequest($request, $location, $action, $version, $oneWay);
			$modifiedResponse = $this->modifyTextWithPatterns($response, $patterns);
		} elseif (! $this->alwaysGenerate) {
			$this->logCallback('A SOAP response stub already exists in: ' . $responseFilename, $response);
			$this->logCallback('No SOAP stubs were generated.');
			return $response;
		} else {
			// A response was found, but we want to overwrite it.
			$this->logCallback('SOAP stubs will be overwritten.');
		}
		
		$this->writeRequest($modifiedRequest, $requestFilename);
		$this->writeResponse($modifiedResponse, $responseFilename);
		return $modifiedResponse;
	}
}
