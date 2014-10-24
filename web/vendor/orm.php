<?php

/**
 * Classes for objects that map to entries in database tables.
 *
 * @author finke
 */

/**
 * Base class for database-bound objects.
 *
 * @author finke
 * @requires PHP 5.3+
 */
 
class Object {
	/**
	 * The associated database table for this object.
	 * @var string
	 */
	var $db_table = null;
	
	/**
	 * The API to use for DB interaction. Possible values are "mysql" and "atomics"
	 */
	var $db_api = "mysql";
	
	/**
	 * If the API is atomics, then the object must provide a default host.
	 */
	var $db_host = null;
	
	/**
	 * The column of $db_table that holds the primary key.
	 * @var string
	 */
	var $pk_column = "id";
	
	/**
	 * The primary key of the object.
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * An associative array that maps to column names in the database.
	 * @var array
	 */
	var $_data = array();
	
	/**
	 * An array of the columns (keys in $_data) that have been modified.
	 * @var array
	 * @see Object::$_data
	 */
	var $_modified = array();
	
	/**
	 * The original data.  Allows us to look at values before they were modified.
	 */
	var $_original = array();
	
	/**
	 * Whether the object is new ("insert") or existing ("update"), with regards to the database.
	 * @var string
	 */
	var $_mode = null;
	
	/**
	 * Fields that should be null in the database if they have a false value.
	 * @var array
	 */
	
	var $_null_fields = array();
	
	var $private_fields = array();
	
	var $_cache_safe = false;
	
	public static $CACHE_NO_OBJECT = -1;
	
	/**
	 * Based on the type of $identity, either 
	 *   a) retrieves the object from cache, and failing that, from the DB.
	 *   b) Creates a new object, populating it with the contents of the
	 *	  identity array.
	 *
	 * @param mixed $identity
	 * @param mixed $column
	 */
	
	public function __construct($identity = null, $column = null, $bypass_cache = false, $ignore_db = false) {
		// Defaults to insert mode until a PK is explicity set.
		$this->_mode = "insert";
		
		// If your application does not span multiple data centers, you can set $_cache_safe to always be true
		// and $bypass_cache to always be false, as there's no chance of retrieving stale data from cache.
		$this->_cache_safe = $bypass_cache;
		
		if ($identity !== null) {
			if (is_array($identity) && $column === null) {
				// Either this object doesn't exist in the database or we already had all the information
				// from the database needed to populate the object.
				$this->_mode = "insert";
				
				$this->_cache_safe = true;
				
				// $identity is an associative array; use that as a template for the object.
				foreach ($identity as $key => $val) {
					$this->_data[$key] = $val;
					
					if ($key === $this->pk_column) {
						// This means that the data for the entire object was already available,
						// most like from a SELECT * that returned multiple rows.
						$this->_mode = "update";
						$this->_id = $val;
					}
				}
			}
			else {
				// This means that an existing key was passed in.
				$this->_mode = "update";
			
				if ($column === null) {
					$column = $this->pk_column;
				}
				
				if (!is_array($column)) {
					$identity = array($column => $identity);
					$column = array($column);
				}
				
				sort($column);
				
				$cache_key = $this->db_table;
				
				foreach ($column as $col) {
					$cache_key .= ":" . $col ."=".md5($identity[$col]);
				}
				
				if ($bypass_cache) {
					$row = false;
				}
				else {
					$row = cache_get($cache_key);
				}
				
				if ($row === false) {
					if ($ignore_db) {
						throw new Exception("DoesNotExistInCache");
					}
					
					// Retrieve the object from the database, and then put it into cache.
					$query = "SELECT * FROM `".$this->db_table."` WHERE ";
					
					foreach ($column as $col) {
						$query .= " `".$col."` = '".db_escape($identity[$col])."' AND ";
					}
					
					$query = substr($query, 0, strlen($query) - 5);
					
					$result = db_query($query, $this->db_api, $this->db_host); // @cached

					if (db_num_rows($result) > 0) {
						$row = db_fetch_assoc($result);
						
						if ((count($column) > 1) || ($column[0] != "id")) {
							cache_set($cache_key, $row["id"]);
						}
						else {
							cache_set($cache_key, $row);
						}
						
						if ((count($column) > 1) || ($column[0] != "id")) {
							// Save the extra call to cache or possibly DB.
							return $this->__construct($row);
						}
					}
					else {
						// Caching the fact that this object does not exist.
						$row = Object::$CACHE_NO_OBJECT;
						
						// Cache the fact that this object does not exist.
						// Potential problems:
							// 1. I request an object that doesn't exist.
							// 2. I cache that it doesn't exist.
							// 3. I create the object I really need, somewhere else.
							// 4. I request the object again, but now it does exist.
							// 5. Non-existence is returned.
						// We can fix this by only caching Object::$CACHE_NO_OBJECT when it's a primary key $identity, as this will be overwritten.
						if ((count($column) == 1) && ($column[0] == "id")) {
							cache_set($cache_key, $row);
						}
					}
				}
				
				if ($row != Object::$CACHE_NO_OBJECT && (count($column) > 1 || $column[0] != "id")) {
					// This value should be just the ID.
					return $this->__construct($row);
				}

				if ($row == Object::$CACHE_NO_OBJECT) {
					// Trying to instantiate an object that doesn't exist.
					throw new Exception("DoesNotExist");
				}

				foreach ($row as $key => $val) {
					$this->_data[$key] = $val;
					
					if ($key == $this->pk_column) {
						$this->_id = $val;
					}
				}
			}
		}
		else {
			$this->_cache_safe = true;
		}
		
		$this->_original = $this->_data;
		
		return $this;
	}
	
	/**
	 * A simpler lookup method than providing multiple arrays to __construct
	 */
	
	static function select($params, $bypass_cache = false, $ignore_db = false, $class_name = null) {
		$identity = $params;
		$column = array_keys($params);
		sort($column);
		
		if (function_exists("get_called_class")) {
			$class_name = get_called_class();
		}
		
		eval('$placeholder_object = new '.$class_name.'();');
		
		$cache_key = $placeholder_object->db_table;
		
		foreach ($column as $col) {
			$cache_key .= ":" . $col ."=".md5($identity[$col]);
		}
		
		if ($bypass_cache) {
			$row = false;
		}
		else {
			$row = cache_get($cache_key);
		}
		
		if ($row === false) {
			if ($ignore_db) {
				return false;
				// throw new Exception("DoesNotExistInCache");
			}
		
			// Retrieve the object from the database, and then put it into cache.
			$query = "SELECT * FROM `".$placeholder_object->db_table."` WHERE ";
		
			foreach ($column as $col) {
				$query .= " `".$col."` = '".db_escape($identity[$col])."' AND ";
			}
		
			$query = substr($query, 0, strlen($query) - 5);
			$result = db_query($query, $placeholder_object->db_api, $placeholder_object->db_host); // @cached
			
			if (db_num_rows($result) > 0) {
				$row = db_fetch_assoc($result);
				
				cache_set($cache_key, $row["id"]);
			
				// Save the extra call to cache or possibly DB.
				$object = $placeholder_object->__construct($row, null, $bypass_cache, $ignore_db);
				return $object;
			}
			else {
				// Caching the fact that this object does not exist.
				$row = Object::$CACHE_NO_OBJECT;
			}
		}
		
		if ($row != Object::$CACHE_NO_OBJECT) {
			// This value should be just the ID.
			$object = $placeholder_object->__construct($row, null, $bypass_cache, $ignore_db);
			return $object;
		}
		else {
			// Trying to instantiate an object that doesn't exist.
			// throw new Exception("DoesNotExist");
			return false;
		}
	}
	
	/**
	 * Save any changes that have been made to the object to the database.
	 *
	 * @return int The primary key of the object.
	 */
	
	public function save() {
		if (!$this->_cache_safe) {
			throw new Exception("NotCacheSafe");
		}
		
		$new = intval($this->_mode === "insert");
		$modified = ($this->_mode === "insert") ? array_keys((array) $this->_data) : $this->_modified;
		
		if ($this->_mode === "update") {
			// Only run the query if something has changed.
			if (count($this->_modified) == 1 && ($this->_modified[0] == "modified_date")) {
				$this->_modified = array();
			}
			
			if (count($this->_modified) > 0) {
				$query = "UPDATE `".$this->db_table."` SET ";
				
				foreach ($this->_modified as $field) {
					$value = $this->_data[$field];
					
					$query .= " `".$field."`=";
					
					if (!$value && (in_array($field, $this->_null_fields))) {
						$query .= " NULL, ";
					}
					else {
						$query .= " '".db_escape($value)."', ";
					}
				}
				
				$query = substr($query, 0, strlen($query) - 2);
				$query .= " WHERE `".$this->pk_column."`='".db_escape($this->_id)."'";
				db_query($query, $this->db_api, $this->db_host); // @update
				
				$this->_modified = array();
			}
		}
		else {
			$query = "INSERT INTO `".$this->db_table."` SET ";
			
			foreach ($this->_data as $field => $value) {
				$query .= " `".$field."`=";
				
				if (!$value && in_array($field, $this->_null_fields)) {
					$query .= " NULL, ";
				}
				else {
					$query .= " '".db_escape($value)."', ";
				}
			}
		
			$query = substr($query, 0, strlen($query) - 2);
			$result = db_query($query, $this->db_api, $this->db_host); // @insert
			
			// Now that it's in the database, it switches to be an "update" mode object.
			if (empty($this->_data["id"])) {
				$this->_id = db_insert_id($result);
			}
			
			$this->_mode = "update";
			$this->_modified = array();
			$this->_data["id"] = $this->_id;
			
			/*
			$cache_key = "ids:".$this->db_table;
			$id_list = cache_get($cache_key);
			
			if (is_array($id_list)) {
				$id_list[] = $this->_id;
				
				cache_set($cache_key, $id_list);
			}
			*/
			
			$cache_key = "count:".$this->db_table;
			cache_incr($cache_key);
		}
		
		$this->cache();
		
		$this->call_hooks("save", $new, $modified);
		
		return $this->_id;
	}
	
	/**
	 * Increments a field's value atomically.
	 *
	 * @param string $field The db field to increment.
	 */
	
	public function incr($field, $amount = 1) {
		if (!$this->_cache_safe) {
			throw new Exception("NotCacheSafe");
		}
		
		$amount = intval($amount);
		
		if ($amount) { 
			$q = "UPDATE `".$this->db_table."` SET `".$field."`=`".$field."`+".$amount." WHERE `".$this->pk_column."`='".db_escape($this->_id)."'";
			db_query($q, $this->db_api, $this->db_host); // @update
			
			$this->$field = $this->$field + $amount;
			
			$this->cache();
			
			$this->call_hooks("save", false, array($field));
		}
	}
	
	/**
	 * Decrements a field's value atomically.
	 *
	 * @param string $field The db field to decrement.
	 */
	
	public function decr($field, $amount = 1, $floor = null) {
		if (!$this->_cache_safe) {
			throw new Exception("NotCacheSafe");
		}
		
		$amount = intval($amount);
		
		if ($amount) {
			$q = "UPDATE `".$this->db_table."` SET `".$field."`=`".$field."`-".$amount." WHERE `".$this->pk_column."`='".db_escape($this->_id)."' ";
			
			if (!is_null($floor)) {
				$q .= "AND `".$field."` > ".intval($floor)." ";
			}
			
			db_query($q, $this->db_api, $this->db_host); // @update
			
			$this->$field = $this->$field - $amount;
		
			$this->cache();
			
			$this->call_hooks("save", false, array($field));
		}
	}
	
	/**
	 * Push the object into the cache.
	 *
	 * It doesn't store the object itself in the cache, only the $data array.
	 */
	
	public function cache() {
		$cache_key = $this->db_table . ":id=". md5($this->id);
		cache_set($cache_key, $this->_data);
	}
	
	/**
	 * Remove the object from cache, as well as its page representation.
	 */
	
	public function uncache() {
		$cache_key = $this->db_table . ":id=". md5($this->id);
		cache_delete($cache_key);
	}
	
	/**
	 * Remove the object from that database and cache.
	 */
	
	public function delete() {
		if (!$this->_cache_safe) {
			throw new Exception("NotCacheSafe");
		}
		
		if ($this->_mode === "update") {
			$old_id = $this->_id;
			
			$query = "DELETE FROM ".$this->db_table." WHERE `".$this->pk_column."`='".db_escape($this->_id)."'";
			db_query($query, $this->db_api, $this->db_host); // @delete
			
			$this->_id = null;
			unset($this->_data[$this->pk_column]);
			$this->_mode = "insert";
			
			$cache_key = "count:".$this->db_table;
			cache_decr($cache_key);
		}
		
		$this->uncache();
	}
	
	/**
	 * Retrieve a member variable from the $data array, with some shorthand names.
	 * 
	 * @param string $member The field to retrieve.
	 * @return mixed
	 */
	
	public function __get($member) {
		if ($member === "id") {
			if ($this->_id) {
				return $this->_id;
			}
		}
		
		if (isset($this->_data[$member])) {
			return $this->_data[$member];
		}
		
		return null;
	}
	
	/**
	 * Set a member variable, flagging it to be updated in the database.
	 *
	 * @param string $member The field to set.
	 * @param mixed $value The value to set for $member.
	 */
	
	public function __set($member, $value) {
		if ($member === "email") {
			$value = strtolower($value);
		}
		
		if (!isset($this->_data[$member]) || $this->_data[$member] != $value) {
			if ($member === $this->pk_column && $this->_mode === "update") {
				// You can't change the primary key if it's already been set.
				throw new Exception("Unmutable");
			}
			
			$this->_data[$member] = trim($value);
			
			if ($this->_mode === "update") {
				$this->_modified[] = $member;
			}
		}
		
		if ($member === "id") {
			$this->_id = $this->_data["id"];
		}
	}
	
	public function toJSON() {
		$data = $this->_data;
		
		foreach ($this->private_fields as $field) {
			unset($data[$field]);
		}
		
		$data["id"] = $this->_id;
		
		return $data;
	}
	
	static function add_hook($action, $class, $function) {
		global $object_hooks;
		
		if (!isset($object_hooks[$action])) {
			$object_hooks[$action] = array();
		}
		
		$object_hooks[$action][] = array($class, $function);
	}
	
	public function call_hooks($action, $new, $modified) {
		global $object_hooks;
		
		if (isset($object_hooks[$action])) {
			foreach ($object_hooks[$action] as $hook_data) {
				eval($hook_data[0].'::'.$hook_data[1].'($this, $new, $modified);');
			}
		}
	}
}

$object_hooks = array();

?>