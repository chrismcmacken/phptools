<?php

class Cache_Memcached extends Cache_MemcacheBase {
	protected $serversToAdd = array();

	public function __construct($persistentId = null) {
		if (! extension_loaded('memcached')) {
			throw new Exception('Memcached extension is not loaded');
		}

		if (! is_null($persistentId)) {
			$this->memcache = new Memcached($persistentId);
		} else {
			$this->memcache = new Memcached();
		}
	}

	/**
	 * Add a server to the pool.  You must call this or addServers()
	 * at least once.
	 *
	 * The host can be a hostname or IP address.  The weight can optionally
	 * specify additional server weighting.
	 *
	 * @param string $host Hostname or IP
	 * @param integer $port Port for memcache
	 * @param integer $weight Server weighting, default 0
	 * @return boolean True on success, false otherwise
	 */
	public function addServer($host, $port, $weight = 0) {
		$server = array($host, $port, $weight);

		if (! $this->alreadyListed($server)) {
			$ret = $this->addServer($host, $port, $weight);
		}

		return $ret;
	}

	/**
	 * Add a bunch of servers to the pool.  A bulk version of addServer().
	 *
	 * @param array $servers Server info, like what's used for addServer()
	 * @return boolean True on success, false otherwise
	 */
	public function addServers($servers) {
		$serversFiltered = array();

		foreach ($servers as $server) {
			if (! $this->alreadyListed($server)) {
				$serversFiltered[] = $server;
			}
		}

		if ($serversFiltered) {
			$ret = $this->addServers($servers);
		}

		return $ret;
	}

	/**
	 * Checks if we should connect to servers and will add all of them at
	 * once.  This makes the internal data structures only update once.
	 *
	 * @return boolean True on success, false otherwise
	 */
	public function addServersInternal() {
		if (empty($this->serversToAdd)) {
			return true;
		}

		$ret = $this->memcache->addServers($this->serversToAdd);
		$this->serversToAdd = array();
		return $ret;
	}

	/**
	 * See if this server is already listed in Memcached's list
	 *
	 * @param array $server Host, port, weight
	 * @return boolean True if found, false otherwise
	 */
	protected function alreadyListed($server) {
		foreach ($this->memcache->getServerList() as $inList) {
			if ($server[0] == $inList['host'] && $server[1] == $inList['port'] && $server[2] == $inList['weight']) {
				return true;
			}
		}

		return false;
	}
}
