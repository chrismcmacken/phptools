<?php

spl_autoload_register(function ($name) {
	$filename = strtolower($name) . '.class.php';
	$filename = str_replace('_', DIRECTORY_SEPARATOR, $filename);
	$paths = array(
		'db',
		'dependencyinjectioncontainer',
		'dump',
		'friend',
		'session2',
		'tokenizer',
		'webrequest',
	);

	foreach ($paths as $path) {
		$fullFile = __DIR__ . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $filename;
		if (file_exists($fullFile)) {
			require_once($fullFile);
			return true;
		}
	}

	return false;
});
