<?php
/*
 Copyright (c) 2011 individual committers of the code
 
 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
 
 Except as contained in this notice, the name(s) of the above copyright holders 
 shall not be used in advertising or otherwise to promote the sale, use or other
 dealings in this Software without prior written authorization.
 
 The end-user documentation included with the redistribution, if any, must 
 include the following acknowledgment: "This product includes software developed 
 by contributors", in the same place and form as other third-party
 acknowledgments. Alternately, this acknowledgment may appear in the software
 itself, in the same form and location as other such third-party
 acknowledgments.
 */

/**
 * Help keep the various database-specific things in check by using
 * fragments.
 */

class DB_Fragment {
	const ANY = 'any';
	const GT = 'gt';
	const GTE = 'gte';
	const LIKE = 'like';
	const LT = 'lt';
	const LTE = 'lte';
	const NOT = 'not';
	const NOW = 'now';

	protected $args;
	protected $type;


	/**
	 * Creates a new DB Fragment
	 *
	 * @param string $fragmentType See defined constants
	 * @param mixed $args Arguments to pass around - available via getArgs()
	 * @throws Exception Invalid fragment type
	 */
	public function __construct($fragmentType, $args = null) {
		switch ($fragmentType) {
			case DB_Fragment::ANY:
			case DB_Fragment::GT:
			case DB_Fragment::GTE:
			case DB_Fragment::LIKE:
			case DB_Fragment::LT:
			case DB_Fragment::LTE:
			case DB_Fragment::NOT:
			case DB_Fragment::NOW:
				// Valid
				break;

			default:
				throw new Exception('Invalid fragment type: ' . $fragmentType);
		}

		$this->type = $fragmentType;
		$this->args = $args;
	}


	/**
	 * Convert the fragment into a string.
	 *
	 * @return string
	 */
	public function __toString() {
		return __CLASS__ . '(' . $this->type . ')';
	}


	public function getArgs() {
		return $this->args;
	}


	public function getType() {
		return $this->type;
	}
}
