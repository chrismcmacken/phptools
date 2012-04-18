#!/usr/bin/php
<?php

require_once(__DIR__ . '/tokenizer.class.php');

function countCalls($callback, $seconds = 10) {
	$iterations = 0;
	$start = microtime(true);
	while (microtime(true) - $start < $seconds) {
		$callback();
		$iterations ++;
	}
	$end = microtime(true);
	return array(
		'iterations' => $iterations,
		'time' => $end - $start,
		'perSec' => $iterations / ($end - $start)
	);
}

function runTest($test) {
	if (! empty($test['setup'])) {
		$func = $test['setup'];
		echo "Preparing " . $test['name'] . "\n";
		$func();
	}

	echo "Timing " . $test['name'] . "\n";
	$result = countCalls($test['function']);
	showResult($result);
}

function showResult($result) {
	echo "\t" . $result['perSec'] . " calls per second\n";
}

function testFind() {
	$t = clone $GLOBALS['findTokenizer'];
	$t->findTokens(array(
		T_IS_EQUAL,
		T_IS_IDENTICAL,
		T_IS_GREATER_OR_EQUAL,
		T_IS_SMALLER_OR_EQUAL,
		T_IS_NOT_EQUAL,
		T_IS_NOT_IDENTICAL)
	);
}

function testFindSetup() {
	if (empty($GLOBALS['findTokenizer'])) {
		$GLOBALS['findTokenizer'] = Tokenizer::tokenizeFile('big_file.php');
	}
}

function testFindRepeat() {
	$t = clone $GLOBALS['findTokenizerRepeat'];
	$t->findTokens(array(
		T_IS_EQUAL,
		T_IS_IDENTICAL,
		T_IS_GREATER_OR_EQUAL,
		T_IS_SMALLER_OR_EQUAL,
		T_IS_NOT_EQUAL,
		T_IS_NOT_IDENTICAL)
	);
}

function testFindRepeatSetup() {
	if (empty($GLOBALS['findTokenizerRepeat'])) {
		$GLOBALS['findTokenizerRepeat'] = Tokenizer::tokenizeFile('big_file.php');
		$GLOBALS['findTokenizerRepeat']->indexTokenTypes();
	}
}

function testTokenize() {
	$tokenizer = Tokenizer::tokenizeFile('big_file.php');
}


if (! file_exists('big_file.php')) {
	echo "Sorry, but you need to find or make a really big PHP file for\n";
	echo "the tokenization tests.  Name it \"big_file.php\" and then run\n";
	echo "this speed test program again.\n";
	exit();
}

$tests = array(
	array(
		'longOption' => 'tokenize',
		'shortOption' => 't',
		'enabled' => false,
		'name' => 'Tokenize::tokenizeFile()',
		'function' => 'testTokenize',
		'setup' => null,
	),
	array(
		'longOption' => 'find',
		'shortOption' => 'f',
		'enabled' => false,
		'name' => 'Tokenize->findTokens()',
		'function' => 'testFind',
		'setup' => 'testFindSetup',
	),
	array(
		'longOption' => 'index-find',
		'shortOption' => 'i',
		'enabled' => false,
		'name' => 'Tokenize->findTokens() with index',
		'function' => 'testFindRepeat',
		'setup' => 'testFindRepeatSetup',
	)
);

$longOptions = array();
$shortOptions = '';

foreach ($tests as $test) {
	$longOptions[] = $test['longOption'];
	$shortOptions .= $test['shortOption'];
}

$options = getopt($shortOptions, $longOptions);

foreach ($options as $option => $optionValue) {
	$handled = false;

	// Careful - $test is by reference
	foreach ($tests as &$test) {
		if ($test['longOption'] == $option || $test['shortOption'] == $option) {
			$test['enabled'] = true;
			$handled = true;
		}
	}

	unset($test);  // Done using this by reference
}

$testsExecuted = 0;

foreach ($tests as $test) {
	if ($test['enabled']) {
		runTest($test);
		$testsExecuted ++;
	}
}

if ($testsExecuted == 0) {
	// Show help
	echo "Specify which speed tests to run using command-line parameters:\n";

	foreach ($tests as $test) {
		echo "\t-" . $test['shortOption'] . ", --" . $test['longOption'] . " \t" . $test['name'] . "\n";
	}
}
