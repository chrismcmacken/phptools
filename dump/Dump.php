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
 * Write out a representation of the data so we can ideally copy it into a
 * PHP script and get back what we passed in.  Format it nicely so humans
 * don't cry when they read the output of large files.
 *
 * Unlike PHP's functions, this attempts to write out fully executable code
 * that is also not ugly.  Something like a pretty var_export or an
 * improved var_dump / print_r.  It handles showing the difference between
 * null, 0, false, and ''.  Also, this detects circular references, but the
 * generated output won't re-link the data into the circular reference again.
 */
class Dump {
	protected $collapses = array();
	protected $displayFlag = true;  // Should we display during destructor
	protected $htmlFlag = null;  // true, false, or null = autodetect
	protected $indent = 0;
	protected $name = null;  // What the var is named
	protected $nameStack = array();  // For recursion checks
	protected $what = null;

	/**
	 * Set up a copy of the value that was passed in.
	 *
	 * @param mixed $what Target to dump
	 * @param string|null $name Name to dump it as
	 */
	public function __construct($what = null, $name = null) {
		$this->what = $what;
		$this->name = $name;
	}


	/**
	 * The destructor is the usual spot for where the code writes out the
	 * data.
	 */
	public function __destruct() {
		if ($this->displayFlag) {
			$this->dumpStart();
		}
	}


	/**
	 * Convert to a string, in case "echo Dump::out($var)" is used.
	 *
	 * @return string
	 */
	public function __toString() {
		ob_start();
		$this->dumpStart();
		return ob_get_clean();
	}


	/**
	 * Set the htmlFlag so we output HTML instead of using autodetection
	 *
	 * @return $this
	 */
	public function asHtml() {
		$this->htmlFlag = true;
		return $this;
	}


	/**
	 * Set the htmlFlag so we output text instead of using autodetection
	 *
	 * @return $this
	 */
	public function asText() {
		$this->htmlFlag = false;
		return $this;
	}


	/**
	 * Returns the string of a function declaration from a ReflectionMethod.
	 * It looks something like this:
	 *
	 * static protected MyMethodName($mixed, SomeObject $whatever = null)
	 *
	 * @param ReflectionMethod $method
	 * @return string
	 */
	public function buildFunctionStartFromReflection(ReflectionMethod $method) {
		if ($method->isProtected()) {
			$out = 'protected';
		} elseif ($method->isPrivate()) {
			$out = 'private';
		} else {
			$out = 'public';
		}

		if ($method->isStatic()) {
			$out = 'static ' . $out;
		}

		$parameters = array();

		foreach ($method->getParameters() as $rparam) {
			$parameter = '$' . $rparam->name;
			$class = $rparam->getClass();

			if (! is_null($class)) {
				$parameter = $class->name . ' ' . $parameter;
			}

			if ($rparam->isDefaultValueAvailable()) {
				$default = var_export($rparam->getDefaultValue(), true);
				$parameter .= ' = ' . $default;
			}

			$parameters[] = $parameter;
		}

		$out .= ' function ' . $method->name . '(' . implode(', ', $parameters) . ')';
		return $out;
	}


	/**
	 * Finish a collapsable section
	 *
	 * @param string|null $contentHtml HTML to show in the alternate area
	 */
	protected function collapseEnd($contentHtml = null) {
		if (! array_pop($this->collapses)) {
			return;
		}

		if (is_null($contentHtml)) {
			$contentHtml = '&hellip;  ';
		}

		echo '</span><span style="display: none">' . $contentHtml . '</span>';
	}


	/**
	 * Start a collapsable section
	 *
	 * @param string $tag Text to show in tag
	 * @param boolean $expanding If false, show tag.  If true, show link
	 */
	protected function collapseStart($tag, $expanding = true) {
		if (! $this->htmlFlag) {
			$this->collapses[] = false;
			return;
		}

		$countStr = '  /* ' . $tag . ' */  ';

		if (! $expanding) {
			echo $this->span('count', $countStr);
			$this->collapses[] = false;
			return;
		}

		echo '<a href="#" onclick="return dumpToggle(this)" class="dump_count">' . $countStr . '</a><span>';
		$this->collapses[] = true;
	}


	/**
	 * Sets the display flag
	 *
	 * @param boolean $flag
	 * @return this
	 */
	public function display($flag) {
		$this->displayFlag = (boolean) $flag;
		return $this;
	}


	/**
	 * Helper function to find the right method to call to output a variable
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function dumpAnything(&$what, $silent = false) {
		$dataType = gettype($what);
		$method = 'helper' . $dataType;

		if (! method_exists($this, $method)) {
			$method = 'helperUnknown';
		}

		return $this->$method($what, $silent);
	}


	/**
	 * Writes out the name of the object
	 */
	protected function dumpName() {
		if (is_null($this->name)) {
			return;
		}

		if (! is_string($this->name)) {
			throw new Exception('Name must be a string');
		}

		$this->span('name', $this->name);
		$this->span('operator', ' = ');
	}


	/**
	 * Displays where a value recurses to
	 *
	 * @param array $traversed
	 * @return string
	 */
	protected function dumpRecursive($traversed) {
		$out = 'RECURSIVE(';
		
		foreach ($traversed as $entry) {
			if ($entry[0] == 'object') {
				$out .= '->' . $entry[2];
			} elseif ($entry[0] == 'array') {
				$out .= '[' . $this->dumpAnything($entry[2], true) . ']';
			} else {
				$out .= $entry[2];
			}
		}

		$out .= ')';
		return $this->span('recursive', $out);
	}


	/**
	 * Start a dump
	 */
	protected function dumpStart() {
		if (is_null($this->htmlFlag)) {
			if (php_sapi_name() != 'cli') {
				// Web-based request
				$this->htmlFlag = true;
			} else {
				$this->htmlFlag = false;
			}
		}

		if (! is_null($this->name)) {
			$this->pushStack(null, $this->name);
		} else {
			$this->pushStack(null, '_BASE');
		}

		if ($this->htmlFlag) {
?><style type="text/css">
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
<div class="dump"><?php
		}

		$this->dumpName();
		$this->dumpAnything($this->what);

		if (! is_null($this->name)) {
			echo ';';
		}

		if ($this->htmlFlag) {
			echo "</div>\n";
		} else {
			echo "\n";
		}
	}


	/**
	 * Gets an object's properties and returns them in an array
	 *
	 * The keys in the array are specially formatted:
	 *   XYZ = Public property XYZ
	 *   \0*\0XYZ = Protected property XYZ
	 *   \0ClassName\0 = Private property XYZ
	 *
	 * @param object $what
	 * @return array $properties
	 */
	public function getObjectProperties($what) {
		// The non-static properties are trivial to obtain
		$out = (array) $what;
		$accessibleClassVars = null;

		// Now we have to add the statics - first we try reflection
		$reflect = new ReflectionClass($what);
		$props = $reflect->getProperties(ReflectionProperty::IS_STATIC);

		if (is_array($props) && count($props)) {
			foreach ($props as $prop) {
				$key = $prop->getName();
				$origKey = $key;

				if ($prop->isProtected()) {
					$key = "\0*\0" . $key;
				} elseif ($prop->isPrivate()) {
					$key = "\0" . get_class($what) . "\0" . $key;
				}

				if (! method_exists($prop, 'setAccessible')) {
					// Reflection setAccessible was added in 5.3.0
					if (is_null($accessibleClassVars)) {
						$accessibleClass = $this->getStaticAccessibleClass($what);
						$accessibleClassVars = $accessibleClass->____getObjectVars();
					}

					if (isset($accessibleClassVars[$origKey])) {
						$value = $accessibleClassVars[$origKey];
					} else {
						$value = 'UNKNOWN - You need PHP 5.3';
					}
				} else {
					$prop->setAccessible(true);
					$value = $prop->getValue($what);
				}

				$out[$key] = $value;
			}
		}

		return $out;
	}


	/**
	 * Build a class and return an instance in order to access statics
	 *
	 * @param object $what
	 * @return object Class from which you can get statics
	 */
	public function getStaticAccessibleClass($what) {
		static $accessibleClasses = array();
		$oldClassName = get_class($what);

		if (! isset($accessibleClasses[$oldClassName])) {
			$newClassName = 'Acc_' . get_class($what) . '_' . uniqid();
			$reflect = new ReflectionClass(get_class($what));

			$template = "class $newClassName extends $oldClassName {\n";

			// Build a constructor if needed
			$constructor = $reflect->getConstructor();

			if ($constructor) {
				$template .= $this->buildFunctionStartFromReflection($constructor);
				$template .= "{}\n";
			}

			// Add our special methods to get statics and finish
			$template .= "\tpublic function ____getObjectVars() { return array_merge(get_class_vars(__CLASS__), get_object_vars(\$this)); }\n";
			$template .= "}";
			eval($template);  // put the new class into memory
			$accessibleClasses[$oldClassName] = $newClassName;
		}

		$newClassName = $accessibleClasses[$oldClassName];
		$serialized = serialize($what);
		$privateMark = ":\"\0$oldClassName\0";
		$lengthDifference = strlen($oldClassName) - 1;
		$serialized = explode($privateMark, $serialized, 2);

		while (count($serialized) > 1) {
			$pos = strrpos($serialized[0], ':');
			$num = substr($serialized[0], $pos + 1);
			$serialized[0] = substr($serialized[0], 0, $pos + 1);
			$serialized[0] .= $num - $lengthDifference;
			$serialized = implode(":\"\0*\0", $serialized);
		}

		$serialized = str_replace("\0$oldClassName\0", "\0*\0", $serialized);
		$serialized = explode(':', $serialized, 4);
		$serialized[1] = strlen($newClassName);
		$serialized[2] = '"' . $newClassName . '"';
		$serialized = implode(':', $serialized);
		$recreated = unserialize($serialized);
		return $recreated;
	}

	 
	/**
	 * Arrays
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 */
	protected function helperArray($what, $silent = false) {
		if ($this->isRecursive($what)) {
			return;
		}

		if (count($what) == 0) {
			$this->span('array', 'array()');
			return;
		}

		$this->span('array', 'array(');
		$this->collapseStart(count($what), ! empty($what));
		$this->indent ++;
		$isFirst = true;

		foreach ($what as $k => $v) {
			if (! $isFirst) {
				echo ',';
			}

			$isFirst = false;
			$this->newline();
			$this->span('key', $this->dumpAnything($k, true));
			$this->span('operator', ' => ');
			$this->pushStack($what, $k);
			$this->dumpAnything($v);
			$this->popStack();
		}

		$this->indent --;

		if (count($what)) {
			$this->newline();
		}

		$this->collapseEnd();
		$this->span('array', ')');
	}


	/**
	 * Boolean values
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function helperBoolean($what, $silent = false) {
		$value = ($what)? 'true' : 'false';
		return $this->span($value, $value, $silent);
	}


	/**
	 * Doubles and floating point numbers
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function helperDouble($what, $silent = false) {
		if ((string)$what == (string)((int)$what)) {
			$what .= '.0';
		}
		
		return $this->span('double', $what, $silent);
	}


	/**
	 * Integers
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function helperInteger($what, $silent = false) {
		return $this->span('integer', $what, $silent);
	}


	/**
	 * Null values
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function helperNull($what, $silent = false) {
		return $this->span('null', 'null', $silent);
	}


	/**
	 * Generic object dumping function.  Will call a less general
	 * method if one exists.
	 *
	 * @param mixed $what
	 * @param boolean $silent Not honored
	 * @return string
	 */
	protected function helperObject($what, $silent = false) {
		if ($this->isRecursive($what)) {
			return '';
		}

		$className = get_class($what);
		while ($className) {
			$testMethodName = 'object' . $className;
			if (method_exists($this, $testMethodName)) {
				return $this->$testMethodName($what, $silent);
			}
			$className = get_parent_class($className);
		}

		$data = $this->getObjectProperties($what);
		return $this->helperObjectSetState($what, get_class($what), $data);
	}


	/**
	 * Does the common bit of dumping a __set_state call to an object
	 *
	 * @param mixed $what
	 * @param string $className
	 * @param array $data
	 */
	protected function helperObjectSetState($what, $className, $data) {
		static $varTypes = array(
			'public',
			'protected',
			'private',
		);
		$this->span('object', $className . '::__set_state(array(');
		$this->collapseStart(count($data), ! empty($data));
		$this->indent ++;
		$isFirst = true;
		foreach ($data as $k => $v) {
			if (! $isFirst) {
				echo ',';
			}
			$isFirst = false;
			$this->newline();
			$varType = 0;  // 0 = public, 1 = protected, 2 = private
			$chunks = explode("\0", $k);
			if (count($chunks) == 3) {
				$k = $chunks[2];

				if ($chunks[1] == '*') {
					$varType = 1;  // \0*\0var_name
				} else {
					$varType = 2;  // \0class_name\0var_name
				}
			}
			$this->span('object_' . $varTypes[$varType], $this->dumpAnything($k, true));
			$this->span('operator', ' => ');
			$this->pushStack($what, $k);
			$this->dumpAnything($v);
			$this->popStack();
		}
		$this->indent --;
		if (count($data)) {
			$this->newline();
		}
		$this->collapseEnd();
		$this->span('object', '))');
	}


	/**
	 * Resources
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function helperResource($what, $silent = false) {
		$type = get_resource_type($what);
		$out = 'null /* resource(' . intval($what) . ', ' . $type;

		if ($type == 'stream') {
			$meta = stream_get_meta_data($what);
			
			if (isset($meta['uri'])) {
				$out .= ', "' . $meta['uri'] . '"';
			} else {
				$out .= ', type ' . $meta['stream_type'];
			}

			if (isset($meta['mode'])) {
				$out .= ', "' . $meta['mode'] . '"';
			}
		}

		$out .= ') */';
		return $this->span('resource', $out, $silent);
	}


	/**
	 * Strings
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function helperString($what, $silent = false) {
		$out = $what;
		$tag = null;
		$alternate = null;

		if ($this->htmlFlag && ! $silent) {
			$sql = $this->stringSql($what);

			if ($sql) {
				$alternate = $sql;
				$tag = 'Format SQL';
			}

			$xml = $this->stringXml($what);

			if ($xml) {
				$alternate = $xml;
				$tag = 'Format XML';
			}
		}

		// Check for simple strings where double quotes are not needed
		if (! preg_match('/[^ -~]/', $what)) {
			// Simple string, can be single quoted
			$out = str_replace('\\', '\\\\', $out);
			$out = str_replace('\'', '\\\'', $out);
			$out = '\'' . $out . '\'';
		} else {
			// More complex string and must be double quoted
			$swaps = array(
				'\\' => '\\\\',
				'"' => '\\"',
				'$' => '\\$',
				"\n" => '\\n',
				"\r" => '\\r',
				"\t" => '\\t',
				"\v" => '\\v',
				"\0" => '\\0',
			);
			$out = str_replace(array_keys($swaps), array_values($swaps), $out);
			$out = preg_replace('/([^ -~])/e', "'\x'.bin2hex(\"\\1\")", $out);
			$out = '"' . $out . '"';
		}

		if (is_null($alternate)) {
			return $this->span('string', $out, $silent);
		}

		$this->collapseStart($tag);
		$this->span('string', $out);
		$this->collapseEnd($alternate);
		return $out;
	}


	/**
	 * This method is called for all unhandled data types
	 *
	 * @param mixed $what
	 * @param mixed $silent If true, return plain text
	 * @return string
	 */
	protected function helperUnknown($what, $silent = false) {
		return $this->span('unknown', 'DUMPING TYPE OF ' . gettype($what) . ' IS NOT SUPPORTED', $silent);
	}


	/**
	 * Convert text and make it safe for HTML output
	 *
	 * @param string $text
	 * @return string HTML
	 */
	public function htmlSafeText($text) {
		$html = htmlentities($text);
		$html = nl2br($html);
		$html = str_replace("\t", ' &nbsp; &nbsp; &nbsp; &nbsp;', $html);
		return $html;
	}

	
	/**
	 * Writes a message and returns true if the value passed in appears to
	 * have been dumped previously.
	 *
	 * Arrays:  As we progress into an array, the serialized form should
	 * continue to get smaller.  If it is the same size or larger than any
	 * parent, we are looping.  Using === compares the arrays as strings,
	 * which will not work for us.
	 *
	 * Objects:  Store references to all parent objects and if this object
	 * === the parent object, it is recursion.
	 *
	 * @param array|object $what
	 * @return boolean True if this is recursion
	 */
	protected function isRecursive($what) {
		$type = gettype($what);
		$traversed = array();

		if (is_array($what)) {
			$what = strlen(serialize($what));
		}

		foreach ($this->nameStack as $entry) {
			if ($entry[0] == $type) {
				if ($entry[1] === $what) {
					$this->dumpRecursive($traversed);
					return true;
				}
			}
			$traversed[] = $entry;
		}

		return false;
	}


	/**
	 * Return true if the string passed in appears to be a SQL command
	 *
	 *   SELECT * FROM
	 *   INSERT INTO * VALUES
	 *   UPDATE * SET
	 *   ALTER TABLE
	 *
	 * @param string $what
	 * @return boolean
	 */
	protected function isSql($what) {
		if (! is_string($what)) {
			return false;
		}

		return preg_match('/^\s*(SELECT\s.+\sFROM|INSERT\s+INTO\s.+\sVALUES|UPDATE\s.*\sSET|ALTER\s+TABLE)\s/i', $what);
	}


	/**
	 * Write out a newline followed by a set of indents
	 */
	protected function newline() {
		echo "\n";
		echo str_repeat("\t", $this->indent);
	}


	/**
	 * ArrayObject special object handler
	 *
	 * @param ArrayObject $what
	 * @param boolean $silent
	 */
	protected function objectArrayObject($what, $silent) {
		$this->span('object', 'new ' . get_class($what) . '(');
		$s = $what->getArrayCopy();
		$this->dumpAnything($s, $silent);
		$this->span('object', ')');
	}


	/**
	 * DOMAttr special object handler
	 *
	 * @param DOMAttr $what
	 * @param boolean $silent
	 */
	protected function objectDOMAttr($what, $silent) {
		$s = array(
			'name' => $what->name,
			'specified' => $what->specified,
			'value' => $what->value,
		);
		$this->helperObjectSetState($what, get_class($what), $s);
	}


	/**
	 * DOMDocument special object handler
	 *
	 * @param DOMDocument $what
	 * @param boolean $silent
	 */
	protected function objectDOMDocument($what, $silent) {
		$this->span('object', 'DOMDocument::loadXML(');
		$s = $what->saveXML();
		$this->dumpAnything($s, $silent);
		$this->span('object', ')');
	}


	/**
	 * DOMElement special object handler
	 *
	 * @param DOMElement $what
	 * @param boolean $silent
	 */
	protected function objectDOMElement($what, $silent) {
		$s = array(
			'attributes' => $what->attributes,
			'baseURI' => $what->baseURI,
			'localName' => $what->localName,
			'namespaceURI' => $what->namespaceURI,
			'nodeName' => $what->nodeName,
			'nodeType' => $what->nodeType,
			'nodeValue' => $what->nodeValue,
			'prefix' => $what->prefix,
			'schemaTypeInfo' => $what->schemaTypeInfo,
			'tagName' => $what->tagName,
			'textContent' => $what->textContent,
		);
		$this->helperObjectSetState($what, get_class($what), $s);
	}


	/**
	 * DOMNamedNodeMap special object handler
	 *
	 * @param DOMNamedNodeMap $what
	 * @param boolean $silent
	 */
	protected function objectDOMNamedNodeMap($what, $silent) {
		$s = array();
		
		foreach ($what as $k => $v) {
			$s[$k] = $v;
		}

		$this->helperObjectSetState($what, get_class($what), $s);
	}


	/**
	 * DOMNode special object handler
	 *
	 * @param DOMNode $what
	 * @param boolean $silent
	 */
	protected function objectDOMNode($what, $silent) {
		$s = array(
			'attributes' => $what->attributes,
			'baseURI' => $what->baseURI,
			'localName' => $what->localName,
			'namespaceURI' => $what->namespaceURI,
			'nodeName' => $what->nodeName,
			'nodeType' => $what->nodeType,
			'nodeValue' => $what->nodeValue,
			'prefix' => $what->prefix,
			'textContent' => $what->textContent,
		);
		$this->helperObjectSetState($what, get_class($what), $s);
	}


	/**
	 * DOMNodeList special object handler
	 *
	 * @param DOMNodeList $what
	 * @param boolean $silent
	 */
	protected function objectDOMNodeList($what, $silent) {
		$s = array(
			'length' => $what->length,
		);
		$this->helperObjectSetState($what, get_class($what), $s);
	}


	/**
	 * stdClass special object handler
	 *
	 * @param stdClass $what
	 * @param boolean $silent
	 */
	protected function objectstdClass($what, $silent) {
		$s = $this->getObjectProperties($what);
		$this->span('object', '(object) ');
		$this->helperArray($s);
	}


	/**
	 * Helper function to make the code more readable.  To output, use this:
	 *
	 *   Dump::out($whatever_you_want);  // Echos out the value
	 *
	 * You can also chain the other functions in here in a fluent interface:
	 *
	 *   $output = Dump::out($thing)->asHtml()->return();  // Returns the value
	 *
	 * @param mixed $what
	 * @param string|null $name
	 * @return Dump
	 */
	static public function out($what = null, $name = null) {
		return new Dump($what, $name);
	}


	/**
	 * Remove a thing from the name stack
	 */
	protected function popStack() {
		array_pop($this->nameStack);
	}


	/**
	 * Push a thing onto the name stack
	 *
	 * @param mixed $what
	 * @param mixed $name
	 */
	protected function pushStack($what, $name) {
		$type = gettype($what);

		if (is_array($what)) {
			$what = strlen(serialize($what));
		}

		$this->nameStack[] = array(
			$type,
			$what,
			$name,
		);
	}

	/**
	 * Returns the string output so you don't need to capture it with
	 * ob_* functions.
	 *
	 * @return string
	 */
	public function returned() {
		$this->displayFlag = false;
		return $this->__toString();
	}


	/**
	 * Writes out a span tag
	 *
	 * @param string $class Trailing part of class name
	 * @param string $data Text to write
	 * @param boolean $silent True to not echo anything and just return text
	 * @return string
	 */
	protected function span($class, $data, $silent = false) {
		if (! $silent) {
			if ($this->htmlFlag) {
				echo '<span class="dump_' . $class . '">' . htmlentities($data) . '</span>';
			} else {
				echo $data;
			}
		}

		return $data;
	}


	/**
	 * Test if a string is SQL.  If so, return a formatted HTML version.
	 *
	 * @param string $str Input string to test and format
	 * @return null|string Formatted HTML or null
	 */
	protected function stringSql($str) {
		if (! preg_match('/^\s*(select\\s.*\\sfrom|insert\\s+into\\s.*\\svalues|update\\s+[^\\s]+\\sset|alter\\s+table)\\s/ims', $str)) {
			return null;
		}

		$tokens = $this->stringSqlTokenize($str);
		$text = $this->stringSqlTokensToText($tokens);
		return $this->htmlSafeText($text);
	}


	/**
	 * Change a SQL string into an array of tokens
	 *
	 * @param string $str SQL string to parse
	 * @return array Tokens
	 */
	protected function stringSqlTokenize($str) {
		$str = trim($str);

		// Break the string into tokens
		$tokens = array();
		$index = 0;
		$length = strlen($str);
		$token = '';

		while ($index < $length) {
			$char = substr($str, $index ++, 1);
			$lChar = strtolower($char);
			if (strpos('._abcdefghijklmnopqrstuvwxyz1234567890', $lChar) !== false) {
				// Continue building the token
				$token .= $char;
			} elseif (strpos('\'"`', $char) !== false) {
				// Go to the end of the quote
				if ($token != '') {
					$tokens[] = $token;
				}

				$token = $char;
				$match = $char;

				while ($index < $length && ($char = substr($str, $index ++, 1)) != $match) {
					if ($char == '\\') {
						$token .= $char;
						$char = substr($str, $index ++, 1);
					}

					$token .= $char;
				}

				$token .= $char;
			} elseif (strpos(" \r\n\t", $char) !== false) {
				if ($token != '') {
					$tokens[] = $token;
				}
				$token = '';
			} else {
				// Probably some operator
				if ($token != '') {
					$tokens[] = $token;
				}
				$tokens[] = array($char);  // Flag as a symbol
				$token = '';
			}
		}
		
		if ($token != '') {
			$tokens[] = $token;
		}

		return $tokens;
	}


	/**
	 * Convert an array of tokens back into a formatted string
	 *
	 * @param array $tokens
	 * @return string
	 */
	protected function stringSqlTokensToText($tokens) {
		// Insert a newline before these tags
		static $br = 'ALTER FROM GROUP HAVING INNER INSERT JOIN LEFT LIMIT ON ORDER RIGHT SELECT SET UPDATE VALUES WHERE';
		// Insert a newline and increase the indent for these
		static $brIndent = 'AND OR';
		// Tokens to only change into upper case
		static $caps = 'AS INTO NULL ON';
		$out = '';
		$indent = 0;

		if (! is_array($br)) {
			// Convert strings into arrays
			$br = explode(' ', $br);
			$brIndent = explode(' ' , $brIndent);
			$caps = explode(' ', $caps);
		}

		foreach ($tokens as $token) {
			if (is_array($token)) {
				$token = $token[0];
				if ($token == '(') {
					$indent ++;
				} elseif ($token == ')') {
					$indent --;
				}
				// TODO:  Handle symbols
				$out .= ' ' . $token;
			} else {
				$uToken = strtoupper($token);
				if (in_array($uToken, $caps)) {
					$out .= ' ' . $uToken;
				} elseif (in_array($uToken, $br)) {
					$out .= "\n" . str_repeat("\t", $indent) . $uToken;
				} elseif (in_array($uToken, $brIndent)) {
					$out .= "\n" . str_repeat("\t", $indent + 1) . $uToken;
				} else {
					$out .= ' ' . $token;
				}
			}
		}

		return trim($out);
	}


	/**
	 * Test if a string is XML.  If so, return a formatted HTML version.
	 *
	 * @param string $str Input string to test and format
	 * @return null|string Formatted HTML or null
	 */
	protected function stringXml($str) {
		if (! preg_match('/^\\s*</ms', $str) || ! preg_match('/>\\s*$/ms', $str)) {
			return null;
		}

		$xml = new DOMDocument();
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;
		$result = @$xml->loadXML($str);
		
		if (! $result) {
			return null;
		}

		$text = $xml->saveXML();
		return $this->htmlSafeText($text);
	}
}
