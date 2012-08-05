<?php

class Cache_Memcache extends Cache_Base {
	public function __construct() {
		if (! extension_loaded('memcache')) {
			throw new Exception('Memcache extension is not loaded');
		}

		$this->memcache = new Memcache();
	}

	/**
	 * Add a server to the pool.  You must call this or addServers()
	 * at least once.
	 *
	 * The host can be a hostname, IP address, or other transports.  See
	 * the PHP documentation for Memcache::pconnect() for more information.
	 *
	 * The default port for memcache is typically 11211, but that is not the
	 * default for the $port parameter.  This way you can add servers like
	 * this:
	 *
	 * $cache->addServer('localhost', 11211);  // Force a port
	 * $cache->addServer('unix:///var/lib/memcache.lock');  // No port
	 *
	 * @param string $host Hostname, IP, or other transport
	 * @param integer $port Port for memcache
	 * @return boolean True on success, false otherwise
	 */
	public function addServer($host, $port = 0) {
		$ret = $this->memcache->pconnect($host, $port);
		return $ret;
	}

	/**
	 * Add a bunch of servers to the pool in bulk.
	 *
	 * @param array $servers Server info, like multiple addServer() calls
	 * @return boolean True on success, false on failure
	 */
	public function addServers($servers) {
		$ret = true;

		foreach ($servers as $server) {
			$addRet = call_user_func_array(array($this, 'addServer'), $server);

			if (! $addRet) {
				$ret = false;
			}
		}

		return $ret;
	}
}
