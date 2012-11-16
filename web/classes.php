<?php

class Extension extends Object {
	var $db_table = "extensions";
	
	var $_null_fieds = array( "description", "icon", "meta" );
	
	var $_locales = null;
	
	public function __get($member) {
		if ($member === "locales") {
			if ($this->_locales === null) {
				$this->_locales = array();
				
				$query = "SELECT * FROM `locales` WHERE `extension_id`='".db_escape($this->id)."' ORDER BY `locale_code` ASC";
				$result = db_query($query);
				
				while ($row = db_fetch_assoc($result)) {
					$locale = new Locale($row);
					
					if ($locale->locale_code == $this->default_locale) {
						$locale->is_default = 1;
					}
					
					$this->_locales[] = $locale;
				}
			}
			
			return $this->_locales;
		}
		else if ($member === "default_locale_object") {
			$locales = $this->locales;
			
			foreach ($locales as $locale) {
				if ($locale->is_default) {
					return $locale;
				}
			}
			
			return false;
		}
		else if ($member === "url") {
			if ($this->meta) {
				$meta_json = json_decode($this->meta);
				
				if ($meta_json && !empty($meta_json->homepage_url)) {
					return $meta_json->homepage_url;
				}
			}
				
			return false;
		}
		
		return parent::__get($member);
	}
	
	public function save() {
		$now = date("Y-m-d H:i:s");
		
		if (!$this->created_date) {
			$this->created_date = $now;
		}
		
		if (!$this->modified_date || count($this->_modified) > 0) {
			$this->modified_date = $now;
		}
		
		$create_locales = false;
		
		if ($this->_mode === "insert") {
			$create_locales = true;
		}
		
		$update_message_limit = false;
		
		if ($this->_mode === "update" && in_array("message_limit", $this->_modified)) {
			$update_message_limit = true;
		}
		
		$rv = parent::save();
		
		if ($create_locales) {
			foreach ($GLOBALS["locale_codes"][$this->type] as $locale_code) {
				$locale = new Locale();
				$locale->locale_code = $locale_code;
				$locale->extension_id = $this->id;
				$locale->message_limit = $this->message_limit;
				
				if ($this->default_locale == $locale_code) {
					$locale->is_default = 1;
				}
				
				$locale->save(); // @cache-safe
			}
			
			$e = new Event();
			$e->key = $this->id;
			$e->user_id = $this->user_id;
			$e->type = "extension:insert";
			$e->meta = json_encode(array("extension_id" => $this->id));
			$e->save();
		}
		else {
			$e = new Event();
			$e->key = $this->id;
			$e->user_id = $this->user_id;
			$e->type = "extension:update";
			$e->meta = json_encode(array("extension_id" => $this->id));
			$e->save();
		}
		
		if ($update_message_limit) {
			$query = "SELECT * FROM `locales` WHERE `extension_id`='".db_escape($this->id)."'";
			$result = db_query($query);
			
			while ($row = db_fetch_assoc($result)) {
				$locale = new Locale($row);
				$locale->message_limit = $this->message_limit;
				$locale->save(); // @cache-safe
			}
		}
	}
	
	public function permalink() {
		return "/extension/" . $this->id;
	}
	
	public function icon_permalink() {
		return $this->permalink() . "/icon";
	}
	
	public function locale($locale_code) {
		return Locale::select(array("extension_id" => $this->id, "locale_code" => $locale_code));
	}
	
	public function description($locale_code = null) {
		if ($this->type === "crx") {
			if (!$locale_code) $locale_code = get_locale();
		
			if ($this->meta) {
				$meta_json = json_decode($this->meta);
			
				$matches = array();
			
				if ($meta_json && $meta_json->description && preg_match_all("/^__MSG_(.*)__$/Ui", $meta_json->description, $matches)) {
					$description_key = $matches[1][0];
				
					$locale_code = format_locale_code($locale_code);
				
					$description_mindex = MessageIndex::select(array("extension_id" => $this->id, "name" => $description_key, "file" => "messages.json"));
				
					if ($description_mindex) {
						$description_message = Message::select(array("message_index_id" => $description_mindex->id, "locale_code" => $locale_code));
					
						if ($description_message) {
							return $description_message->message;
						}
					}
				}
			}
		}
		
		return $this->description;
	}
	
	public function name($locale_code = null) {
		if ($this->type === "crx") {
			if (!$locale_code) $locale_code = get_locale();
		
			if ($this->meta) {
				$meta_json = json_decode($this->meta);
			
				$matches = array();
			
				if ($meta_json && $meta_json->name && preg_match_all("/^__MSG_(.*)__$/Ui", $meta_json->name, $matches)) {
					$name_key = $matches[1][0];
				
					$locale_code = format_locale_code($locale_code);
				
					$name_mindex = MessageIndex::select(array("extension_id" => $this->id, "name" => $name_key, "file" => "messages.json"));
				
					if ($name_mindex) {
						$name_message = Message::select(array("message_index_id" => $name_mindex->id, "locale_code" => $locale_code));
					
						if ($name_message) {
							return $name_message->message;
						}
					}
				}
			}
		}
		
		return $this->name;
	}
}

class Locale extends Object {
	var $db_table = "locales";
	
	var $_messages = null;
	var $_extension = null;
	var $_files_messages = null;
	
	public function __get($member) {
		if ($member === "files_messages") {
			if ($this->_files_messages === null) {
				$this->_files_messages = new stdClass();
				
				$query = "SELECT `mi`.`name`, `mi`.`file`, `m`.* FROM `message_index` `mi` LEFT JOIN `messages` `m` ON `mi`.`id`=`m`.`message_index_id` WHERE `mi`.`extension_id`='".db_escape($this->extension_id)."' AND `m`.`locale_code`='".db_escape($this->locale_code)."' ORDER BY `mi`.`file` ASC, `mi`.`order` ASC";
				$result = db_query($query);
				
				while ($row = db_fetch_assoc($result)) {
					if (!isset($this->_files_messages->{$row["file"]})) {
						$this->_files_messages->{$row["file"]} = new stdClass();
					}
					
					$name = $row["name"];
					unset($row["name"]);
					
					$message = new Message($row);
					
					$this->_files_messages->{$row["file"]}->{$name} = $message;
				}
			}
			
			return $this->_files_messages;
		}
		else if ($member === "messages") {
			if ($this->_messages === null) {
				$this->_messages = new stdClass();
				
				$query = "SELECT `mi`.`name`, `m`.* FROM `message_index` `mi` LEFT JOIN `messages` `m` ON `mi`.`id`=`m`.`message_index_id` WHERE `mi`.`extension_id`='".db_escape($this->extension_id)."' AND `m`.`locale_code`='".db_escape($this->locale_code)."' ORDER BY `mi`.`order` ASC";
				$result = db_query($query);
				
				while ($row = db_fetch_assoc($result)) {
					$name = $row["name"];
					unset($row["name"]);
					
					$message = new Message($row);
					
					$this->_messages->{$name} = $message;
				}
			}
			
			return $this->_messages;
		}
		else if ($member === "progress") {
			return floor(($this->message_count / $this->message_limit) * 100);
		}
		else if ($member === "extension") {
			if ($this->_extension === null) {
				$this->_extension = new Extension($this->extension_id);
			}
			
			return $this->_extension;
		}
		
		return parent::__get($member);
	}
	
	public static function select($params, $bypass_cache = false, $ignore_db = false) {
		return parent::select($params, $bypass_cache, $ignore_db, "Locale");
	}
	
	public function save() {
		$save_event = false;
		
		if (in_array("message_count", $this->_modified) && $this->message_count == $this->message_limit && !$this->is_default) {
			$save_event = true;
			
			// @todo-hook
			$e = new Event();
			$e->key = $this->extension_id.":".$this->locale_code;
			$e->user_id = sess_id();
			$e->type = "locale:complete";
			$e->meta = json_encode(array("extension_id" => $this->extension_id, "locale_code" => $this->locale_code, "user_id" => sess_id()));
		}
		
		$rv = parent::save();
		
		if ($save_event) {
			$e->save();
		}
	}
	
	public function getMessagesJSON($file = null) {
		if ($file) {
			$locale_messages = $this->files_messages->{$file};
		}
		else {
			$locale_messages = $this->messages;
		}
		
		if (!$this->is_default) {
			$extension = new Extension($this->extension_id);
			$default_locale = $extension->default_locale_object;
			
			if ($file) {
				$default_messages = $default_locale->files_messages->{$file};
			}
			else {
				$default_messages = $default_locale->messages;
			}
			
			foreach ($locale_messages as $name => $message) {
				if ($default_messages->{$name}) {
					if ($default_messages->{$name}->placeholders) {
						$locale_messages->{$name}->placeholders = $default_messages->{$name}->placeholders;
					}
				}
				else {
					unset($locale_messages->{$name});
				}
			}
		}
		
		$locale_messages_better = array();
		
		foreach ($locale_messages as $name => $message) {
			$locale_messages_better[$name] = array(
				"message" => $message->message
			);
			
			if ($this->is_default && $message->description) {
				$locale_messages_better[$name]["description"] = $message->description;
			}
			
			if ($message->placeholders) {
				$locale_messages_better[$name]["placeholders"] = json_decode($message->placeholders);
			}
		}
		
		return json_decode(json_encode($locale_messages_better));
	}
	
	public function message($name, $file = null) {
		if ($file) {
			$messages = $this->files_messages;
			
			if (isset($messages->{$file}->{$name})) {
				return $messages->{$file}->{$name};
			}
		}
		else {
			$messages = $this->messages;
		
			if (isset($messages->{$name})) {
				return $messages->{$name};
			}
		}
		
		return false;
	}
	
	public function permalink() {
		return "/extension/" . $this->extension_id . "/" . $this->locale_code;
	}
}

class MessageIndex extends Object {
	var $db_table = "message_index";
	
	var $_null_fields = array("description", "placeholders", "deleted_date");
	
	public static function select($params, $bypass_cache = false, $ignore_db = false) {
		return parent::select($params, $bypass_cache, $ignore_db, "MessageIndex");
	}
	
	public function save() {
		if (!$this->created_date) {
			$this->created_date = date("Y-m-d H:i:s");
		}
		
		return parent::save();
	}
	
	public function delete() {
		$this->deleted_date = date("Y-m-d H:i:s");
		$this->save(); // @cache-safe
		
		// @todo Remove any Message references to this.
		$query = "SELECT * FROM `messages` WHERE `message_index_id`='".db_escape($this->id)."'";
		$result = db_query($query);
		
		while ($row = db_fetch_assoc($result)) {
			$message = new Message($row);
			$message->delete(); // @cache-safe
		}
	}
}

class Message extends Object {
	var $db_table = "messages";
	
	var $_locale = null;
	var $_extension_id = null;
	var $_name = null;
	
	var $_mindex = null;
	
	public function __get($member) {
		if ($member === "locale") {
			if ($this->_locale === null) {
				$this->_locale = Locale::select(array("extension_id" => $this->extension_id, "locale_code" => $this->locale_code));
			}
			
			return $this->_locale;
		}
		else {
			switch ($member) {
				case "extension_id":
				case "name":
				case "placeholders":
				case "description":
					if ($this->_mindex === null) {
						$this->_mindex = new MessageIndex($this->message_index_id);
					}
					
					return $this->_mindex->{$member};
				break;
			}
		}
		
		return parent::__get($member);
	}
	
	public static function select($params, $bypass_cache = false, $ignore_db = false) {
		return parent::select($params, $bypass_cache, $ignore_db, "Message");
	}
		
	public function save() {
		$check_for_locale = false;
		$reset_other_locales = false;
		$save_history = false;
		
		if ($this->_mode === "insert") {
			// Create the locale, if it doesn't exist.
			$check_for_locale = true;
		}
		
		if ($this->_mode === "update" && in_array("message", $this->_modified) && $this->locale->is_default) {
			// The content of the message has been changed. Reset the other locales that have this message.
			$reset_other_locales = true;
		}
		
		if ($this->_mode === "update" && in_array("user_id", $this->_modified) && in_array("message", $this->_modified)) {
			// A user is changing another user's translation.
			$e = new Event();
			$e->key = $this->locale_code.":".$this->message_index_id;
			$e->type = "message:update";
			$e->user_id = $this->user_id;
			$e->meta = json_encode(
				array(
					"user_id" => $this->user_id,
					"previous_user_id" => $this->_original["user_id"], 
					"message" => $this->message,
					"previous_message" => $this->_original["message"], 
					"locale_code" => $this->locale_code,
					"message_index_id" => $this->message_index_id
				)
			);
			$e->save();
		}
		
		if ($this->_mode === "insert" || in_array("message", $this->_modified)) {
			// Save a log of this change.
			$save_history = true;
		}
		
		$now = date("Y-m-d H:i:s");
		
		if (!$this->created_date) {
			// Check if this ever existed in history before.
			$query = "SELECT `created_date` FROM `message_history` WHERE `message_index_id`='".db_escape($this->message_index_id)."' AND `locale_code`='".db_escape($this->locale_code)."' ORDER BY `created_date` ASC LIMIT 1";
			$result = db_query($query);
			
			if (db_num_rows($result) > 0) {
				$row = db_fetch_assoc($result);
				$this->created_date = $row["created_date"];
			}
			else {
				$this->created_date = $now;
			}
		}
		
		if (!$this->modified_date || $save_history) {
			$this->modified_date = $now;
		}
		
		$rv = parent::save();
		
		if ($save_history) {
			$history = new MessageHistory();
			$history->message_index_id = $this->message_index_id;
			$history->locale_code = format_locale_code($this->locale_code);
			$history->message = $this->message;
			$history->user_id = $GLOBALS["user"]->id;
			$history->save(); // @cache-safe
		}
		
		if ($reset_other_locales) {
			$query = "SELECT * FROM `messages` WHERE `message_index_id`='".db_escape($this->message_index_id)."' AND `id` <> '".db_escape($this->id)."'";
			$result = db_query($query);
			
			while ($row = db_fetch_assoc($result)) {
				$message = new Message($row);
				$message->delete(); // @cache-safe
			}
		}
		
		if ($check_for_locale) {
			$locale = Locale::select(array("extension_id" => $this->extension_id, "locale_code" => $this->locale_code), true);
			$locale->incr("message_count"); // @cache-safe
		}
	}
	
	public function delete() {
		$history = new MessageHistory();
		$history->locale_code = format_locale_code($this->locale_code);
		$history->message_index_id = $this->message_index_id;
		$history->message = "";
		$history->user_id = $GLOBALS["user"]->id;
		$history->save(); // @cache-safe
		
		$locale = Locale::select(array("extension_id" => $this->extension_id, "locale_code" => $this->locale_code), true);
		
		if (!$locale) {
			error_email(print_r( $this->_data, true ), 'classes.php', '525');
			die('{ status : false, msg : "Server error. Try again later. Code: c528" }');
		}
		
		$locale->decr("message_count", 1, 0); // @cache-safe
		
		// If this message is in the default locale, remove any translated messages too.
		if ($locale->is_default) {
			$query = "SELECT * FROM `messages` WHERE `message_index_id`='".db_escape($this->message_index_id)."' AND `id` <> '".db_escape($this->id)."'";
			$result = db_query($query);
			
			while ($row = db_fetch_assoc($result)) {
				$_msg = new Message($row);
				$_msg->delete(); // @cache-safe
			}
		}
		
		$rv = parent::delete();
		
		return $rv;
	}
	
	public function has_history() {
		if ($this->created_date != $this->modified_date) {
			return true;
		}
	}
}

class MessageHistory extends Object {
	var $db_table = "message_history";
	
	public function save() {
		if (!$this->created_date) {
			$this->created_date = date("Y-m-d H:i:s");
		}
		
		return parent::save();
	}
}

class User extends Object {
	var $db_table = "users";
	
	public static $EMAIL_FLAG_EXTENSION_UPDATE = 1;
	public static $EMAIL_FLAG_LOCALE_COMPLETE = 2;
	public static $EMAIL_FLAG_MESSAGE_CHANGE = 4;
	public static $EMAIL_FLAG_EXTENSION_INSERT = 8;
	
	public function __get($member) {
		if ($member === "username") {
			if ($this->_data["username"]) {
				return $this->_data["username"];
			}
			
			$parts = explode("@", $this->email);
			$username = $parts[0];
			
			if (strlen($username) > 8) {
				// return substr($username, 0, 8) . "...";
			}
			
			return $username;
		}
		
		return parent::__get($member);
	}
	
	public function save() {
		if (!$this->created_date) {
			$this->created_date = date("Y-m-d H:i:s");
		}
		
		if (!$this->api_key) {
			$this->reset_api_key();
		}
		
		return parent::save();
	}
	
	public function permalink() {
		return "/member/" . $this->id;
	}
	
	public function reset_api_key() {
		while (true) {
			$this->api_key = random_string(32);
			
			try {
				$other_member = new User($this->api_key, "api_key");
				continue;
			} catch (Exception $e) {
				break;
			}
		}
	}
	
	public function toJSON() {
		return array(
			"id" => $this->id,
			"username" => $this->username,
			"permalink" => $this->permalink()
		);
	}
}

class Event extends Object {
	var $db_table = "events";
	
	public function save() {
		if (!$this->created_date) {
			$this->created_date = date("Y-m-d H:i:s");
		}
		
		return parent::save();
	}
}

?>