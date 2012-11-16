<?php

/**
 * This file contains wrapper functions for PHP's Memcached interface.
 * Connections to memcached are automatically created when needed.
 *
 * @author finke
 */

if (DEBUG) {
	$GLOBALS["cache_calls"] = array();
}

if (!MEMCACHE) {
	$GLOBALS["temp_cache"] = array();
}

$GLOBALS["local_cache"] = array();

/**
 * Connects to in-memory cache.
 *
 * @author finke
 */

function cache_connect($force = false) {
	if (MEMCACHE || $force) {
		if (!isset($GLOBALS["mc"])) {
			$GLOBALS["mc"] = new Memcache;
			
			foreach ($GLOBALS["MEMCACHE_SERVERS"] as $server) {
				$GLOBALS["mc"]->addServer($server[0], $server[1]);
			}
		}
	}
}

/**
 * Retrieves a value from the in-memory cache.
 *
 * Returns false when caching is disabled or when the object is not in cache.
 *
 * @author finke
 * @param string $key The cache key.
 * @return mixed The object, or false on failure.
 */

function cache_get($key) {
	if (MEMCACHE && !isset($_GET["nocache"])) {
		if (isset($_GET["uncache"])) {
			if (!isset($GLOBALS["uncache_progress"])) {
				$GLOBALS["uncache_progress"] = array();
			}
			
			$GLOBALS["uncache_progress"][$key] = true;
			
			if ((strpos($key, "module:") === 0) || (strpos($key, "page:") === 0)) {
				return false;
			}
		}
		
		if (isset($GLOBALS["local_cache"][$key])) {
			return $GLOBALS["local_cache"][$key];
		}
		
		cache_connect();
		
		$rv = $GLOBALS["mc"]->get($key);
		$GLOBALS["local_cache"][$key] = $rv;
		
		return $rv;
	}
	else {
		if (!isset($GLOBALS["temp_cache"][$key])) {
			return false;
		}
		
		return $GLOBALS["temp_cache"][$key];
	}
}

/**
 * Inserts a value into the in-memory cache.
 *
 * The default expiration time is 24 hours.  Returns true on success, false on failure.
 * It does not matter whether the object is already in the cache. 
 *
 * @author finke
 * @param string $key The cache key.
 * @param mixed $val The object to store in cache.
 * @param int $timeout The maximum number of seconds to store the object.
 * @return boolean True on success, false on failure.
 */

function cache_set($key, $val, $timeout = 86400) {
	$rv = true;
	
	if (MEMCACHE) {
		cache_connect();
		
		$rv = $GLOBALS["mc"]->set($key, $val, false, $timeout);
		$GLOBALS["local_cache"][$key] = $val;
	}
	else {
		$GLOBALS["temp_cache"][$key] = $val;
	}
	
	return $rv;
}

/**
 * Increments a value in cache.
 * 
 * @param string $key The cache key to increment.
 * @return int/boolean The value after incrementing, or false if the key was not cached
 */

function cache_incr($key) {
	$rv = false;
	
	if (MEMCACHE) {
		cache_connect();
		
		$rv = $GLOBALS["mc"]->increment($key);
		
		if (isset($GLOBALS["local_cache"][$key])) {
			$GLOBALS["local_cache"][$key]++;
		}
	}
	else {
		if (isset($GLOBALS["temp_cache"][$key])) {
			$GLOBALS["temp_cache"][$key]++;
		}
	}
	
	return $rv;
}

/**
 * Decrements a value in cache.
 * 
 * @param string $key The cache key to decrement.
 * @return int/boolean The value after decrementing, or false if the key was not cached
 */

function cache_decr($key) {
	$rv = false;
	
	if (MEMCACHE) {
		cache_connect();
		
		$rv = $GLOBALS["mc"]->decrement($key);
		
		if (isset($GLOBALS["local_cache"][$key])) {
			$GLOBALS["local_cache"][$key]--;
		}
	}
	else {
		if (isset($GLOBALS["temp_cache"][$key])) {
			$GLOBALS["temp_cache"][$key]--;
		}
	}	
	return $rv;
}

/**
 * Removes an object from the cache.
 *
 * @author finke
 * @param string $key The cache key.
 * @return boolean True on success, false on failure.
 */

function cache_delete($key) {
	$rv = true;
	
	if (MEMCACHE) {
		cache_connect();
		
		$rv = $GLOBALS["mc"]->delete($key);
		
		if (isset($GLOBALS["local_cache"][$key])) {
			unset($GLOBALS["local_cache"][$key]);
		}
	}
	else {
		unset($GLOBALS["temp_cache"][$key]);
	}
	
	return $rv;
}

/**
 * Closes the connection to the in-memory cache.
 *
 * @author finke
 */

function cache_close() {
	if (MEMCACHE) {
		if (isset($GLOBALS["mc"])) {
			$GLOBALS["mc"]->close();
			unset($GLOBALS["mc"]);
		}
	}
}

/**
 * Flushes the cache.
 *
 * @author finke
 */

function cache_flush() {
	cache_connect(true);

	if (MEMCACHE) {
		$GLOBALS["local_cache"] = array();
		
		return $GLOBALS["mc"]->flush();
	}
	else {
		$GLOBALS["temp_cache"] = array();
	}
	
	return true;
}

?>