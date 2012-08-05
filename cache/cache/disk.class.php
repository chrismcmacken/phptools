<?php

class Cache_Disk extends Cache_Base {
	protected $cacheDir = '/tmp/';
	protected $extension = '.cache';

	public function __construct($cacheDir = null, $extension = null) {
		if (! is_null($cacheDir)) {
			$path = realpath($cacheDir);

			if (! is_dir($path)) {
				throw new Exception($cacheDir . ' is not a directory');
			}

			if (! is_writable($path)) {
				throw new Exception($cacheDir . ' is not writable');
			}

			$this->cacheDir = $path . '/';
		}

		if (! empty($extension) && is_string($extension)) {
			$this->extension = $extension;
		}
	}

	public function add($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);

		if (file_exists($this->cacheDir . $safeKey)) {
			return false;
		}

		$ret = $this->set($key, $value, $ttl);
		return $ret;
	}

	public function clear() {
		$files = glob($this->cacheDir . $this->namespace . '*' . $this->extension);
		$ret = true;

		foreach ($files as $file) {
			if (! unlink($file)) {
				$ret = false;
			}
		}

		if (empty($this->namespace)) {
			$files = glob($this->cacheDir . '*/*' . $this->extension);

			foreach ($files as $file) {
				if (! unlink($file)) {
					$ret = false;
				}
			}
		}

		return $ret;
	}

	public function delete($key) {
		$safeKey = $this->makeKey($key);

		if (! file_exists($this->cacheDir . $safeKey)) {
			return false;
		}

		$ret = unlink($this->cacheDir . $safeKey);
		return $ret;
	}

	public function get($key) {
		$safeKey = $this->makeKey($key);

		if (! file_exists($this->cacheDir . $safeKey)) {
			return false;
		}

		$json = @file_get_contents($this->cacheDir . $safeKey);
		$decoded = @json_decode($json);

		// Clean up invalid data
		if (! is_array($decoded) || empty($decoded['data'])) {
			$this->delete($key);
			return false;
		}

		// Clean up expired data
		if (! empty($decoded['expires']) && $decoded['expires'] < time()) {
			$this->delete($key);
			return false;
		}

		return $this->unstringify($decoded['data']);
	}

	protected function makeKey($key) {
		// The key needs to be converted
		$newKey = $this->makeKeyPathPortion($key);
		$newKey = $this->namespace . $newKey . $this->extension;
		return $newKey;
	}

	protected function makeKeyPathPortion($path) {
		// Make this chunk into a safe path.  Disallow periods, slashes,
		// weird characters.  Only allow letters, numbers, and a couple
		// symbols.
		if (strlen($path) > 80 || preg_match('/[^-a-zA-Z0-9_]/', $path)) {
			$path = md5($path) . sha1($path);
		}

		return $path;
	}

	public function set($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);
		$data = array(
			'data' => $this->stringify($value)
		);
		
		if ($ttl) {
			$data['expires'] = time() + $ttl;
		}

		$json = json_encode($data);
		@file_put_contents($this->cacheDir . $safeKey, $json);
		return $ret;
	}

	public function replace($key, $value, $ttl = 0) {
		$safeKey = $this->makeKey($key);

		if (! file_exists($this->cacheDir . $safeKey)) {
			return false;
		}

		return $this->set($key, $value, $ttl);
	}

	public function setNamespace($namespace) {
		if (empty($namespace)) {
			$this->namespace = '';
			return true;
		}

		if (! is_string($namespace)) {
			return false;
		}

		// We use namespaces as directories
		$namespace = $this->makeKeyPathPortion($namespace) . '/';

		if (! is_dir($this->cacheDir . $namespace)) {
			if (! mkdir($this->cacheDir . $namespace)) {
				return false;
			}
		}

		$this->namespace = $namespace;

		return true;
	}
}
