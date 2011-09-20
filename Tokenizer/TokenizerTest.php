<?PHP
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

require_once('Tokenizer.class.php');

class TokenizerTest extends PHPUnit_Framework_TestCase {
	/**
	 * Find the various test files
	 *
	 * @return array
	 */
	public function dataTokenizer() {
		$files = glob('tests/*.php');
		$tests = array();

		foreach ($files as $inputFile) {
			$outputFile = $inputFile;
			$outputFile = substr($outputFile, 0, -3);
			$outputFile .= 'txt';
			$test = array(
				$inputFile,
				$outputFile,
			);
			$tests[] = $test;
		}

		return $tests;
	}


	/**
	 * Run the test against an external PHP file and the external file
	 * which describes the list of expected tokens
	 *
	 * @dataProvider dataTokenizer
	 * @param string $inputFile
	 * @param string $tokenFile
	 */
	public function testTokenizer($inputFile, $tokenFile) {
		$this->assertTrue(file_exists($inputFile), 'Input file missing: ' . $inputFile);
		$this->assertTrue(file_exists($tokenFile), 'Token file missing: ' . $tokenFile);
		$tokenizer = Tokenizer::tokenizeFile($inputFile);
		$expected = file_get_contents($tokenFile);
		$actual = '';

		foreach ($tokenizer as $key => $token) {
			$match = $token[3];

			if (is_null($match)) {
				$match = 'null';
			}

			$line = array(
				$tokenizer->getName($token),
				// Skip content since it could contain newlines
				$token[2],  // line number
				$match,  // match
			);
			$actual .= implode(' ', $line) . "\n";
		}

		$this->assertEquals($expected, $actual);
	}
}
