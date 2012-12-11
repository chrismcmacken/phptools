<?php

/*
 Copyright (c) 2012 individual committers of the code
 
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
 * Make a small object for tokens so people don't need to remember which
 * key in the array is the line number.
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

class TokenizerToken {
	const EXCEPTION_PROPERTY = 608027;  // Random number, but unchanging
	protected $type;  // Token constant
	protected $content;  // Content from PHP source file
	protected $line;  // Line number
	protected $match;  // Matching token's index
	static protected $backtickIsLeft = true;
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
	static protected $unknownTokenName = null;


	/**
	 * Create a Tokenizer object from a list of tokens
	 *
	 * @param integer|string $type Integer token constant or string of content
	 * @param string $content Content of token
	 * @param integer $line Line number
	 */
	protected function __construct($type, $content, $line) {
		$this->type = $type;
		$this->content = $content;
		$this->line = $line;
		$this->match = null;  // No match needed
	}


	/**
	 * Get any property via magic
	 */
	public function __get($name) {
		switch ($name) {
			case 'type':
			case 'content':
			case 'line':
			case 'match':
				return $this->$name;

			case 'important':
				return $this->isImportant();

			case 'name':
				return $this->getName();
		}

		throw new InvalidArgumentException($name . ' is not a property you can access', static::EXCEPTION_PROPERTY);
	}


	/**
	 * Returns a token as a string
	 *
	 * return string
	 */
	public function __toString() {
		$str = $this->getName() . ' on line ' . $this->line;
		return $str;
	}


	/**
	 * Convert one element from the output from tokens_get_all() into a
	 * TokenizerToken object.
	 *
	 * @param array|string $data
	 * @param null|object $previousToken
	 * @return TokenizerToken
	 */
	static public function create($data, $previousToken) {
		if (is_array($data)) {
			// Some strings get converted into tokens
			if (T_STRING === $data[0]) {
				$lowercase = strtolower($data[1]);

				if (isset(static::$customTokens[$lowercase])) {
					$data[0] = $lowercase;
				}
			}

			return new TokenizerToken($data[0], $data[1], $data[2]);
		}

		if (! $previousToken) {
			$previousToken = new TokenizerToken(T_TOKENIZER_DEFAULT, '', 1);
		}

		$tokenType = $data;

		if (! isset(static::$customTokens[$tokenType])) {
			$tokenType = T_TOKENIZER_DEFAULT;
		} elseif (T_TOKENIZER_BACKTICK == $tokenType) {
			// Check for left/right backticks
			if (static::$backtickIsLeft) {
				$tokenType = T_TOKENIZER_BACKTICK_LEFT;
			} else {
				$tokenType = T_TOKENIZER_BACKTICK_RIGHT;
			}

			static::$backtickIsLeft = ! static::$backtickIsLeft;
		}

		$lastLine = $previousToken->line + substr_count($previousToken->content, PHP_EOL);

		return new TokenizerToken($tokenType, $data, $lastLine);
	}


	/**
	 * Returns the line number for this token
	 *
	 * @return integer
	 */
	public function getLine() {
		return $this->line;
	}


	/**
	 * Gets the name of the token or UNKNOWN(xyz) if the token is unknown.
	 * The xyz is replaced with the string or number representing the token
	 * type.
	 *
	 * @return string
	 */
	public function getName() {
		$type = $this->type;

		if (! empty(static::$customTokens[$type])) {
			return static::$customTokens[$type];
		}

		$name = token_name($type);

		if ($name === static::$unknownTokenName) {
			$name = 'UNKNOWN(' . $type. ')';
		}

		return $name;
	}


	/**
	 * Returns the type of the token.  For built-in tokens, this is an
	 * integer.  For custom ones, this is a string.
	 *
	 * @return string|integer
	 */
	public function getType() {
		return $this->type;
	}

	
	/**
	 * Set up constants
	 */
	static public function initialize() {
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
	 * @return boolean True if this token "counts" for processing
	 */
	public function isImportant() {
		switch ($this->type) {
			case T_COMMENT:
			case T_DOC_COMMENT:
			case T_WHITESPACE:
				return false;
		}

		return true;
	}


	/**
	 * Return true if this token is of the specified type.  If an array
	 * is passed, will return true if this token is any of the specified
	 * types.
	 *
	 * These are all equal
	 *    $this->isType(T_COMMENT) || $this->isType(T_DOC_COMMENT)
	 *    $this->isType(array(T_COMMENT, T_DOC_COMMENT))
	 *    $this->isType(T_COMMENT, T_DOC_COMMENT)
	 *
	 * @param mixed|array Any token constant or array of constants
	 * @return boolean
	 */
	public function isType($types) {
		if (! is_array($types)) {
			$types = func_get_args();
		}

		return in_array($this->type, $types);
	}


	/**
	 * Reset the tokenizer class between tokenization of files
	 *
	 * You don't need to call this directly - the Tokenizer class does this.
	 */
	static public function reset() {
		static::$backtickIsLeft = true;
	}


	/**
	 * Sets the matching index.  Not really a property of a token,
	 * but so handy and useful for the tokenizer.
	 *
	 * @param integer $match Index in Tokenizer
	 */
	public function setMatch($match) {
		$this->match = $match;
	}
}

TokenizerToken::initialize();
