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
 * The matching token offset would give you the index of a '{' if you were
 * at a '}'.  Usually it will be the same as the key of the token.  Only for
 * special tokens that could have a match would it be different.
 *
 * This class uses arrays instead of potentially thousands of objects because
 * it needs to run really fast on extremely large files.  That's why you will
 * see a little duplicate code here.  I'd rather save a function call and
 * prefer to call isset() instead of $this->valid().
 */

require_once(__DIR__ . '/tokenizertoken.class.php');

class Tokenizer implements ArrayAccess, Iterator {
	const EXCEPTION_EXTENSION = 297671;
	const EXCEPTION_GETTOKEN = 635090;
	const EXCEPTION_OFFSETSET = 200469;
	const EXCEPTION_OFFSETUNSET = 277193;

	protected $currentToken = 0;
	protected $isValid = true;
	protected $isValidReason = null;
	protected $filename = null;
	protected $tokens = array();
	protected $tokensByType = null;


	/**
	 * Create a Tokenizer object from a list of tokens
	 *
	 * @param array $tokens Token list from token_get_all()
	 */
	protected function __construct($tokens) {
		$this->currentToken = 0;
		$this->isValid = true;
		$this->isValidReason = null;
		$this->tokens = $this->standardizeTokens($tokens);
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
	 * Create an index of the tokens by type.  Calling this method will
	 * significantly speed up calls to findTokens().  If you plan on
	 * using findTokens() only once, then this call will waste a little
	 * bit of speed but it shouldn't be too noticeable.
	 */
	public function indexTokenTypes() {
		if (! is_null($this->tokensByType)) {
			return;
		}

		$tokensByType = array();
		
		foreach ($this->tokens as $k => $v) {
			$type = $v->getType();

			if (empty($tokensByType[$type])) {
				$tokensByType[$type] = array($k);
			} else {
				$tokensByType[$type][] = $k;
			}
		}

		$this->tokensByType = $tokensByType;
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

		if (is_null($this->tokensByType)) {
			foreach ($this->tokens as $k => $v) {
				if (in_array($v->getType(), $tokenList)) {
					$indices[] = $k;
				}
			}
		} else {
			foreach ($tokenList as $type) {
				if (! empty($this->tokensByType[$type])) {
					$indices = array_merge($indices, $this->tokensByType[$type]);
				}
			}

			sort($indices);
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
	 * Increment our index and return the new current token, skipping ones
	 * that are not important
	 *
	 * @return array|null Null if no more tokens
	 */
	public function getImportantTokenIndex($increment, $startAt = null) {
		if (is_null($startAt)) {
			$startAt = $this->currentToken;
		}

		$offset = $startAt + $increment;

		$token = $this->getTokenAt($offset);
		
		while (! is_null($token) && ! $token->isImportant()) {
			$offset += $increment;
			$token = $this->getTokenAt($offset);
		}

		if (is_null($token)) {
			return null;
		}

		return $offset;
	}


	/**
	 * Returns the name of the file that was tokenized, if any
	 *
	 * @return null|string filename
	 */
	public function getFilename() {
		return $this->filename;
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

		while (! is_null($token) && ! $token->isImportant()) {
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

		while (! is_null($token) && ! $token->isImportant()) {
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


	/**
	 * Make sure that the tokenizer functions exist
	 */
	static public function initialize() {
		if (! extension_loaded('tokenizer')) {
			throw new Exception('Missing the tokenizer extension', self::EXCEPTION_EXTENSION);
		}
	}


	/**
	 * Returns true if the tokenizer class found no problems loading the file.
	 * Keep in mind that this doesn't check very much right now.  This flag
	 * does NOT mean the PHP is syntatically correct.
	 *
	 * @return boolean
	 */
	public function isValid() {
		return $this->isValid;
	}


	/**
	 * Returns the first reason why the tokenizer thinks the code is not
	 * valid.  If the code looks valid, this returns null.
	 *
	 * @return string|null
	 */
	public function isValidReason() {
		return $this->isValidReason;
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
		if (array_key_exists($offset, $this->tokens)) {
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
	 * Go to a specific index
	 *
	 * @param integer $index
	 */
	public function setIndex($index) {
		$this->currentToken = (integer) $index;
	}


	/**
	 * Sets the reason that the tokenizer believes the code is invalid.
	 * Only keeps around the first reason.
	 *
	 * @param $string Reason
	 */
	protected function setReason($reason) {
		$this->isValid = false;
		if (is_null($this->isValidReason)) {
			$this->isValidReason = $reason;
		}
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
		$lastToken = null;
		$matchStack = array();
		$matchStackPhp = array();
		TokenizerToken::reset();
		$tokenObjects = array();
		$matchables = array(
			T_OPEN_TAG,
			T_OPEN_TAG_WITH_ECHO,
			T_CLOSE_TAG,
			T_CURLY_OPEN,
			T_DOLLAR_OPEN_CURLY_BRACES,
			T_TOKENIZER_BRACE_LEFT,
			T_TOKENIZER_BACKTICK_LEFT,
			T_TOKENIZER_BRACKET_LEFT,
			T_TOKENIZER_PAREN_LEFT,
			T_TOKENIZER_BACKTICK_RIGHT,
			T_TOKENIZER_BRACE_RIGHT,
			T_TOKENIZER_BRACKET_RIGHT,
			T_TOKENIZER_PAREN_RIGHT,
			T_BAD_CHARACTER,
		);

		foreach ($tokens as $key => $token) {
			$token = TokenizerToken::create($token, $lastToken);
			$tokenObjects[] = $token;
			$lastToken = $token;
			$tokenType = $token->type;

			if (in_array($tokenType, $matchables)) {
				switch ($tokenType) {
					case T_OPEN_TAG:
					case T_OPEN_TAG_WITH_ECHO:
						$token->setMatch(false);
						$matchStackPhp[] = array($key, $token);
						break;

					case T_CLOSE_TAG:
						if ($this->isValid) {
							if (! count($matchStackPhp)) {
								$token->setMatch(false);
								$this->setReason('Trying to match ' . $token . ' with nothing');
							} else {
								$match = array_pop($matchStackPhp);
								$token->setMatch($match[0]);
								$match[1]->setMatch($key);
							}
						} else {
							$token->setMatch(false);
						}
						break;

					case T_CURLY_OPEN:
					case T_DOLLAR_OPEN_CURLY_BRACES:
					case T_TOKENIZER_BRACE_LEFT:
						$token->setMatch(false);
						$matchStack[] = array(T_TOKENIZER_BRACE_RIGHT, $key, $token);
						break;

					case T_TOKENIZER_BACKTICK_LEFT:
						$token->setMatch(false);
						$matchStack[] = array(T_TOKENIZER_BACKTICK_RIGHT, $key, $token);
						break;

					case T_TOKENIZER_BRACKET_LEFT:
						$token->setMatch(false);
						$matchStack[] = array(T_TOKENIZER_BRACKET_RIGHT, $key, $token);
						break;

					case T_TOKENIZER_PAREN_LEFT:
						$token->setMatch(false);
						$matchStack[] = array(T_TOKENIZER_PAREN_RIGHT, $key, $token);
						break;

					case T_TOKENIZER_BACKTICK_RIGHT:
					case T_TOKENIZER_BRACE_RIGHT:
					case T_TOKENIZER_BRACKET_RIGHT:
					case T_TOKENIZER_PAREN_RIGHT:
						if ($this->isValid) {
							$match = array_pop($matchStack);

							if ($tokenType != $match[0]) {
								$token->setMatch(false);
								$this->setReason('Trying to match ' . $token . ' with ' . $match[2]);
							} else {
								$token->setMatch($match[1]);
								$match[2]->setMatch($key);
							}
						} else {
							$token->setMatch(false);
						}
						break;

					case T_BAD_CHARACTER:
						$this->setReason('T_BAD_CHARACTER encountered on line ' . $token->line);
						break;

					default:
				}
			}
		}

		if (count($matchStack)) {
			$this->setReason('Unmatched braces left on stack.  Last one was ' . $matchStack[0][2]);
		}

		// 1 open tag at the end is fine
		if (count($matchStackPhp) > 1) {
			$this->setReason('Two open PHP tags that were not closed');
		}

		return $tokenObjects;
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
		$tokenizer->filename = $filename;
		return $tokenizer;
	}


	/**
	 * Tokenize a string
	 *
	 * @param string $str String to tokenize
	 * @return Tokenizer
	 */
	static public function tokenizeString($str) {
		/* PHP sometimes warns with "Unexpected character in input"
		 * when tokenizing a file, especially when accidentally
		 * tokenizing images.
		 */
		$tokens = @token_get_all($str);
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
