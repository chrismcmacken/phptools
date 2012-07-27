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
 shall not be used in advertising or otherwise to promote the sale, use or
 other dealings in this Software without prior written authorization.
 
 The end-user documentation included with the redistribution, if any, must
 include the following acknowledgment: "This product includes software
 developed by contributors", in the same place and form as other third-party
 acknowledgments. Alternately, this acknowledgment may appear in the software
 itself, in the same form and location as other such third-party
 acknowledgments.
 */
 
/** A no-frills-possible text/html templating engine.  There won't be any cool
 * caching, locale support, multiple template directory support nor even HTML
 * escaping.  One can just extend this class to add features as needed.
 *
 * This is just a slimmed down version of another templating engine, Templum.
 * Check it out at http://templum.electricmonk.nl/
 */
class Ultralite {
	// Pattern for matching an argument without spaces or quoted argument
	//    "([^\\"]|\\.)*"
	static protected $argPattern = '[^"\'\s][^\s]*|"(?:[^\\\\"]|\\\\.)*"|\'(?:[^\\\\\']|\\\\.)*\'';
	protected $baseDir = null;
	protected $parsingFile = null;
	protected $variables = array();

	public function __construct($templateDir, $variables = array()) {
		$this->baseDir = $templateDir;
		$this->variables = (array) $variables;
	}

	// Use magic getters to get variables
	public function __get($name) {
		if (! array_key_exists($name, $this->variables)) {
			return null;
		}

		return $this->variables[$name];
	}

	// Use magic setters to set variables
	public function __set($name, $value) {
		$this->variables[$name] = $value;
	}

	// Report errors that happen in templates - must be public
	public function errorHandler($code, $message, $file, $line) {
		if (($code & error_reporting()) == 0) {
			return true;
		}

		restore_error_handler();
		ob_end_clean();
		throw new Exception("$message (file: {$this->parsingFile}, line $line)");
	}

	// Separate method to get a template's contents for overriding
	protected function getFileContents($template) {
		$fn = $this->baseDir . '/' . $template;
		$rfn = realpath($fn);

		if (! $rfn) {
			throw new Exception('Unable to find ' . $fn);
		}

		if (! is_readable($rfn)) {
			throw new Exception('Unable to read ' . $rfn);
		}

		return file_get_contents($rfn);
	}

	// include another template in your template
	//
	// Shorthand:  {{>template.tpl}}
	// Long:  [[$this->inc('template.tpl')]]
	//
	// Shorthand:  {{>template.tpl a=1 b='BBB'}}
	// Long:  [[$this->inc('template.tpl', array('a'=>1, 'b'=>'BBB'))]]
	protected function inc($template, $moreVars = array()) {
		$class = get_class($this);
		$engine = new $class($this->baseDir, array_merge($this->variables, $moreVars));
		echo $engine->render($template);
	}

	// A simple method only for overrides
	protected function output($val) {
		echo $val;
	}

	// Take a template string and change it into PHP
	public function parse($str) {
		// Handle the {{>template}} shorthand, which requires a callback
		$str = preg_replace_callback('/{{>\s*(' . static::$argPattern . ')?((?:\s*(?:' . static::$argPattern . ')\s*=\s*(?:' . static::$argPattern . '))*)\s*}}/', array($this, 'parseInclude'), $str);

		// You will notice duplications in here.  PHP eats newlines following
		// close PHP tags, so we're compensating to preserve whitespace as
		// would be expected from a templating system.  Another goal is to
		// preserve the correct line number for the template.  Tricky.
		$replacements = array(
			// Catch newlines

			// {{$variable}} or {{function()}}
			'/({{\s*(?:.*?)\s*}})\\n/' => '\\1<?php echo "\\n"; ?' . ">\n",
			'/({{\s*(?:.*?)\s*}})\\r\\n/' => '\\1<?php echo "\\r\\n"; ?' . ">\r\\n",
			'/({{\s*(?:.*?)\s*}})\\r/' => '\\1<?php echo "\\r"; ?' . ">\r",
			'/{{\s*(.*?)\s*}}/' => '<?php $this->output(@ \\1); ?' . '>',

			// [[ PHP code ]]
			// Newline case is tricky: the code may not end with a semicolon
			'/\[\[[\n\r\f\t ]*/' => '<?php ',
			'/([\n\r\f\t ]*\]\])\\n/' => '\\1<?php echo "\\n"; ?' . ">\n",
			'/([\n\r\f\t ]*\]\])\\r\\n/' => '\\1<?php echo "\\r\\n"; ?' . ">\r\n",
			'/([\n\r\f\t ]*\]\])\\r/' => '\\1<?php echo "\\r"; ?' . ">\r",
			'/[\n\r\f\t ]*\]\]/' => ' ?' . '>',

			// @ PHP code
			'/^[ \t\f]*@[\f\t ]*(.*[^\n\r\t\f ])?[ \t\f]*$/m' => '<?php \\1 ?' . '>'
		);
		$php = preg_replace(array_keys($replacements), array_values($replacements), $str);
		return $php;
	}

	// Translate {{> template }} and {{> template arg=val arg2=val2}}
	// into [[$this->inc(template)]] and
	// [[$this->inc(template, array(arg=>val, arg2=>val2))]]
	protected function parseInclude($matches) {
		$out = '<?php $this->inc(' . $this->quote($matches[1]);
		$args = array();

		if (count($matches) >= 3) {
			$argsStr = $matches[2];
			$sep = '';

			while (preg_match('/\s*(' . static::$argPattern . ')\s*=\s*(' . static::$argPattern . ')\s*(.*)/', $argsStr, $submatches)) {
				$args[] = $this->quote($submatches[1]) . '=>' . $submatches[2];
				$argsStr = $submatches[3];
			}
		}

		if (count($args)) {
			$out .= ', array(';
			$out .= implode(', ', $args);
			$out .= ')';
		}

		$out .= ') ?' . '>';

		// Do not worry about the newline at the end of the line being
		// consumed by PHP since the template file will probably end with
		// a newline
		return $out;
	}

	protected function quote($thing) {
		$c = substr($thing, 0, 1);

		if ($c == '"' || $c == "'") {
			return $thing;
		}

		return "'" . addslashes($thing) . "'";
	}

	// Generate the processed template results
	public function render($template) {
		$this->parsingFile = $template;
		extract($this->variables);
		$contents = $this->getFileContents($template);
		$php = $this->parse($contents);
		set_error_handler(array($this, 'errorHandler'));
		ob_start();
		//fwrite(STDERR, "\n\n$template\n\n$php\n");
		eval('?' . '>' . $php);
		$result = ob_get_clean();
		restore_error_handler();
		$this->parsingFile = null;
		return $result;
	}
}

