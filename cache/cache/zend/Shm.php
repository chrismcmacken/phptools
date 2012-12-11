<?php

class Cache_Zend_Shm extends Cache_Base {
	public function __construct() {
		if (! extension_loaded('zend_cache')) {
			throw new Exception('Zend Cache extension is not loaded');
		}
	}

	public function clear() {
		$ret = @zend_shm_cache_clear($this->namespace);
		return $ret;
	}

	public function delete($key) {
		$safeKey = $this->makeKey($key);
		$ret = @zend_shm_cache_delete($safeKey);
		return $ret;
	}

	public function get($key) {
		$safeKey = $this->makeKey($key);
		$ret = @zend_shm_cache_fetch($safeKey);
		return $ret;
	}

	public function set($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$ret = @zend_shm_cache_store($safeKey, $value, $ttl);
		return $ret;
	}
}
