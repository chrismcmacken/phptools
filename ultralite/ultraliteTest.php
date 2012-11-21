<?PHP
/*
 Copyright (c) 2012 individual committers of the code
 
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

require_once('Ultralite.php');
require_once('Ultralitehtml.php');

class UltraliteTest extends PHPUnit_Framework_TestCase {
	/**
	 * Provide scenarios for testing Ultralite
	 *
	 * Returns an array containing arrays of parameters for testGeneric.
	 * [0] = class name
	 * [1] = variables (array)
	 * [2] = template filename
	 * [3] = expected output
	 *
	 * @return array
	 */
	public function dataGeneric() {
		return array(
			'simple' => array(
				'Ultralite',
				array(
					'a' => 7,
					'c' => '<p>'
				),
				'abc.tpl',
				"a 7\nb \nc <p>\n"
			),
			'simple-html' => array(
				'UltraliteHtml',
				array(
					'a' => 7,
					'c' => '<p>'
				),
				'abc.tpl',
				"a 7\nb \nc &lt;p&gt;\n"
			),
			'php_block' => array(
				'Ultralite',
				array(),
				'block.tpl',
				"test hi code\n"
			),
			'line' => array(
				'Ultralite',
				array(
					'a' => 'AAA',
					'b' => range(1, 5),
					'c' => true
				),
				'line.tpl',
				"left\na AAA\nb Array\nc 1\nright\n"
			),
			'error' => array(
				'Ultralite',
				array(),
				'error.tpl',
				'EXCEPTION: Use of undefined constant blah_blah - assumed \'blah_blah\' (file: error.tpl, line 2)'
			),
			'bad_include' => array(
				'Ultralite',
				array(),
				'bad_include.tpl',
				'EXCEPTION: Unable to find ' . __DIR__ . '/fixtures/bad'
			),
			'loop' => array(
				'Ultralite',
				array(
					'list' => range(1, 4)
				),
				'loop.tpl',
				"loop 1\nloop 2\nloop 3\nloop 4\n"
			),
			'class_properties_crlf' => array(
				'Ultralite',
				array(
					'user' => (object) array(
						'name' => 'Sir Henry',
						'quest' => 'To run away',
					),
				),
				'class_properties_crlf.tpl',
				"Sample line 1\r\n\r\nWhat is your name?  Sir Henry\r\nWhat is your quest?  To run away\r\n\r\nFantastic!\r\n",
			),
		);
	}


	/**
	 * Run a test of Ultralite or any subclass
	 *
	 * @dataProvider dataGeneric
	 * @param string $className
	 * @param array $variables
	 * @param string $template
	 * @param string $result
	 */
	public function testGeneric($className, $variables, $template, $result) {
		$ul = new $className(__DIR__ . '/fixtures', $variables);
		$err = null;
		$output = null;

		try {
			$output = $ul->render($template);
		} catch (Exception $ex) {
			$output = 'EXCEPTION: ' . $ex->getMessage();
		}

		$this->assertEquals($result, $output);
	}


	/**
	 * Confirm the right PHP code is generated for various tags and
	 * elements.
	 *
	 * @return array
	 */
	public function dataParse() {
		return array(
			'plain text' => array(
				"blah blah\nline 2",
				"blah blah\nline 2",
			),
			'variable' => array(
				'a {{$a}}',
				'a <?php $this->output(@ $a); ?' . '>',
			),
			'variable with whitespace' => array(
				"a {{  \t  \$a  \t  }}",
				'a <?php $this->output(@ $a); ?' . '>',
			),
			'variable end of line' => array(
				'a {{$a}}' . "\nline2",
				'a <?php $this->output(@ $a); ?' . "><?php echo \"\\n\"; ?" . ">\nline2",
			),
			'at' => array(
				"@function()",
				"<?php function() ?" . ">"
			),
			'at with whitespace' => array(
				"\t @ function() ",
				"<?php function() ?" . ">"
			),
			'at end of line' => array(
				"\t@function()\nLine2",
				"<?php function() ?" . ">\nLine2"
			),
			'at line two' => array(
				"One two three.\n@blah_blah\n",
				"One two three.\n<?php blah_blah ?" . ">\n"
			),
			'block' => array(
				"Line 1\nblah [[function()]]\n line 3",
				"Line 1\nblah <?php function() ?" . "><?php echo \"\\n\"; ?" . ">\n line 3"
			),
			'block with whitespace' => array(
				"Line 1\nblah [[ function() ]]\n line 3",
				"Line 1\nblah <?php function() ?" . "><?php echo \"\\n\"; ?" . ">\n line 3"
			),
			'include via inc' => array(
				'@$this->inc("bad");',
				'<?php $this->inc("bad"); ?' . '>'
			),
			'include via inc with params' => array(
				'@$this->inc("bad", array("a"=>"b"));',
				'<?php $this->inc("bad", array("a"=>"b")); ?' . '>'
			),
			'include via braces' => array(
				'{{>bad}}',
				'<?php $this->inc(\'bad\') ?' . '>'
			),
			'include via braces with param' => array(
				'{{>person chris="awesome"}}',
				'<?php $this->inc(\'person\', array(\'chris\'=>"awesome")) ?' . '>'
			),
			'include via braces with quoted param' => array(
				'{{>person chris="awe\\"some"}}',
				'<?php $this->inc(\'person\', array(\'chris\'=>"awe\\"some")) ?' . '>'
			),
			'include via braces with two params' => array(
				'{{>bad a=true b=7}}',
				'<?php $this->inc(\'bad\', array(\'a\'=>true, \'b\'=>7)) ?' . '>'
			),
			'include via braces with nasty params' => array(
				'{{>"bad" a=T_IF \'c\'="string }} \\\\\\" string" "d"=\'string2 \\\\\\\' yeah\'}}',
				'<?php $this->inc("bad", array(\'a\'=>T_IF, \'c\'=>"string }} \\\\\\" string", "d"=>\'string2 \\\\\\\' yeah\')) ?' . '>'
			),
			'include via braces with end of line' => array(
				"{{>partial.tpl}}\n@echo HI\n",
				"<?php \$this->inc('partial.tpl') ?" . ">\n<?php echo HI ?" . ">\n"
			),
		);
	}


	/**
	 * Try parsing a template and change it to valid PHP
	 *
	 * @dataProvider dataParse
	 * @param string $input
	 * @param string $result
	 */
	public function testParse($input, $result) {
		$engine = new Ultralite(__DIR__ . '/fixtures');
		$actual = $engine->parse($input);
		$this->assertEquals($result, $actual);
	}
}
