<?php

/**
 * Blatantly lie and say that stuff was cached.  However, it will never be
 * there when you look for it.
 */
class Cache_None extends Cache_Base {
	public function add($key, $value, $ttl = 0) {
		return true;
	}

	public function clear() {
		return true;  // Nothing to clear, so this is accurate
	}

	public function delete($key) {
		return true;
	}

	public function get($key) {
		return false;
	}

	public function set($key, $value, $ttl = 0) {
		return true;
	}

	public function replace($key, $value, $ttl = 0) {
		return true;
	}
}
