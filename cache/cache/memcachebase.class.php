<?php

class Cache_MemcacheBase extends Cache_Base {
	public $memcache = null;
	public $recentDeletes = array();
	public $recentDeleteTime = 0;

	public function add($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$this->recentDeletesCheck($safeKey);
		$safeTtl = $this->makeTtl($ttl);
		$ret = @$this->memcache->add($safeKey, $value, 0, $safeTtl);
		return $ret;
	}

	/**
	 * This marks everything as expired, which could also trigger a bug if
	 * you try to set something right away.  That's why we sleep for 1 second
	 * and reset the recent deletes array
	 */
	public function clear() {
		$ret = @$this->memcache->flush();
		sleep(1);
		$this->recentDeletes = array();
		$this->recentDeleteTime = 0;
		return $ret;
	}

	public function delete($key) {
		if (time() !== $this->recentDeleteTime) {
			$this->recentDeletes = array();
			$this->recentDeleteTime = time();
		}

		$safeKey = $this->makeKey($key);
		$this->recentDeletes[$key] = true;
		$ret = @$this->memcache->delete($safeKey);
		return $ret;
	}

	public function get($key) {
		$safeKey = $this->makeKey($key);
		$this->recentDeletesCheck($safeKey);
		$ret = @$this->memcache->get($safeKey);
		return $ret;
	}

	/**
	 * Make a safe key for use with memcache.  Also adds namespace to the key.
	 *
	 * No whitespace, no control characters, and a max size of 250 characters.
	 * The documentation says it is a "text string" but doesn't define what
	 * text is.  I'll define it as all characters from ! to ~.
	 *
	 * @param string $key Input key that might be unsafe
	 * @return string Safe key for use with memcache
	 */
	protected function makeKey($key) {
		$newKey = $this->namespace . $key;

		if (strlen($newKey) > 250 || preg_match('/[^\\041-\\176]/', $newKey)) {
			// Convert to a safe key
			// md5 = 32 chars, sha1 = 40 chars, total = 72 chars
			$newKey = md5($newKey) . sha1($newKey);
		}

		return $newKey;
	}

	/**
	 * Memcache has a quirk with large TTL values
	 *
	 * @param integer $ttl TTL from user
	 * @return integer TTL that memcache understands
	 */
	protected function makeTtl($ttl) {
		if ($ttl > 2592000) {
			return 2592000;
		}

		return $ttl;
	}

	public function set($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$this->recentDeletesCheck($safeKey);
		$safeTtl = $this->makeTtl($ttl);
		$ret = @$this->memcache->set($safeKey, $value, 0, $safeTtl);
		return $ret;
	}

	/**
	 * Memcache doesn't handle a delete followed immediately by a set
	 * very well.  In this instance, we shall sleep to at least help
	 * prevent the problem.
	 *
	 * @param string $safeKey Safe version of a key
	 */
	protected function recentDeletesCheck($safeKey) {
		if (! $this->recentDeleteTime) {
			return;
		}

		if ($this->recentDeleteTime != time()) {
			$this->recentDeletes = array();
			$this->recentDeleteTime = 0;
			return;
		}

		if (array_key_exists($safeKey, $this->recentDeletes)) {
			sleep(1);
			$this->recentDeletes = array();
			$this->recentDeleteTime = 0;
		}
	}

	public function replace($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$this->recentDeletesCheck($safeKey);
		$safeTtl = $this->makeTtl($ttl);
		$ret = @$this->memcache->replace($key, $value, 0, $safeTtl);
		return $ret;
	}
}
