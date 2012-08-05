Cache Class
===========

Abstraction layer so you can switch out your caching for other mechanisms easily.  Handy for the following situations:

* You are writing code and are unsure what will be supported on the destination system.
* Testing if things are cached properly without relying on daemons working.
* A standard interface to simply storing information is desirable.
* Allowing for flexibility in the future by not tying yourself to a single implementation.
* You don't know the tricks to get data stored in the caching mechanism you're using and you don't want to research it - we handle it for you!

Things that you don't need to worry about:

* Key length for memcache and memcached.
* Invalid key characters for disk, memcache and memcached.
* Unexpected results with delete + set in memcache and memcached.
* Not calling addServers() for memcached when the persistent pool was found (see Memcached::__construct's comments).
* Serializing data when needed.

About Caching
-------------

A cache is a place where you can save information that was costly to obtain or generate.  Caches are found in computer processors to make accessing memory faster, on disk drives because reading from the disk is terribly slow, in your web browser to avoid additional network traffic and now in your PHP app to help you save time.

A cache is not intended to be a permanent place to hold information.  For that you should really use a database or other storage mechanism of some sort.  If the cache ever gets completely cleared, your application should continue to operate and it should not fail.  Keep in mind that a cache is there to prevent you from doing expensive operations, such as loading a user record.  If you can't get the data from the cache, you will need to be able to regenerate it again.

Cache Smashing
--------------

One thing you should be wary of is cache smashing.  If you have a bunch of front-end servers and they all are caching data just fine, but then someone clears the cache and you're getting hit by hundreds of visitors per second, this could pose a huge problem.  The data won't be in the cache, so you will have hundreds of requests per second now trying to generate this data with database queries, SOAP calls, reading files from disks or however they fetch the information.  Keep this potential issue in mind when designing how things get cached and how your code will fallback to build the information when it's not readily available in the cache.

Usage Examples
--------------

Create an APC cache and save a value.

    require('../../path_to/phptools/autoload.php');
    $cache = new Cache_Apc();
    $cache->set('favorite_color', 'blue');

Create an APC cache and get that value back.

    require('../../path_to/phptools/autoload.php');
    $cache = new Cache_Apc();
    $result = $cache->get('favorite_color');
    if ($result === false) {
        echo "Need to go look up the favorite color again.\n";
    } else {
        echo "Found cached favorite color: $result\n";
    }

Connect to three memcache servers and clear all data there.

    require('../../path_to/phptools/autoload.php');
    $cache = new Cache_Memcache();
    $cache->addServer('server-1.memcache.example.com', 11211);
    $cache->addServer('server-2.memcache.example.com', 11211);
    $cache->addServer('server-3.memcache.example.com', 11211);
    $cache->clear();

Use Zend's disk based caching with a custom namespace.  Obtain a lock on a key.  Set some data on the locked key and have it expire in three seconds.

    require('../../path_to/phptools/autoload.php');
    $cache = new Cache_Zend_Disk();
    $cache->setNamespace('sessions');
    $sessionId = get_session_id_from_somewhere();
    $sessionData = get_session_data_from_somewhere();
    if ($cache->lock($sessionId)) {
        // Locked the session
        $cache->set($sessionId, $mySessionData, 3);
        $cache->unlock($sessionId);
    } else {
        // Unable to lock the session within a few seconds.
        // You should probably handle this scenario carefully.
    }

Classes
-------

* Cache_Apc - APC in-memory caching on the current server, using PHP's apc extension.
* Cache_Base - Abstract class defining the interface and helpful methods.
* Cache_Disk - Manual disk-based caching.  File cleanup isn't so good, so you should cron the cleanup or put files in a spot where they get erased automatically by the system.
* Cache_Memcache - Uses PHP's memcache extension to connect to a memcache server or pool of memcache servers.
* Cache_Memcached - Uses PHP's memcached extension to connect to a memcache server or pool of memcache servers.
* Cache_Memory - In-memory cache that only lasts as long as the current process.
* Cache_None - A pretend caching class.  You should try this out to make sure the software you're writing falls back gracefully when the cached data isn't found.
* Cache_Zend_Disk - Zend server's disk-based caching.
* Cache_zend_Shm - Zend server's shm (shared memory) based caching.

Methods
-------

* `__construct()` - apc, memcache, memory, none, zend_disk, zend_shm
* `__construct($cacheDir = null, $extension = null)` - disk
* `__construct($persistentId = null)` - memcached

Instantiate a new object.  There are no arguments for most of the caching classes.

disk:  You can specify the cache directory where files get written and the extension on the files.  By default, the cache directory is `/tmp` and the extension is `.cache`

memcache:  The connection is automatically persistent.  While this means you'll automatically create connections to all of the memcache servers when the class is loaded, they will stick around for the next request automatically.

memcached:  You can specify the persistent pool ID, which makes the connection persistent.

* `add($key, $value, $ttl = 0)` - all classes

Adds a key/value pair to the cache, but only if the key is not already in the cache.  This is the opposite of replace().  For information on the parameters, see set().

Classes susceptible to a race condition:  disk, zend_disk, zend_shm

* `addServer($host, $port = 0)` - memcache
* `addServer($host, $port = 11211, $weight = 0)` - memcached

memcache: The default port is 0 to make connecting to UNIX sockets and other transports look better in your code.  It's modeled after Memcache::pconnect().

memcached:  If you are adding many servers, you will probably want to call addServers() instead so the internal data structures for the Memcached object only get updated once.

* `addServers($servers)` - memcache, memcached

Consolidates your calls to connect to memcache into one.  The structure of the $servers array is the same as the parameters to addServer().  Eg. for memcache, you would have an array like `array(array(localhost, 11211), array('192.168.5.10', 11211))`.  For more information, see Memcached::addServers().

* `clear()` - all classes

Wipes out the key/value pairs from the cache.  If supported and if you are using a namespace, this only wipes out the data for the one namespace.

Classes that clear everything, not just the namespaced data: apc, memcache, memcached

* `delete($key)` - all classes

Removes the key/value pair from the cache.

* `get($key)` - all classes

Retrieves a value from the cache.

* `lock($key, $ttl = 10, $retries = 15)` - all classes

Set another cached value flagging that a particular key is to be edited only by this process.  It is up to your code to enforce requiring a lock before editing things.  If you have one script that uses locks and another that does not, the second script is not automatically prohibited from editing the key/value even when the first has a lock.  Clearing and deleting values are not prohibited by locks either.

One way this could be used is for a page to lock a user's session.  When you use disk-based sessions (the default), PHP will lock the file so other PHP scripts that are running as that user won't destroy the session.  When the page execution is complete, the session lock is removed.  When you switch to a memcache-based session, this locking mechanism no longer exists.  Thus, this function can help you simulate locks.

You should manually call unlock() when done.  This class will also automatically unlock all locks when the object is destroyed.

$ttl is described in set().

Classes susceptible to a race condition are any that are listed for the add() method.

* `set($key, $value, $ttl = 0)` - all classes

Sets a value into the cache.  $key is the name that you want to set and $value is the associated value.  $ttl is how long the data should last, in seconds.  A $ttl of 0 means to persist the data and never expire it.

* `setNamespace($namespace)` - all classes

Switches to a different namespace.  By default, you are in a global namespace.  You can switch to any namespace by passing a string.  If you pass an empty string or null, you will go back to the global namespace.

* `replace($key, $value, $ttl = 0)` - all classes

Updates the value for a key/value pair in the cache, but only if the key is already in the cache.  This is the opposite of add().  For information on the parameters, see set().

Classes susceptible to a race condition:  apc, disk, zend_disk, zend_shm

* `unlock($key)` - all classes

Removes a lock that was granted for a specific key.

Race Condition
--------------

Some methods can suffer from a race condition where another process can change cached data and affect the results of a method.  For instance, add() should only add a value if it does not exist.  The default implementation that is supported by most caching classes will first check if the value exists and secondly will set the value.  If there is another call to set() by a second process during this time, add() will think it succeeded and so will the other process, but the result may be not what you want.

If you truly care about the extremely unlikely chance that this will happen, then you should use a module that prevents this race condition.

To Do
-----

* Add xcache
* Add additional stringify() methods
    * Dump / eval
    * var_export / eval
    * json - may lose formatting, but should not care for plain arrays
* Add a class to use a DB object
* Serialized floats are HUGE.  Like this: `d:3.14158999999999988261834005243144929409027099609375;` - maybe shorten?
    * `preg_replace('/d:([0-9]+(\.[0-9]+)?([Ee][+-]?[0-9]+)?);/e', "'d:'.((float)$1).';'", $val);  // d:3.14159;`
    * Some values could get rounded off, which would be bad
    * What if a string contains what looks like a serialized float?
