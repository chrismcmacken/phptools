<?php

/**
 * Copyright (c) <year> individual committers of the code

 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.

 * Except as contained in this notice, the name(s) of the above copyright holders
 * shall not be used in advertising or otherwise to promote the sale, use or other
 * dealings in this Software without prior written authorization.

 * The end-user documentation included with the redistribution, if any, must
 * include the following acknowledgment: "This product includes software developed
 * by contributors", in the same place and form as other third-party
 * acknowledgments. Alternately, this acknowledgment may appear in the software
 * itself, in the same form and location as other such third-party
 * acknowledgments.
 */


/**
 * A Log class to aid in debugging and event tracking.
 */
class Log {


	/**
	 * This variable is used to store a callback function that
	 * returns a reference to the DB object included in phptools.
	 * It's set via setDbObjectCallback(); It expects a callback of the same
	 * form as the call_user_func_array() function.
	 *
	 * @var string
	 */
	protected static $dbObjectCallback;


	/**
	 * Used to indicate if we're in a dev / debug
	 * environment
	 *
	 * @var bool
	 */
	protected static $debug = false;

	/**
	 * Constants for log type
	 */
	const DEBUG = 'DEBUG';
	const ERROR = 'ERROR';
	const INFO = 'INFO';
	const WARN = 'WARN';


	/**
	 * Logs a full debug_backtrace as long as
	 * debugging is turned on.
	 *
	 * @static
	 * @param $message
	 */
	public static function debug($message) {
		$backTrace = debug_backtrace(true);

		static::writeLog(static::DEBUG, $message, $backTrace);
	}


	/**
	 * Logging program errors
	 *
	 * @static
	 * @param $message
	 * @param null $detail
	 */
	public static function error($message, $detail = null) {
		static::writeLog(static::ERROR, $message, $detail);
	}


	/**
	 * for logging simple informational messages
	 *
	 * @static
	 * @param $message
	 * @param null $detail
	 */
	public static function info($message, $detail = null) {
		static::writeLog(static::INFO, $message, $detail);
	}


	/**
	 * Helper function to get the place that has called our log function
	 *
	 * @static
	 * @return string
	 */
	protected static function getCaller() {
		$trace = debug_backtrace(true);
		$called = end($trace);

		if(! isset($called['class'])) {
			//from a file, no class or function
			$source = $called['file'] . ":" . $called['line'];
		} elseif(isset($called['class']) && $called['class'] == __CLASS__) {
			$source = $called['file'] . ":" . $called['line'];
		} elseif(isset($called['class'], $called['type'], $called['function'])) {
			//from a class
			$source = $called['class'] . $called['type'] . $called['function'] . '()';
		} else {
			$source = "unknown:0";
		}

		return $source;
	}


	/**
	 * Set the callback that fetches the Db object for us
	 *
	 * @static
	 * @param $callback
	 * @throws Exception
	 */
	public static function setDbObjectCallback($callback) {
		if(! is_callable($callback)) {
			throw new Exception("The dbObjectCallback must be set to a callback function that is a valid callback and returns an instance of the DB object");
		}

		static::$dbObjectCallback = $callback;
	}


	/**
	 * Sets the debug flag
	 *
	 * @static
	 * @param bool $value
	 */
	public static function setDebug(boolean $value) {
		static::$debug = $value;
	}


	/**
	 * Log warning messages
	 *
	 * @static
	 * @param $message
	 * @param null $detail
	 */
	public static function warn($message, $detail = null) {
		static::writeLog(static::WARN, $message, $detail);
	}


	/**
	 * Helper function that logs the actual message. If it can't log a message to the
	 * database we trigger an error in the apache log.
	 *
	 * @static
	 * @param $type
	 * @param $message
	 * @param $detail
	 * @throws Exception
	 */
	protected static function writeLog($type, $message, $detail) {
		$db = call_user_func(static::$dbObjectCallback);

		if(! $db instanceof DB_Base) {
			throw new Exception("The database object returned by the dbObjectCallback must return an instance of the DB class");
		}

		//get caller
		$source = static::getCaller();

		$data = array(
			'type' => $type,
			'fileName' => $_SERVER['SCRIPT_NAME'],
			'uri' => $_SERVER['REQUEST_URI'],
			'message' => $message,
			'host' => $_SERVER['SERVER_NAME'],
			'source' => $source,
			'created' => time(),
		);

		if(null !== $detail) {
			$data['detail'] = Dump::out($detail)->returned();
		}

		$result = $db->insert('log', $data);

		if(false == $result) {
			trigger_error("Unable to write to the log table.", E_WARNING);
		}
	}
}