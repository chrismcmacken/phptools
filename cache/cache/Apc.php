<?php

class Cache_Apc extends Cache_Base {
	public function __construct() {
		if (! extension_loaded('apc')) {
			throw new Exception('APC extension is not loaded');
		}
	}

	public function add($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$ret = @apc_add($safeKey, $value, $ttl);
		return $ret;
	}

	public function clear() {
		$ret = @apc_clear_cache('user');
		return $ret;
	}

	public function delete($key) {
		$safeKey = $this->makeKey($key);
		$ret = @apc_delete($safeKey);
		return $ret;
	}

	public function get($key) {
		$safeKey = $this->makeKey($key);
		$ret = @apc_fetch($safeKey);
		return $ret;
	}

	public function set($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$ret = @apc_store($safeKey, $value, $ttl);
		return $ret;
	}
}
