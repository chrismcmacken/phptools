<?php

spl_autoload_register(function ($name) {
	$filename = '_' . strtolower($name) . '.class.php';
	$filename = str_replace('_', PATH_SEPARATOR);
	$filename = __DIR__ . $filename;

	if (file_exists($filename)) {
		require_once($filename);
		return true;
	}

	return false;
});
