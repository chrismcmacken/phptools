<?PHP

class WebResponse {
	static public $statusCodes = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing', // WebDAV
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information', // HTTP/1.1
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status', // WebDAV
		208 => 'Already Reported', // WebDAV
		226 => 'IM Used', // RFC 3229
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other', // HTTP/1.1
		304 => 'Not Modified',
		305 => 'Use Proxy', // HTTP/1.1
		306 => 'Switch Proxy', // HTTP/1.0, no longer used
		307 => 'Temporary Redirect', // HTTP/1.1
		308 => 'Permanent Redirect', // experimental RFC
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot', // RFC 2324
		420 => 'Enhance Your Calm', // Twitter
		422 => 'Unprocessable Entity', // WebDAV
		423 => 'Locked', // WebDAV
		424 => 'Failed Dependency', // WebDAV
		425 => 'Unordered Collection', // Internet draft
		426 => 'Upgrade Required', // RFC 2817
		428 => 'Precondition Required', // RFC 6585
		429 => 'Too Many Requests', // RFC 6585
		431 => 'Request Header Fields Too Large', // RFC 6585
		444 => 'No Response', // Nginx
		449 => 'Retry With', // Microsoft
		450 => 'Blocked by Windows Parental Controls', // Microsoft
		451 => 'Unavailable for Legal Reasons', // Internet draft - might also be "Redirect" per Microsoft
		494 => 'Request Header Too Large', // Nginx
		495 => 'Cert Error', // Nginx
		496 => 'No Cert', // Nginx
		497 => 'HTTP to HTTPS', // Nginx
		499 => 'Client Closed Request', // Nginx
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates', // RFC 2295
		507 => 'Insufficient Storage', // WebDAV; RFC 4918
		508 => 'Loop Detected', // WebDAV 5842
		509 => 'Bandwidth Limit Exceeded', // Apache bw/limited extension
		510 => 'Not Extended', // RFC 2774
		511 => 'Network Authentication Required', // RFC 6585
		598 => 'Network Read Timeout Error', // Microsoft
		599 => 'Network Connect Timeout Error', // Microsoft
	);

	static public function redirectAndExit($uri) {
		if (! headers_sent()) {
			header('Location: ' . $uri);
		}
		exit();
	}

	static public function showErrors() {
		error_reporting(E_ALL | E_STRICT | E_NOTICE);
		ini_set('display_errors', 'on');
		ini_set('display_startup_errors', 'on');
	}

	static public function status($codeOrString) {
		if (is_integer($codeOrString)) {
			if (! empty(static::$statusCodes[$codeOrString])) {
				$codeOrString = $codeOrString . ' ' . static::$statusCodes[$codeOrString];
			}
		}

		if (array_key_exists('SERVER_PROTOCOL', $_SERVER)) {
			$protocol = $_SERVER['SERVER_PROTOCOL'];
		} else {
			$protocol = 'HTTP/1.1';
		}

		header($protocol . ' ' . $codeOrString);
	}
}
