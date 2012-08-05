<?php

/**
 * Base class for all caching classes
 *
 * This defines a number of helpful functions and the default way that
 * caching is implemented.  Internal functions that are required to be
 * defined are also listed here.
 */
abstract class Cache_Base {
	protected $activeLocks = array();  // See lock() and unlock()
	protected $namespace = '';

	/**
	 * Remove active locks
	 */
	public function __destruct() {
		foreach ($this->activeLocks as $key => $uniqId) {
			$this->unlock($key);
		}
	}

	/**
	 * Adds a key/value to the cache as long as that key was not already
	 * set.  This will behave very similar to _set() and is the opposite
	 * of _replace().
	 *
	 * Unless your caching method does support "add", this default method
	 * will simulate it as best as it can.  This code is also susceptible
	 * to a race condition - see comment below for details.
	 *
	 * @param string $key Name of the thing to cache
	 * @param mixed $value What to cache
	 * @param integer $ttl Time to live, in seconds
	 * @return boolean True on success, false on failure
	 */
	public function add($key, $value, $ttl = 0) {
		// Get the value to see if it is already there
		if ($this->get($key) !== false) {
			return false;
		}

		// Set the new value.  The race condition could happen here if
		// another process can set a value before this one does.
		return $this->set($key, $value, $ttl);
	}

	/**
	 * Wipe all data from the cache.
	 *
	 * @return boolean True on success, false on failure
	 */
	public function clear();

	/**
	 * Delete a key/value from the cache.
	 *
	 * @param string $key Name of the thing to cache
	 * @return boolean True on success, false on failure
	 */
	public function delete($key);

	/**
	 * Retrieve a value for a key.
	 *
	 * @param string $key Name of the thing to cache
	 * @return mixed The stored value or false if not found
	 */
	public function get($key);

	/**
	 * "Lock" a value so only one process can own it
	 *
	 * Depending on implementation (see _add()), this may not be 100% reliable.
	 * To counter race conditions and other problems, we use a randomly
	 * generated value for the key/value pair and will check that when we
	 * unlock the key.  Retries only happen once per second.  If the object
	 * already has a lock on the value, this will immediately return false.
	 *
	 * @param string $key The key you wish to lock
	 * @param integer $ttl How long the lock should last, in seconds
	 * @param integer $retries How many times to try to get the lock
	 * @return boolean True on success, false on failure
	 */
	public function lock($key, $ttl = 10, $retries = 15) {
		if (! empty($this->activeLocks[$key])) {
			return false;
		}

		$lockKey = $this->lockKey($key);
		$tryCount = 0;
		$lockValue = uniqid(mt_rand(), true);
		$ret = $this->add($lockKey, $lockValue, $ttl);

		while (! $ret && $retries) {
			$retries --;
			$ret = $this->add($lockKey, $lockValue, $ttl);
		}

		if ($ret) {
			$this->activeLocks[$key] = $lockValue;
		}

		return $re
	}

	/**
	 * Generates the name for a lock given the regular key passed in.
	 * This also does not call makeKey().  Thus the key is NOT safe for
	 * immediate use.
	 *
	 * @param string $key The key we want to lock
	 * @return string The safe key for the lock we're generating
	 */
	protected function lockKey($key) {
		$lockKey = get_class($this) . '-lock-' . $key;
		return $lockKey;
	}

	/**
	 * Generate a namespaced key
	 *
	 * @param string $key Original key
	 * @return string Key with namespace added
	 */
	protected function makeKey($key) {
		$newKey = $this->namespace . $key;
		return $newKey;
	}

	/**
	 * Caches a key/value.
	 *
	 * @param string $key Name of the thing to cache
	 * @param mixed $value What to cache
	 * @param integer $ttl Time to live, in seconds
	 * @return boolean True on success, false on failure
	 */
	public function set($key, $value, $ttl = 0);

	/**
	 * Replaces a key/value to the cache as long as that key is already
	 * set.  This will behave very similar to _set() and is the opposite
	 * of _add().
	 *
	 * Unless your caching method does support "add", this default method
	 * will simulate it as best as it can.  This code is also susceptible
	 * to a race condition - see comment below for details.
	 *
	 * @param string $key Name of the thing to cache
	 * @param mixed $value What to cache
	 * @param integer $ttl Time to live, in seconds
	 * @return boolean True on success, false on failure
	 */
	public function replace($key, $value, $ttl = 0) {
		// Get the value to see if it is already there
		if ($this->get($key) === false) {
			return false;
		}

		// Set the new value.  The race condition could happen here if
		// another process can set a value before this one does.
		return $this->set($key, $value, $ttl);
	}

	/**
	 * Set a namespace for your key
	 *
	 * @param string $namespace
	 * @return boolean True on success, false on failure
	 */
	public function setNamespace($namespace) {
		if (is_null($namespace)) {
			$namespace = '';
		}

		if (! is_string($namespace)) {
			return false;
		}

		if ($namespace !== '') {
			$this->namespace = $namespace . '::';
		} else {
			$this->namespace = '';
		}

		return true;
	}

	/**
	 * Convert values into a string
	 *
	 * @param mixed $value Value to change into a string
	 * @return string The converted value
	 */
	protected function stringify($value) {
		$ret = 'ser:' . serialize($value);
		return $ret;
	}

	/**
	 * Unlock a key that you had previously locked.
	 *
	 * If you lost the lock via timeout, a race condition, or some other
	 * bad coding, then this returns false.
	 *
	 * @param string $key The key you had locked.
	 * @return boolean True on success, false on failure.
	 */
	public function unlock($key) {
		if (empty($this->activeLocks[$key])) {
			return false;
		}

		if ($this->get($key) !== $this->activeLocks[$key]) {
			return false;
		}

		$this->delete($key);
		unset($this->activeLocks[$key]);
		return true;
	}

	/**
	 * Convert a stringified value back to what it originally was.  If it
	 * can not be converted back, return false.
	 *
	 * @param string $string Value from stringify()
	 * @return mixed Original value
	 */
	protected function unstringify($string) {
		$parts = explode(':', $string, 2);

		if (count($parts) != 2) {
			return false;
		}

		if ($parts[0] == 'ser') {
			$data = @unserialize($parts[1]);
			return $data;
		}

		return false;
	}
}
