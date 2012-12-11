<?php

spl_autoload_register(function ($name) {
	$filename = $name . '.php';
	$filename = str_replace('_', DIRECTORY_SEPARATOR, $filename);
	$paths = array(
		'Cache',
		'Crypto',
		'DB',
		'DependencyInjectionContainer',
		'Dump',
		'Friend',
		'Logger',
		'Renamer',
		'Session2',
		'SlimRoute',
		'Tokenizer',
		'Ultralite',
		'VariableStream',
		'WebRequest',
		'WebResponse',
		'ZipBuilder',
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
