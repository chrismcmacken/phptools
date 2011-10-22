<?php

spl_autoload_register(function ($name) {
	$filename = '_' . strtolower($name) . '.class.php';
	$filename = str_replace('_', PATH_SEPARATOR, $filename);
	$filename = __DIR__ . $filename;
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
		$fullFile = $path . PATH_SEPARATOR . $filename;
		if (file_exists($fullFile)) {
			require_once($fullFile);
			return true;
		}
	}

	return false;
});
