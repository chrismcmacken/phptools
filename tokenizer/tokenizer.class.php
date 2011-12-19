<?php

/*
 Copyright (c) 2011 individual committers of the code
 
 Permission is hereby granted, free of charge, to any person obtaining a
 copy of this software and associated documentation files (the "Software"),
 to deal in the Software without restriction, including without limitation
 the rights to use, copy, modify, merge, publish, distribute, sublicense,
 and/or sell copies of the Software, and to permit persons to whom the
 Software is furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 DEALINGS IN THE SOFTWARE.
 
 Except as contained in this notice, the name(s) of the above copyright
 holders shall not be used in advertising or otherwise to promote the sale,
 use or other dealings in this Software without prior written authorization.
 
 The end-user documentation included with the redistribution, if any, must 
 include the following acknowledgment: "This product includes software
 developed by contributors", in the same place and form as other third-party
 acknowledgments. Alternately, this acknowledgment may appear in the software
 itself, in the same form and location as other such third-party
 acknowledgments.
 */
 
/**
 * Create a consistent way to tokenize a file and to navigate those tokens.
 *
 * PHP's tokenizer does not tokenize everything.  Plus it uses one Hebrew
 * token name instead of consistently using English for everything.  This
 * tokenizer class helps make everything the same.
 *
 * Standardized tokens all look like this:
 *
 *   array(
 *     [0] => T_TOKEN_CONSTANT,
 *     [1] => 'token content',
 *     [2] => 77,  // Line number
 *     [3] => 28,  // Matching token offset or null if no match
 *   )
 *
 * The matching token offset would give you the index of a '{' if you were
 * at a '}'.  Usually it will be the same as the key of the token.  Only for
 * special tokens that could have a match would it be different.
 *
 * This class uses arrays instead of potentially thousands of objects because
 * it needs to run really fast on extremely large files.  That's why you will
 * see a little duplicate code here.  I'd rather save a function call and
 * prefer to call isset() instead of $this->valid().
 */

class Tokenizer implements ArrayAccess, Iterator {
	const EXCEPTION_EXTENSION = 297671;
	const EXCEPTION_GETTOKEN = 635090;
	const EXCEPTION_OFFSETSET = 200469;
	const EXCEPTION_OFFSETUNSET = 277193;

	static protected $customTokens = array(
		// Eliminate the only Hebrew token
		T_PAAMAYIM_NEKUDOTAYIM => 'T_DOUBLE_COLON',

		// Default token and ones that don't match strings
		-1 => 'T_TOKENIZER_DEFAULT',
		-2 => 'T_TOKENIZER_BACKTICK_LEFT',
		-3 => 'T_TOKENIZER_BACKTICK_RIGHT',

		// Ones that don't come back as arrays
		'`' => 'T_TOKENIZER_BACKTICK',
		'~' => 'T_TOKENIZER_BITWISE_NOT',
		'^' => 'T_TOKENIZER_BITWISE_XOR',
		'!' => 'T_TOKENIZER_BOOLEAN_NEGATION',
		'{' => 'T_TOKENIZER_BRACE_LEFT',
		'}' => 'T_TOKENIZER_BRACE_RIGHT',
		'[' => 'T_TOKENIZER_BRACKET_LEFT',
		']' => 'T_TOKENIZER_BRACKET_RIGHT',
		':' => 'T_TOKENIZER_COLON',
		',' => 'T_TOKENIZER_COMMA',
		'.' => 'T_TOKENIZER_CONCAT',
		'"' => 'T_TOKENIZER_DOUBLE_QUOTE',
		'=' => 'T_TOKENIZER_EQUAL',
		'>' => 'T_TOKENIZER_IS_GREATER_THAN',
		'<' => 'T_TOKENIZER_IS_LESS_THAN',
		'/' => 'T_TOKENIZER_MATH_DIVIDE',
		'-' => 'T_TOKENIZER_MATH_MINUS',
		'*' => 'T_TOKENIZER_MATH_MULTIPLY',
		'%' => 'T_TOKENIZER_MATH_MODULUS',
		'+' => 'T_TOKENIZER_MATH_PLUS',
		'(' => 'T_TOKENIZER_PAREN_LEFT',
		')' => 'T_TOKENIZER_PAREN_RIGHT',
		'&' => 'T_TOKENIZER_REFERENCE',
		';' => 'T_TOKENIZER_SEMICOLON',
		'@' => 'T_TOKENIZER_SUPPRESS_ERRORS',
		'?' => 'T_TOKENIZER_TERNARY',
		'$' => 'T_TOKENIZER_VARIABLE_VARIABLE',

		// T_STRING substitutions into tokens
		'define' => 'T_TOKENIZER_DEFINE',
		'false' => 'T_TOKENIZER_FALSE',
		'true' => 'T_TOKENIZER_TRUE',
	);

	protected $currentToken = 0;
	protected $isValid = true;
	protected $tokens = array();
	static protected $unknownTokenName = null;


	/**
	 * Create a Tokenizer object from a list of tokens
	 *
	 * @param array $tokens Token list from token_get_all()
	 */
	protected function __construct($tokens) {
		$this->tokens = $this->standardizeTokens($tokens);
		$this->currentToken = 0;
		$this->isValid = true;
	}


	/**
	 * Returns the value at the current index (the current token)
	 *
	 * Used by the Iterator interface
	 */
	public function current() {
		if (isset($this->tokens[$this->currentToken])) {
			return $this->tokens[$this->currentToken];
		}

		return null;
	}


	/**
	 * Finds instances of tokens, calling the callback with a Tokenizer
	 * object initialized to that position.
	 *
	 * @param mixed $tokenList T_* constant or array of T_* constants
	 * @param function $callback Callback, taking a single parameter, optional
	 * @return array Indices of where that token was found
	 */
	public function findTokens($tokenList, $callback = null) {
		$tokenList = (array) $tokenList;
		$indices = array();

		foreach ($this->tokens as $k => $v) {
			if (in_array($v[0], $tokenList)) {
				$indices[] = $k;
			}
		}

		if (! is_null($callback)) {
			$oldPosition = $this->currentToken;

			foreach ($indices as $index) {
				$this->currentToken = $index;
				$callback($this);
			}

			$this->currentToken = $oldPosition;
		}

		return $indices;
	}


	/**
	 * Gets the line number for a token passed in
	 *
	 * @param array $token
	 * @return integer
	 */
	public function getLine($token) {
		return $token[2];
	}


	/**
	 * Gets the match for a token passed in.  If there is no need for
	 * a match, return null.  If no match was found, return false.
	 *
	 * @param array $token
	 * @return integer|null|false
	 */
	public function getMatch($token) {
		if (! array_key_exists(3, $token)) {
			return null;
		}

		return $token[3];
	}


	/**
	 * Gets the name for a token or for the token at an offset
	 *
	 * @param mixed $token Token array or token constant
	 * @return string|null
	 */
	public function getName($token) {
		if (is_array($token)) {
			$token = $token[0];
		}

		if (! empty(static::$customTokens[$token])) {
			return static::$customTokens[$token];
		}

		$name = token_name($token);

		if ($name === static::$unknownTokenName) {
			$name = 'UNKNOWN(' . $token . ')';
		}

		return $name;
	}


	/**
	 * Get the name of the token at the specified index
	 *
	 * @param integer $index Array key (direct access)
	 * @return string|null Null if no token at index
	 */
	public function getNameAt($index) {
		$token = $this->getTokenAt($index);

		if (is_null($token)) {
			return null;
		}

		return $this->getName($token);
	}


	/**
	 * Get the name of the token relative to the current token
	 *
	 * @param integer $offset Amount to jump away from current pointer
	 * @return string|null Null if no token at index
	 */
	public function getNameRelative($offset) {
		$token = $this->getTokenRelative($offset);

		if (is_null($token)) {
			return null;
		}

		return $this->getName($token);
	}


	/**
	 * Increment our index and return the new current token, skipping ones
	 * that are not important
	 *
	 * @return array|null Null if no more tokens
	 */
	public function getNextImportantToken() {
		$this->currentToken ++;
		$token = $this->getTokenAt($this->currentToken);

		while (! is_null($token) && ! $this->isImportant($token)) {
			$this->currentToken ++;
			$token = $this->getTokenAt($this->currentToken);
		}

		return $token;
	}


	/**
	 * Increment our index and return the new current token
	 *
	 * @return array|null Null if no more tokens
	 */
	public function getNextToken() {
		$this->currentToken ++;
		return $this->getTokenAt($this->currentToken);
	}


	/**
	 * Increment our index and return the new current token, skipping ones
	 * that are not important
	 *
	 * @return array|null Null if no more tokens
	 */
	public function getPreviousImportantToken() {
		$this->currentToken --;
		$token = $this->getTokenAt($this->currentToken);

		while (! is_null($token) && ! $this->isImportant($token)) {
			$this->currentToken --;
			$token = $this->getTokenAt($this->currentToken);
		}

		return $token;
	}


	/**
	 * Move the current index backwards and get the previous token
	 *
	 * @return array|null Null if there isn't a token
	 */
	public function getPreviousToken() {
		$this->currentToken --;
		return $this->getTokenAt($this->currentToken);
	}


	/**
	 * Gets a token at a specified index
	 *
	 * @param integer $index Array index of token
	 * @return array|null Null if not found
	 */
	public function getTokenAt($index) {
		if (! isset($this->tokens[$index])) {
			return null;
		}

		$token = $this->tokens[$index];
		return $token;
	}


	/**
	 * Gets a token relative to the current pointer
	 *
	 * @param integer $offset Offset from current token
	 * @return array|null Null if no token at specified offset
	 */
	public function getTokenRelative($offset) {
		$index = $this->currentToken + (integer) $offset;

		if (! isset($this->tokens[$index])) {
			return null;
		}

		$token = $this->tokens[$index];
		return $token;
	}


	/**
	 * Returns the key at the current index (the current token)
	 *
	 * Used by the Iterator interface
	 */
	public function key() {
		return $this->currentToken;
	}


	static public function initialize() {
		// Check that tokenizer is loaded
		if (! extension_loaded('tokenizer')) {
			throw new Exception('Missing the tokenizer extension', self::EXCEPTION_EXTENSION);
		}

		foreach (static::$customTokens as $value => $name) {
			if (! defined($name)) {
				define($name, $value);
			}
		}

		/* Set the actual unknown token name in case it's different
		 * due to version changes or localization.
		 */
		static::$unknownTokenName = token_name(T_TOKENIZER_DEFAULT);
	}


	/**
	 * Returns true if the current token is not one of the following:
	 *
	 * T_COMMENT
	 * T_DOC_COMMENT
	 * T_WHITESPACE
	 *
	 * @param array|null|mixed $token Token array, constant or null for current
	 * @return boolean True if this token "counts" for processing
	 */
	public function isImportant($token = null) {
		if (is_null($token)) {
			$token = $this->tokens[$this->currentToken][0];
		}

		if (is_array($token)) {
			$token = $token[0];
		}

		if ($token == T_COMMENT || $token == T_DOC_COMMENT || $token == T_WHITESPACE) {
			return false;
		}

		return true;
	}


	/**
	 * Returns true if the tokenizer class found no problems loading the file.
	 * Keep in mind that this doesn't check very much right now.  This flag
	 * does NOT mean the PHP is syntatically correct.
	 *
	 * @return boolean;
	 */
	public function isValid() {
		return $this->isValid;
	}


	/**
	 * Move to the next token in the list
	 *
	 * Used by the Iterator interface
	 */
	public function next() {
		$this->currentToken ++;
	}


	/**
	 * Return true if the specified offset exists
	 *
	 * Used by the ArrayAccess interface
	 */
	public function offsetExists($offset) {
		return isset($this->tokens[$offset]);
	}


	/**
	 * Return the value at a given offset
	 *
	 * Used by the ArrayAccess interface
	 */
	public function offsetGet($offset) {
		if (isset($this->tokens[$offset])) {
			return $this->tokens[$offset];
		}

		return null;
	}


	/**
	 * Sets a given offset to a particular value - not allowed
	 *
	 * This class is read-only.
	 *
	 * Used by the ArrayAccess interface
	 *
	 * @throws ErrorException Every Time
	 */
	public function offsetSet($offset, $value) {
		throw new ErrorException('Tokenizer objects are read only', static::EXCEOPTION_OFFSETSET);
	}


	/**
	 * Unsets a given offset - not allowed
	 *
	 * This class is read-only.
	 *
	 * Used by the ArrayAccess interface
	 *
	 * @throws ErrorException Every Time
	 */
	public function offsetUnset($offset) {
		throw new ErrorException('Tokenizer objects are read only', static::EXCEOPTION_OFFSETUNSET);
	}


	/**
	 * Reset the internal pointer to the beginning
	 *
	 * Used by the Iterator interface
	 */
	public function rewind() {
		$this->currentToken = 0;
	}


	/**
	 * Convert a list of tokens fromm token_get_all() into a list of
	 * standardized tokens.  A description of a standardized token
	 * is at the top of this class.
	 *
	 * @param array $tokens Token list from token_get_all()
	 * @return array Standardized list of tokens
	 */
	protected function standardizeTokens($tokens) {
		$lastLineNumber = 1;
		$matchStack = array();
		$backtickIsLeft = true;

		foreach ($tokens as $key => $token) {
			if (! is_array($token)) {
				// Convert to array
				$tokenValue = $token;

				if (! isset(static::$customTokens[$tokenValue])) {
					$tokenValue = T_TOKENIZER_DEFAULT;
				}

				$token = array(
					$tokenValue,
					$token,
					$lastLineNumber
				);
				$tokens[$key] = $token;  // Save back to original array
			} elseif (T_STRING === $token[0]) {
				// Convert some strings into tokens
				$lowercase = strtolower($token[1]);

				if (isset(self::$customTokens[$lowercase])) {
					$token[0] = $lowercase;
					$tokens[$key] = $token;  // Save back to original array
				}
			}

			if ($token[0] == T_TOKENIZER_BACKTICK) {
				if ($backtickIsLeft) {
					$tokens[$key][0] = T_TOKENIZER_BACKTICK_LEFT;
				} else {
					$tokens[$key][0] = T_TOKENIZER_BACKTICK_RIGHT;
				}

				$token[0] = $tokens[$key][0];
				$backtickIsLeft = ! $backtickIsLeft;
			}

			$matchOpen = function ($find) use (&$tokens, $key, &$matchStack) {
				$tokens[$key][3] = false;
				$matchStack[] = array($find, $key);
			};

			switch ($token[0]) {
				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_TOKENIZER_BRACE_LEFT:
					$tokens[$key][3] = false;
					$matchStack[] = array(T_TOKENIZER_BRACE_RIGHT, $key);
					break;

				case T_OPEN_TAG:
				case T_OPEN_TAG_WITH_ECHO:
					$tokens[$key][3] = false;
					$matchStack[] = array(T_CLOSE_TAG, $key);
					break;

				case T_TOKENIZER_BACKTICK_LEFT:
					$tokens[$key][3] = false;
					$matchStack[] = array(T_TOKENIZER_BACKTICK_RIGHT, $key);
					break;

				case T_TOKENIZER_BRACKET_LEFT:
					$tokens[$key][3] = false;
					$matchStack[] = array(T_TOKENIZER_BRACKET_RIGHT, $key);
					break;

				case T_TOKENIZER_PAREN_LEFT:
					$tokens[$key][3] = false;
					$matchStack[] = array(T_TOKENIZER_PAREN_RIGHT, $key);
					break;

				case T_CLOSE_TAG:
				case T_TOKENIZER_BACKTICK_RIGHT:
				case T_TOKENIZER_BRACE_RIGHT:
				case T_TOKENIZER_BRACKET_RIGHT:
				case T_TOKENIZER_PAREN_RIGHT:
					if ($this->isValid) {
						$match = array_pop($matchStack);
						if ($token[0] != $match[0]) {
							$tokens[$key][3] = false;
							$this->isValid = false;
						} else {
							$tokens[$key][3] = $match[1];
							$tokens[$match[1]][3] = $key;
						}
					} else {
						$tokens[$key][3] = false;
					}
					break;

				default:
			}

			$lastLineNumber = $token[2] + substr_count($token[1], PHP_EOL);
		}

		return $tokens;
	}


	/**
	 * Tokenize a file
	 *
	 * @param string $filename
	 * @return Tokenizer
	 */
	static public function tokenizeFile($filename) {
		$contents = @file_get_contents($filename);
		$tokenizer = static::tokenizeString($contents);
		return $tokenizer;
	}


	/**
	 * Tokenize a string
	 *
	 * @param string $str String to tokenize
	 * @return Tokenizer
	 */
	static public function tokenizeString($str) {
		$tokens = token_get_all($str);
		$tokenizer = new Tokenizer($tokens);
		return $tokenizer;
	}


	/**
	 * Returns true if the currentToken index exists
	 *
	 * Used by the Iterator interface
	 */
	public function valid() {
		return isset($this->tokens[$this->currentToken]);
	}
}

Tokenizer::initialize();
