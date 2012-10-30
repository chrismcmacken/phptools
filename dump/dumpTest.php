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

require_once('dump.class.php');

class DumpTest extends PHPUnit_Framework_TestCase {
	/**
	 * Returns the little chunk of HTML at the end of HTML formatted data
	 *
	 * @return string
	 */
	public function htmlEnd() {
		return "</div>\n";
	}


	/**
	 * Returns the HTML style tags, CSS, and JS header
	 *
	 * @return string
	 */
	public function htmlStart() {
		return <<< EOF
<style type="text/css">
.dump { text-align: left; white-space: pre; }
.dump_operator { color: #222222; }
.dump_count { text-decoration: none; }
.dump_name { font-weight: bold; }
.dump_false { color: red; }
.dump_true { color: green; }
.dump_integer { color: #777700; }
.dump_double { color: #777700; }
.dump_null {}
.dump_recursive { color: red; }
.dump_resource {}
.dump_string { color: #0000CC; }
.dump_unknown { font-weight: bold; }
.dump_key {}
.dump_object {}
.dump_object_public { color: #007700; }
.dump_object_protected { color: #0000CC; }
.dump_object_private { color: #CC0000; }
</style>
<script language="JavaScript">
function dumpToggle(o) {
	var one = o.nextSibling.style;
	var two = o.nextSibling.nextSibling.style;
	var temp = one.display;
	one.display = two.display;
	two.display = temp;
	return false;
}
</script>
<div class="dump">
EOF;
	}


	// object - static
	// object - private - $testFakeClass0
	// object - chain to parent - $testExtendedObject
	protected function makeFakeClasses() {
		if (class_exists('testFakeClass')) {
			return;
		}

		// Make a fake class that has a private and a static variable
		$fake = 'class testFakeClass { private static $PS = "Private Static"; protected static $S = "Static"; private $P = "Private"; }';
		eval($fake);

		// Extend another class that Dump handles to verify chaining works
		$extended = 'class testExtendedObject extends ArrayObject {}';
		eval($extended);
	}


	/**
	 * Wrap something in a span tag
	 *
	 * @param string $class
	 * @param string $content
	 * @param boolean $escape True if content should be escaped
	 * @return string
	 */
	public function span($class, $content, $escape = true) {
		if ($escape) {
			$content = htmlentities($content);
		}

		return '<span class="dump_' . $class . '">' . $content . '</span>';
	}


	/**
	 * Show the blank span that would be the "closed" version of an array
	 *
	 * @return string
	 */
	public function spanClosed() {
		return '<span style="display: none">&hellip;  </span>';
	}


	/**
	 * Make a span listing the count and does the toggle code if $count > 0
	 *
	 * @param integer $count
	 * @return string
	 */
	public function spanCount($count) {
		if ($count) {
			return '<a href="#" onclick="return dumpToggle(this)" class="dump_count">  /* ' . $count . ' */  </a><span>';
		}

		return $this->span('count', '  /* ' . $count . ' */  ');
	}


	/**
	 * Do a key span and an operator span for the key of an array
	 *
	 * @param name $key
	 * @param string $objectProtection Name for object
	 * @return string
	 */
	public function spanKey($key, $objectProtection = null) {
		if (! is_null($objectProtection)) {
			$out = $this->span('object_' . $objectProtection, $key);
		} else {
			$out = $this->span('key', $key);
		}

		return $out . $this->span('operator', ' => ');
	}


	/**
	 * Checks to make sure we are exporting what we expect
	 *
	 * @param mixed $data What to export
	 * @param string $expectedText Text format
	 * @param string $expectedHtml HTML format
	 * @dataProvider dataFunctionalExport
	 */
	public function testFunctionalExport($data, $expectedText, $expectedHtml) {
		$resultText = Dump::out($data)->asText()->returned();
		$this->assertEquals($expectedText . "\n", $resultText);
		$resultHtml = Dump::out($data)->asHtml()->returned();
		$this->assertEquals($this->htmlStart() . $expectedHtml . $this->htmlEnd(), $resultHtml);
	}

	public function dataFunctionalExport() {
		$this->makeFakeClasses();
		$testFakeClass0 = new testFakeClass();
		$testExtendedObject = new testExtendedObject();
		return array(
			'array, many' => array(
				array(
					1 => 2,
					'apple' => 'dumpling',
					9 => null,
					'G' => false
				),
				"array(\n\t1 => 2,\n\t'apple' => 'dumpling',\n\t9 => null,\n\t'G' => false\n)",
				$this->span('array', 'array(') . $this->spanCount(4) . "\n\t" . $this->spanKey('1') . $this->span('integer', '2') . ",\n\t" . $this->spanKey('\'apple\'') . $this->span('string', '\'dumpling\'') . ",\n\t" . $this->spanKey('9') . $this->span('null', 'null') . ",\n\t" . $this->spanKey('\'G\'') . $this->span('false', 'false') . "\n</span>" . $this->spanClosed() . $this->span('array', ')'),
			),

			'array, none' => array(
				array(),
				'array()',
				$this->span('array', 'array()'),
			),

			'boolean, false' => array(
				false,
				'false',
				$this->span('false', 'false'),
			),

			'boolean, true' => array(
				true,
				'true',
				$this->span('true', 'true'),
			),

			'double 0.0' => array(
				0.0,
				'0.0',
				$this->span('double', '0.0'),
			),

			// This one is tricky due to rounding issues
			'double 0.8' => array(
				0.1 + 0.7,
				'0.8',
				$this->span('double', '0.8'),
			),

			// Make sure we have a trailing decimal
			'double looks like int' => array(
				223344.0,
				'223344.0',
				$this->span('double', '223344.0'),
			),

			'integer' => array(
				234234,
				'234234',
				$this->span('integer', '234234'),
			),

			'null' => array(
				null,
				'null',
				$this->span('null', 'null'),
			),

			'object, Dump' => array(
				Dump::out()->display(false),
				"Dump::__set_state(array(\n\t'collapses' => array(),\n\t'displayFlag' => false,\n\t'htmlFlag' => null,\n\t'indent' => 0,\n\t'name' => null,\n\t'nameStack' => array(),\n\t'what' => null\n))",
				$this->span('object', 'Dump::__set_state(array(') . $this->spanCount(7) . "\n\t" . $this->spanKey('\'collapses\'', 'protected') . $this->span('array', 'array()') . ",\n\t" . $this->spanKey('\'displayFlag\'', 'protected') . $this->span('false', 'false') . ",\n\t" . $this->spanKey('\'htmlFlag\'', 'protected') . $this->span('null', 'null') . ",\n\t" . $this->spanKey('\'indent\'', 'protected') . $this->span('integer', '0') . ",\n\t" . $this->spanKey('\'name\'', 'protected') . $this->span('null', 'null') . ",\n\t" . $this->spanKey('\'nameStack\'', 'protected') . $this->span('array', 'array()') . ",\n\t" . $this->spanKey('\'what\'', 'protected') . $this->span('null', 'null') . "\n</span>" . $this->spanClosed() . $this->span('object', '))'),
			),

			'object, stdClass 0' => array(
				(object)array(),
				'(object) array()',
				$this->span('object', '(object) ') . $this->span('array', 'array()'),
			),

			'object, stdClass 1' => array(
				(object)array(
					'k' => 'v'
				),
				"(object) array(\n\t'k' => 'v'\n)",
				$this->span('object', '(object) ') . $this->span('array', 'array(') . $this->spanCount(1) . "\n\t" . $this->spankey('\'k\'') . $this->span('string', '\'v\'') . "\n</span>" . $this->spanClosed() . $this->span('array', ')'),
			),

			'object, testFakeClass' => array(
				$testFakeClass0,
				"testFakeClass::__set_state(array(\n\t'P' => 'Private',\n\t'PS' => 'Private Static',\n\t'S' => 'Static'\n))",
				$this->span('object', 'testFakeClass::__set_state(array(') . $this->spanCount(3) . "\n\t" . $this->spanKey('\'P\'', 'private') . $this->span('string', '\'Private\'') . ",\n\t" . $this->spanKey('\'PS\'', 'private') . $this->span('string', '\'Private Static\'') . ",\n\t" . $this->spanKey('\'S\'', 'protected') . $this->span('string', '\'Static\'') . "\n</span>" . $this->spanClosed() . $this->span('object', '))'),
			),

			'object, extended' => array(
				$testExtendedObject,
				"new testExtendedObject(array())",
				$this->span('object', 'new testExtendedObject(') . $this->span('array', 'array()') . $this->span('object', ')'),
			),

			'string, complex' => array(
				"VT\tNL\nCR\rBS\\Q\"A'D\$",
				"\"VT\\tNL\\nCR\\rBS\\\\Q\\\"A'D\\\$\"",
				$this->span('string', "\"VT\\tNL\\nCR\\rBS\\\\Q\\\"A'D\\\$\""),
			),

			'string, simple' => array(
				'string',
				'\'string\'',
				$this->span('string', '\'string\''),
			),
		);
	}

	/**
	 * @dataProvider dataFunctionalStringFormatting
	 * @param string $in Input string to format
	 * @param string $out Expected result as the secondary format of the string
	 */
	public function testFunctionalStringFormatting($in, $out) {
		$html = Dump::out($in)->asHtml()->returned();
		$result = preg_match('~\\<span style\\=\\"display\\: none\\"\\>(.*)\\<\\/span\\>~ms', $html, $matches);
		$this->assertTrue((boolean) $result, 'Did not find match in HTML: ' . $html);

		// Reformat the result back into text
		$string = $matches[1];
		$string = str_replace("&nbsp; &nbsp; &nbsp; &nbsp; ", "\t", $string);
		$string = str_replace("<br />\n", "\n", $string);
		$string = html_entity_decode($string);
		$this->assertEquals($out, $string, 'Specially formatted string does not match');
	}

	public function dataFunctionalStringFormatting() {
		return array(
			'sql, select simple' => array(
				'Select id from TableName',
				"SELECT id\nFROM TableName",
			),
			'sql, select with padding' => array(
				"\tselect    id\n\nfrom\n\t\rTableName   \r  \n",
				"SELECT id\nFROM TableName",
			),
			'xml, simple' => array(
				'<html><head></head><body></body></html>',
				"<?xml version=\"1.0\"?" . ">\n<html>\n  <head/>\n  <body/>\n</html>\n",
			),
		);
	}
}
