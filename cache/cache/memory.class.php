<?php

/**
 * In-memory cache.  This will get wiped when the process terminates because
 * it doesn't use any external system.
 */
class Cache_Memory extends Cache_Base {
	protected $store = array();

	public function add($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);

		if (array_key_exists($safeKey, $this->store[$this->namespace])) {
			return false;
		}

		$ret = $this->set($key, $value, $ttl);
		return $ret;
	}

	public function clear() {
		if (empty($this->namespace)) {
			$this->store = array();
		} else {
			$this->store[$this->namespace] = array();
		}

		return true;
	}

	public function delete($key) {
		$safeKey = $this->makeKey($key);

		if (! array_key_exists($safeKey, $this->store[$this->namespace])) {
			return false;
		}

		unset($this->store[$this->namespace]);
		return true;
	}

	public function get($key) {
		$safeKey = $this->makeKey($key);

		if (! array_key_exists($safeKey, $this->store[$this->namespace])) {
			return false;
		}

		return $this->store[$this->namespace];
	}

	public function set($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$this->store[$this->namespace] = $value;
		return true;
	}

	public function replace($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);

		if (array_key_exists($safeKey, $this->store[$this->namespace])) {
			return false;
		}

		$ret = $this->set($key, $value, $ttl);
		return $ret;
	}

	public function setNamespace($namespace) {
		if (empty($namespace)) {
			$namespace = '';
		}

		if (! is_string($namespace)) {
			return false;
		}

		if (! array_key_exists($namespace, $this->store)) {
			$this->store[$namespace] = array();
		}

		$this->namespace = $namespace;
		return true;
	}
}
