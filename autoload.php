<?php

spl_autoload_register(function ($name) {
	$filename = $name . '.php';
	$filename = str_replace('_', DIRECTORY_SEPARATOR, $filename);
	$paths = array(
		'cache',
		'crypto',
		'db',
		'dependencyinjectioncontainer',
		'dump',
		'friend',
		'logger',
		'renamer',
		'session2',
		'slimroute',
		'tokenizer',
		'ultralite',
		'variablestream',
		'webrequest',
		'webresponse',
		'zipbuilder',
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
