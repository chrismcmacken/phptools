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

require_once('tokenizer.class.php');

class TokenizerTest extends PHPUnit_Framework_TestCase {
	/**
	 * Finds all test files (*.php and *.txt) in a subdirectory
	 *
	 * @param string $dir Directory name
	 * @return array
	 */
	public function getTestsFromDirectory($dir) {
		$files = glob($dir . '/*_php');
		$tests = array();

		foreach ($files as $inputFile) {
			$outputFile = $inputFile;
			$outputFile = substr($outputFile, 0, -4);
			$outputFile .= '.txt';
			$test = array(
				$inputFile,
				$outputFile,
			);
			$tests[] = $test;
		}

		return $tests;
	}


	/**
	 * Find the various test files
	 *
	 * @return array
	 */
	public function dataTokenize() {
		return $this->getTestsFromDirectory('tests_tokenize');
	}


	/**
	 * Run the test against an external PHP file and the external file
	 * which describes the list of expected tokens
	 *
	 * @dataProvider dataTokenize
	 * @param string $inputFile
	 * @param string $tokenFile
	 */
	public function testTokenize($inputFile, $tokenFile) {
		$this->assertTrue(file_exists($inputFile), 'Input file missing: ' . $inputFile);
		$this->assertTrue(file_exists($tokenFile), 'Token file missing: ' . $tokenFile);
		$tokenizer = Tokenizer::tokenizeFile($inputFile);
		$expected = file_get_contents($tokenFile);
		$actual = '';

		foreach ($tokenizer as $token) {
			$line = array(
				$token->name,
				$token->line,
			);
			$match = $token->match;

			if (! is_null($match)) {
				if ($match === false) {
					$match = 'false';
				}
				$line[] = $match;
			}

			$actual .= implode(' ', $line) . "\n";
		}

		$this->assertEquals($expected, $actual);
	}


	/**
	 * Get a list of files for testing against the token finder
	 *
	 * @return array
	 */
	public function dataFind() {
		return $this->getTestsFromDirectory('tests_find');
	}

	/**
	 * Run tokenizer against external PHP file then compare the finder
	 * results against the expected results from the text file
	 *
	 * Text file format:
	 *
	 * T_TOKEN_CONSTANT index1 index2 index3
	 *
	 * @dataProvider dataFind
	 * @param string $inputFile
	 * @param string $expectedFile
	 */
	public function testFind($inputFile, $expectedFile) {
		$this->assertTrue(file_exists($inputFile), 'Input file missing: ' . $inputFile);
		$this->assertTrue(file_exists($expectedFile), 'Expected results file missing: ' . $expectedFile);
		$tokenizer = Tokenizer::tokenizeFile($inputFile);
		$expected = file($expectedFile);

		foreach ($expected as $line) {
			$line = trim($line);
			$expectedIndices = explode(' ', $line);
			$tokenConstantString = array_shift($expectedIndices);
			$tokenConstants = explode('|', $tokenConstantString);

			foreach ($tokenConstants as $k => $v) {
				$tokenConstants[$k] = constant($v);
				$this->assertNotNull($tokenConstants[$k], 'Unable to find constant for string: ' . $tokenConstantString);
			}

			$result = $tokenizer->findTokens($tokenConstants);
			$this->assertEquals($expectedIndices, $result);
		}
	}
}
