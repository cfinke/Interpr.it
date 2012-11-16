<?php

/**
 * Processes a zipped extension and adds it to the database.
 *
 * @param array $file_meta A PHP-style $_FILES["foo"] array.
 * @return array An array with a true/false status flag and optional error message ("msg").
 */

function api_upload($file_meta) {
	$rv = array("status" => false);
	
	if ($file_meta["error"]) {
		$rv["msg"] = __("api_error_upload", $file_meta["error"]);
		return $rv;
	}
	
	if (sess_anonymous()) {
		$rv["msg"] = __("api_error_upload_auth");
		$rv["field"] = "signin";
		return $rv;
	}
	
	$file_path = $file_meta["tmp_name"];
	
	$zip = new ZipArchive();
	$res = $zip->open($file_path);
	
	if ($res !== true) {
		// @todo Check for specific error codes
		// @see http://php.net/manual/en/zip.constants.php
		$rv["msg"] = __("api_error_upload_invalid_zip");
		return $rv;
	}
	
	$tmp_dir = sys_get_temp_dir();
	$tmp_dir = preg_replace("%/$%", "", $tmp_dir);
	
	$unpack_dir = $tmp_dir . "/user-" . sess_id() . "-" . time();
	mkdir($unpack_dir);
	
	$zip->extractTo($unpack_dir);
	$zip->close();
	unset($zip);
	
	// Check if the zip file contained a single top-level directory.
	$dirs = array();
	$files = array();
	
	$unpack_dir_handle = opendir($unpack_dir);
	
	while (($fname = readdir($unpack_dir_handle)) !== false) {
		if ($fname[0] !== "." && $fname[0] !== "_") {
			if (is_dir($unpack_dir . "/" . $fname)) {
				$dirs[] = $fname;
			}
			else {
				$files[] = $fname;
			}
		}
	}
	
	closedir($unpack_dir_handle);
	
	if (count($dirs) === 1 && count($files) == 0) {
		$unpack_dir = $unpack_dir . "/" . $dirs[0];
	}
	
	$extension_type = get_extension_type($unpack_dir);
	
	if ($extension_type === "crx") {
		return api_process_crx_upload($unpack_dir);
	}
	else if ($extension_type === "xpi") {
		return api_process_xpi_upload($unpack_dir);
	}
	else {
		$rv["msg"] = __("api_error_upload_missing_file", "manifest.json");
		return $rv;
	}
}

function get_extension_type($unpack_dir) {
	if (is_file($unpack_dir . "/install.rdf") && is_file($unpack_dir . "/chrome.manifest")) {
		return "xpi";
	}
	else if (is_file($unpack_dir . "/manifest.json")) {
		return "crx";
	}
	
	return false;
}

function api_process_crx_upload($unpack_dir) {
	$rv = array("status" => false);
	
	$manifest_text = file_get_contents($unpack_dir . "/manifest.json");
	$manifest_json = json_decode($manifest_text);
	
	if (!$manifest_json) {
		$rv["msg"] = __("api_error_upload_invalid_manifest");
		return $rv;
	}
	
	if (!isset($manifest_json->default_locale) || !$manifest_json->default_locale) {
		$rv["msg"] = __("api_error_upload_missing_default_locale");
		return $rv;
	}
	
	if (!isset($manifest_json->version)) {
		$rv["msg"] = __("api_error_upload_missing_version");
		return $rv;
	}

	if (!isset($manifest_json->name)) {
		$rv["msg"] = __("api_error_upload_missing_name");
		return $rv;
	}
	
	$default_locale = format_locale_code($manifest_json->default_locale);
	
	if (!is_valid_locale($default_locale, "crx")) {
		$rv["msg"] = __("api_error_upload_invalid_default_locale", $default_locale);
		return $rv;
	}
	
	$name = $manifest_json->name;
	$description = ($manifest_json->description ? $manifest_json->description : null);
	$version = $manifest_json->version;
	
	$icon_data = false;
		
	if (isset($manifest_json->icons)) {
		$largest_icon_size = 0;
		$largest_icon_path = false;
		
		foreach ($manifest_json->icons as $icon_size => $icon_path) {
			if (intval($icon_size) > $largest_icon_size) {
				$largest_icon_path = $icon_path;
			}
		}
		
		if ($largest_icon_path && is_file($unpack_dir . "/" . $largest_icon_path)) {
			$icon_type = array_pop(explode(".", $largest_icon_path));
			
			$icon_data = "data:image/" . $icon_type . ";base64," . base64_encode(file_get_contents($unpack_dir . "/" . $largest_icon_path));
		}
	}
	
	if (!is_dir($unpack_dir . "/_locales")) {
		$rv["msg"] = __("api_error_upload_missing_locales_directory");
		return $rv;
	}
	
	$locale_dir_handle = opendir($unpack_dir . "/_locales");
		
	$locales = array();
	
	while (($locale_subdir = readdir($locale_dir_handle)) !== false) {
		if (is_dir($unpack_dir . "/_locales/" . $locale_subdir)) {
			if ($locale_subdir != "." && $locale_subdir != "..") {
				if (is_file($unpack_dir . "/_locales/" . $locale_subdir . "/messages.json")) {
					$formatted_locale_code = format_locale_code($locale_subdir);
					
					if (is_valid_locale($formatted_locale_code, "crx")) { 
						$messages_text = file_get_contents($unpack_dir . "/_locales/" . $locale_subdir . "/messages.json");
						$messages_text = convert_to_unicode($messages_text);
						$messages_json = json_decode($messages_text);
						
						if (!$messages_json) {
							$rv["msg"] = __("api_error_upload_invalid_messages", $locale_subdir);
							return $rv;
						}
						
						if ($messages_json) {
							$locales[$formatted_locale_code] = array("messages.json" => $messages_json);
						}
					}
				}
			}
		}
	}
	
	closedir($locale_dir_handle);
	
	if (!isset($locales[$default_locale])) {
		$rv["msg"] = __("api_error_upload_missing_default_messages");
		return $rv;
	}
	
	rrmdir($unpack_dir);
	
	$matches = array();
	
	if (preg_match_all("/^__MSG_(.*)__$/Ui", $name, $matches)) {
		$name_key = $matches[1][0];
		
		if (!isset($locales[$default_locale]["messages.json"]->{$name_key}) || !isset($locales[$default_locale]["messages.json"]->{$name_key}->message)) {
			$rv["msg"] = __("api_error_missing_default_locale_message", $name_key);
			return $rv;
		}
		
		$name = $locales[$default_locale]["messages.json"]->{$name_key}->message;
	}
	
	$matches = array();
	
	if (preg_match_all("/^__MSG_(.*)__$/Ui", $description, $matches)) {
		$description_key = $matches[1][0];
		
		if (!isset($locales[$default_locale]["messages.json"]->{$description_key}) || !isset($locales[$default_locale]["messages.json"]->{$description_key}->message)) {
			$rv["msg"] = __("api_error_missing_default_locale_message", $description_key);
			return $rv;
		}
		
		$description = $locales[$default_locale]["messages.json"]->{$description_key}->message;
	}
		
	try {
		$extension = new Extension(array("name" => $name, "user_id" => $GLOBALS["user"]->id), array("name", "user_id"), true);
	} catch (Exception $e) {
		$extension = new Extension();
		$extension->name = $name;
		$extension->user_id = sess_id();
	}
	
	$extension->default_locale = format_locale_code($default_locale);
	$extension->description = $description;
	$extension->version = $version;
	$extension->meta = json_encode($manifest_json);
	$extension->type = "crx";
	
	if ($icon_data) {
		$extension->icon = $icon_data;
	}
	
	$extension->save(); // @cache-safe
	
	return api_update_locales($extension, $locales);
}

function api_process_xpi_upload($unpack_dir) {
	$rv = array("status" => false);
	
	// At this point, install.rdf is guaranteed to be in $unpack_dir.
	$manifest_text = file_get_contents($unpack_dir . "/install.rdf");
	
	require_once INCLUDE_PATH . "/vendor/mozilla.rdf.php";
	
	$rdf = new RdfComponent();
	$manifest_data = $rdf->parseInstallManifest($manifest_text);
	
	$manifest_json = new stdClass();
	$manifest_json->default_locale = "en_US";
	$manifest_json->version = $manifest_data["version"];
	$manifest_json->name = $manifest_data["name"]["en-US"];
	$manifest_json->description = $manifest_data["description"]["en-US"];
	
	if ($manifest_data["homepageURL"]) {
		$manifest_json->homepage_url = $manifest_data["homepageURL"];
	}
	
	if (!$manifest_data) {
		$rv["msg"] = __("api_error_upload_invalid_manifest");
		return $rv;
	}

	if (!isset($manifest_json->default_locale) || !$manifest_json->default_locale) {
		$rv["msg"] = __("api_error_upload_missing_default_locale");
		return $rv;
	}

	if (!isset($manifest_json->version)) {
		$rv["msg"] = __("api_error_upload_missing_version");
		return $rv;
	}

	if (!isset($manifest_json->name)) {
		$rv["msg"] = __("api_error_upload_missing_name");
		return $rv;
	}

	$default_locale = format_locale_code($manifest_json->default_locale);

	if (!is_valid_locale($default_locale, "xpi")) {
		$rv["msg"] = __("api_error_upload_invalid_default_locale", $default_locale);
		return $rv;
	}
	
	// Get locale data from chrome.manifest
	
	// Get locale filenames from default locale.
	
	// Ignore contents.rdf
	
	// Parse DTDs
	// Parse .properties
	// Save comments as descriptions

	$name = $manifest_json->name;
	$description = ($manifest_json->description ? $manifest_json->description : null);
	$version = $manifest_json->version;

	/*
	$icon_data = false;

	if (isset($manifest_json->icons)) {
		$largest_icon_size = 0;
		$largest_icon_path = false;

		foreach ($manifest_json->icons as $icon_size => $icon_path) {
			if (intval($icon_size) > $largest_icon_size) {
				$largest_icon_path = $icon_path;
			}
		}

		if ($largest_icon_path && is_file($unpack_dir . "/" . $largest_icon_path)) {
			$icon_type = array_pop(explode(".", $largest_icon_path));

			$icon_data = "data:image/" . $icon_type . ";base64," . base64_encode(file_get_contents($unpack_dir . "/" . $largest_icon_path));
		}
	}
	*/
	
	// Parse chrome.manifest
	
	if (!is_file($unpack_dir . "/chrome.manifest")) {
		$rv["msg"] = __("api_error_upload_missing_file", "chrome.manifest");
		return $rv;
	}
	
	$chrome_manifest_lines = file($unpack_dir . "/chrome.manifest");
	$chrome_manifest_data = parse_chrome_manifest($chrome_manifest_lines);
	
	$replacements = array();
	
	foreach ($chrome_manifest_data["locales"] as $locale_code => $locale_path) {
		foreach ($replacements as $find => $replace) {
			$chrome_manifest_data["locales"][$locale_code] = $locale_path = str_replace($find, $replace, $locale_path);
		}
		
		if (strpos($locale_path, "jar:") === 0) {
			// Unpack the jar, and replace any references to it in other locale lines.
			$path_parts = explode("!", $locale_path, 2);
			$jar_path = str_replace("jar:", "", $path_parts[0]);
			
			$zip = new ZipArchive();
			$res = $zip->open($unpack_dir . "/" . $jar_path);

			if ($res !== true) {
				// @todo Check for specific error codes
				// @see http://php.net/manual/en/zip.constants.php
				// @todo Reference jar filename here.
				$rv["msg"] = __("api_error_upload_invalid_zip");
				return $rv;
			}
			
			$jar_unpack_dir = $unpack_dir . "/" . $jar_path . ".dir";
			mkdir($jar_unpack_dir);
			
			$zip->extractTo($jar_unpack_dir);
			$zip->close();
			unset($zip);
			
			$replacements["jar:" . $jar_path . "!"] = $jar_path . ".dir";
			$chrome_manifest_data["locales"][$locale_code] = $locale_path = str_replace("jar:" . $jar_path . "!", $jar_path . ".dir", $locale_path);
		}
	}
	
	$locales = array();
	
	foreach ($chrome_manifest_data["locales"] as $locale_code => $locale_path) {
		$formatted_locale_code = format_locale_code($locale_code);
		
		if (is_valid_locale($formatted_locale_code, "xpi")) {
			$locales[$formatted_locale_code] = array();
			
			// Now read up any files in the locale.
			
			$files = get_locale_files($unpack_dir . "/" . $locale_path);
			
			foreach ($files as $file) {
				if (!preg_match("/contents\.rdf$/", $file)) {
					$locales[$formatted_locale_code][$file] = parse_locale_file($unpack_dir . "/" . $locale_path . "/" . $file);
				}
			}
		}
	}
	
	rrmdir($unpack_dir);
	
	// @todo Check each file for unknown formatting and throw errors.
	// @todo Check to make sure en_US locale exists.
	
	// @todo Pull description from localized property files, if available.
	
	try {
		$extension = new Extension(array("name" => $name, "user_id" => $GLOBALS["user"]->id, "type" => "xpi"), array("name", "user_id", "type"), true);
	} catch (Exception $e) {
		$extension = new Extension();
		$extension->name = $name;
		$extension->user_id = sess_id();
		$extension->type = "xpi";
	}
	
	$extension->default_locale = format_locale_code($default_locale);
	$extension->description = $description;
	$extension->version = $version;
	$extension->meta = json_encode($manifest_json);
	
	/*
	if ($icon_data) {
		$extension->icon = $icon_data;
	}
	*/
	
	$extension->save(); // @cache-safe
	
	return api_update_locales($extension, $locales);
}

function get_locale_files($dir, $prefix = "") {
	$files = array();
	
	$dir = preg_replace("%/+$%", "", $dir);
	$prefix = preg_replace("%^/+%", "", $prefix);
	
	$dir_handle = opendir($dir);
	
	while (($fname = readdir($dir_handle)) !== false) {
		if ($fname[0] != "." && $fname[0] != "_") {
			if (is_file($dir . "/" . $fname)) {
				$files[] = ($prefix ? ($prefix . "/") : "") . $fname;
			}
			else if (is_dir($dir . "/" . $fname)) {
				$files = array_merge($files, get_locale_files($dir . "/" . $fname, $prefix . "/" . $fname));
			}
		}
	}
	
	closedir($dir_handle);
	
	return $files;
}

function api_update_locales($extension, $locales) {
	$rv = array("status" => false);
	
	$message_limit = 0;
	
	foreach ($locales[$extension->default_locale] as $file => $messages) {
		foreach ($messages as $message) {
			$message_limit++;
		}
	}
	
	if ($message_limit == 0) {
		$rv["msg"] = __("api_error_upload_empty_default_messages");
		return $rv;
	}
	
	$extension->message_limit = $message_limit;
	$extension->save();
	
	// Process the default locale last so that any changes that trigger removals
	// from other locales aren't overwritten seconds later.
	// Move the default locale to the end of the array.
	$_tmp = $locales[$extension->default_locale];
	unset($locales[$extension->default_locale]);
	$locales[$extension->default_locale] = $_tmp;
	
	foreach ($locales[$extension->default_locale] as $file => $json) {
		$order = 0;
		
		foreach ($json as $name => $data) {
			$mindex = MessageIndex::select(array("extension_id" => $extension->id, "name" => $name, "file" => $file), true);
			
			if (!$mindex) {
				$mindex = new MessageIndex();
				$mindex->extension_id = $extension->id;
				$mindex->name = $name;
				$mindex->file = $file;
			}
			else {
				if ($mindex->deleted_date) {
					$mindex->deleted_date = null;
				}
			}
			
			if (isset($data->placeholders)) {
				$mindex->placeholders = json_encode($data->placeholders);
			}
			else {
				$mindex->placeholders = null;
			}
			
			if (isset($data->description)) {
				$mindex->description = $data->description;
			}
			else {
				$mindex->description = null;
			}
			
			$mindex->order = $order++;
			$mindex->save();
		}
	}
	
	foreach ($locales as $locale_code => $files) {
		$locale = Locale::select(array("extension_id" => $extension->id, "locale_code" => $locale_code), true);
		$locale->message_limit = $message_limit;
		$locale->save(); // @cache-safe
		
		foreach ($files as $file => $json) {
			foreach ($json as $name => $data) {
				$data->message = trim($data->message);
				$name = trim($name);
				
				$mindex = MessageIndex::select(array("extension_id" => $extension->id, "name" => $name, "file" => $file));
				
				if (!$mindex || $mindex->deleted_date) {
					// This entry doesn't exist in the default locale.
					continue;
				}
				
				$message = Message::select(array("locale_code" => $locale_code, "message_index_id" => $mindex->id), true);
				
				if (!$message) {
					$message = new Message();
					$message->message_index_id = $mindex->id;
					$message->locale_code = format_locale_code($locale_code);
				}
				
				$message->user_id = sess_id();
				
				if ($locale_code != $extension->default_locale) {
					if ($data->message != $message->message) {
						// Scenarios:
						// 1. Developer uploads default locale
						// 2. es translator translates all strings.
						// 3. Developer adds more strings to default locale.
						// 4. Developer re-uploads.
						// 5. es strings should stay in-tact, even though they are not in the new upload

						// 1. Developer uploads default locale
						// 2. es translator translates all strings.
						// 3. Developer downloads all locales.
						// 4. es translator notices a mistake, fixes it.
						// 5. Developer re-uploads.
						// 6. es strings on the server should stay in-tact, even though they are different in the upload.

						// Check the history.
						// If the uploaded string is in the message's history, then we can assume that it has changed online since
						// the developer downloaded.
						$query = "SELECT * FROM `message_history` WHERE `message_index_id`='".db_escape($mindex->id)."' AND `locale_code`='".db_escape($locale_code)."' AND `message`='".db_escape($data->message)."'";
						$result = db_query($query);

						if (db_num_rows($result) > 0) {
							continue;
						}
						else {
							// The string was changed offline, perhaps via an email submission to the developer.
						}
					}
				}
				
				$message->message = $data->message;
				$message->save(); // @cache-safe
			}
		}
	}
	
	// Remove any strings no longer in the default locale from the other locales.
	$query = "SELECT * FROM `message_index` WHERE `extension_id`='".db_escape($extension->id)."' AND `deleted_date` IS NULL ORDER BY `file` ASC, `order` ASC";
	$result = db_query($query);

	while ($row = db_fetch_assoc($result)) {
		if (!isset($locales[$extension->default_locale][$row["file"]]->{$row["name"]})) {
			$mindex = new MessageIndex($row);
			$mindex->delete(); // @cache-safe
		}
	}
	
	$rv["status"] = true;
	$rv["extension_id"] = $extension->id;
	
	return $rv;
}

function parse_chrome_manifest($lines) {
	$rv = array("status" => true, "locales" => array());
	
	foreach ($lines as $line) {
		$line = trim($line);
		$line = preg_replace("/\s+/", " ", $line);
		
		$parts = explode(" ", $line);
		
		if ($parts[0] === "locale") {
			$rv["locales"][$parts[2]] = $parts[3];
		}
	}
	
	return $rv;
}

function parse_locale_file($file_path) {
	$file_data = trim(file_get_contents($file_path));
	
	$file_data = convert_to_unicode($file_data);
	
	// Parse the file based on its contents.
	$possible_json = json_decode($file_data);
	
	if ($possible_json) {
		// JSON
		return $possible_json;
	}
	else {
		$locale_data = array();
		
		if (strpos($file_data, "<!ENTITY") !== false) {
			// DTD
			
			// Get rid of comments.
			$file_data = preg_replace("/<!--.*?-->/is", "", $file_data);
			
			$matches = array();
			preg_match_all("/<[^>]+>/Uis", $file_data, $matches);
			
			$entities = $matches[0];
			
			foreach ($entities as $entity) {
				$parsed = parse_dtd_entity($entity);
				
				if ($parsed) {
					$locale_data[$parsed[0]] = array("message" => $parsed[1]);
				}
			}
		}
		else {
			// .properties
			// Multi line comments: /* Text Text Text */
			$file_data = preg_replace("%/\*.*?\*/%is", "", $file_data);
			
			// A trailing backslash "\" as last char in the line continue the string in the following document line.
			$file_data = str_replace("\\\n", "\n", $file_data);
			
			$lines = explode("\n", $file_data);
			
			foreach ($lines as $line) {
				$parsed = parse_property($line);
				
				if ($parsed) {
					$locale_data[$parsed[0]] = array("message" => $parsed[1]);
				}
			}
		}
		
		return json_decode(json_encode($locale_data));
	}
	
	return false;
}

function parse_dtd_entity($entity) {
	$entity = trim($entity);
	
	if ($entity) {
		$entity = preg_replace("/^<!ENTITY\s+/i", "", $entity);
		$entity = preg_replace("/>$/", "", $entity);
		
		$matches = array();
		preg_match_all("/[^\s]+\s/Uis", $entity, $matches);
		
		$name = $matches[0][0];
		$value = str_replace($name, "", $entity);
		
		$name = trim($name);
		$value = trim($value);
		
		$delimiter = $value[0];
	
		$value = substr($value, 1, strlen($value) - 2);
	
		if ($delimiter === '"') {
			$value = str_replace("\\\"", '"', $value);
		}
		else if ($delimiter === "'") {
			$value = str_replace("\\\'", "'", $value);
		}
		
		$value = str_replace("&amp;", "&", $value);
		$value = str_replace("&lt;", "<", $value);
		$value = str_replace("&quot;", '"', $value);
		$value = str_replace("&#037;", "%", $value);
		$value = str_replace("&gt;", ">", $value);
		$value = str_replace("&apos;", "'", $value);
	
		return array($name, $value);
	}
	
	return false;
}

function encode_entity($name, $message) {
	$message_string = $message->message;
	
	$message_string = str_replace("&", "&amp;", $message_string);
	$message_string = str_replace("<", "&lt;", $message_string);
	$message_string = str_replace('"', "&quot;", $message_string);
	$message_string = str_replace("%", "&#037;", $message_string);
	$message_string = str_replace(">", "&gt;", $message_string);
	
	return '<!ENTITY '.$name.' "'.$message_string.'">';
}

function parse_property($line) {
	$line = trim($line);
	
	if ($line) {
		// End of the line comments: // Text Text
		$line = preg_replace("%\s*//.*$%", "", $line);
		
		// Whole line comments (from the first character): 
		if ($line && $line[0] !== "#" && strpos($line, '=') !== false) {
			$parts = explode("=", $line, 2);
			
			$name = trim($parts[0]);
			$value = trim($parts[1]);
			
			$value = str_replace("\\n", "\n", $value);
			$value = str_replace("\\\\", "\\", $value);
			$value = str_replace('\\"', '"', $value);
			$value = str_replace("\\'", "'", $value);
			
			return array($name, $value);
		}
	}
	
	return false;
}

function encode_property($name, $message) {
	$message_string = $message->message;
	
	$message_string = str_replace("\n", "\\n", $message_string);
	$message_string = str_replace("\\", "\\\\", $message_string);
	
	return $name . "=" . $message_string;
}

/**
 * Returns a .zip of the locales for an extension.
 *
 * @param int $extension_id The id of the extension.
 * @return file The .zip of the locales for the extension.
 */

function api_download($extension_id) {
	$extension = new Extension($extension_id);
	
	$locales = $extension->locales;
	
	$zip_location = tempnam(sys_get_temp_dir(), "");
	
	$zip = new ZipArchive();
	$zip->open($zip_location, ZIPARCHIVE::CREATE);
	
	foreach ($locales as $locale) {
		if ($locale->message_count > 0) {
			$directory_name = format_locale_code($locale->locale_code, $extension->type);
			
			$zip->addEmptyDir($directory_name);
			
			$files = $locale->files_messages;
			
			foreach ($files as $file => $messages) {
				$zip->addFromString(
					$directory_name . "/" . $file,
					convert_unicode_escapes(
						format_locale_file($locale->getMessagesJSON($file), $file)
					)
				);
			}
		}
	}
	
	$zip->close();
	
	header("Content-Type: application/zip");
	header("Content-Disposition: attachment; filename=".slugify($extension->name)."-locales.zip");
	header("Pragma: no-cache");
	header("Expires: 0");
	header("Content-Length: " . filesize($zip_location));
	readfile($zip_location);
	exit;
}

function format_locale_file($messages_json, $filename) {
	$extension = array_pop(explode(".", strtolower($filename)));
	
	switch ($extension) {
		case "json":
			return json_format(json_encode($messages_json));
		break;
		case "dtd":
			$rv = "";
		
			foreach ($messages_json as $name => $message) {
				$rv .= encode_entity($name, $message) . "\n";
			}
		
			return trim($rv);
		break;
		case "properties":
			$rv = "";
			
			foreach ($messages_json as $name => $message) {
				$rv .= encode_property($name, $message) . "\n";
			}
			
			return trim($rv);
		break;
		default:
			throw new Exception("InvalidFileType");
		break;
	}
}

/*
function api_unregister_translator($user_id, $locale_code) {
	$rv = array("status" => true);
	
	try {
		$translator = new Translator(array("user_id" => $user_id, "locale_code" => $locale_code), array("user_id", "locale_code"), true);
		$translator->delete(); // @cache-safe
	} catch (Exception $e) {
		// This user was never registered as a translator for this locale.
	}
	
	return $rv;
}

function api_register_translator($user_id, $locale_code) {
	$rv = array("status" => true);
	
	try {
		$translator = new Translator(array("user_id" => $user_id, "locale_code" => $locale_code), array("user_id", "locale_code"));
	} catch (Exception $e) {
		$translator = new Translator();
		$translator->user_id = $user_id;
		$translator->locale_code = format_locale_code($locale_code);
		$translator->save(); // @cache-safe
	}
	
	return $rv;
}
*/

/**
 * Saves a message translation.
 *
 * @param int $extension_id The ID of the extension that carries the message.
 * @param string $locale_code The locale code.
 * @param string $name The name of the message.
 * @param string $aMessage The translated message.
 */

function api_save_translation($extension_id, $locale_code, $name, $aMessage, $file = null) {
	$rv = array("status" => true);
	
	try {
		$extension = new Extension($extension_id);
	} catch (Exception $e) {
		$rv["status"] = false;
		$rv["msg"] = __("api_error_invalid_parameter", "extension_id");
		return $rv;
	}
	
	$locale_code = format_locale_code($locale_code);
	
	if ($locale_code == $extension->default_locale && sess_id() != $extension->user_id) {
		$rv["status"] = false;
		$rv["msg"] = __("api_error_default_locale_modification");
		return $rv;
	}
	
	$parameters = array("extension_id" => $extension_id, "name" => $name);
	
	if ($file) {
		$parameters["file"] = $file;
	}
	
	$mindex = MessageIndex::select($parameters);
	$message = Message::select(array("message_index_id" => $mindex->id, "locale_code" => $locale_code), true);
	
	if ($message) {
		if (!$aMessage) {
			$message->delete(); // @cache-safe
			$rv["msg2"] = "deleted";
			return $rv;
		}
	}
	else {
		$message = new Message();
		$message->message_index_id = $mindex->id;
		$message->locale_code = format_locale_code($locale_code);
	}
	
	// Get the order from the default locale.
	$default_locale = $extension->default_locale_object;
	$default_message = $default_locale->message($name, $file);
	
	if (!$default_message) {
		$rv["status"] = false;
		$rv["msg"] = __("api_error_message_not_in_default_locale");
		return $rv;
	}
	else {
		// Confirm that all placeholders are accounted for.
		$named_placeholders = json_decode($default_message->placeholders);
		
		if ($named_placeholders) {
			foreach ($named_placeholders as $placeholder => $_unused) {
				if (strpos($aMessage, "$" . $placeholder . "$") === false) {
					$rv["status"] = false;
					$rv["msg"] = __("api_error_missing_placeholder", "$". $placeholder . "$");
					
					return $rv;
				}
			}
		}
		
		$matches = array();
		preg_match_all("/\\$[0-9]+/", $default_message->message, $matches);
		
		if (count($matches[0]) > 0) {
			foreach ($matches[0] as $placeholder) {
				if (strpos($aMessage, $placeholder) === false) {
					$rv["status"] = false;
					$rv["msg"] = __("api_error_missing_placeholder", str_replace("$", "\\$", $placeholder));
					
					return $rv;
				}
			}
		}
	}
	
	if ($aMessage) {
		$message->message = $aMessage;
		$message->user_id = sess_id();
		$message->save(); // @cache-safe
	}
	
	return $rv;
}

/**
 * Tries to automatically translate a message based on other messages already translated.
 * 
 * @param string $message The original message.
 * @param string $from_locale_code The original locale code.
 * @param string $to_locale_code The target locale code.
 * @return array An array of possible translations, starting with the most popular. Each 
 *         entry in the array has the properties "message" and "popularity," where popularity 
 *         is the ratio to the most popular message's frequency.
 */

/*
Deprecated
function api_translate_message($message, $from_locale_code, $to_locale_code) {
	$query = "SELECT `t`.`message`, COUNT(`t`.`message`) `frequency` FROM `messages` `o` LEFT JOIN `messages` `t` ON `o`.`extension_id`=`t`.`extension_id` AND `o`.`name`=`t`.`name` AND `o`.`id` <> `t`.`id` WHERE `o`.`locale_code`='".db_escape($from_locale_code)."' AND `t`.`locale_code`='".db_escape($to_locale_code)."' AND `o`.`message` LIKE '".db_escape($message)."' GROUP BY `t`.`message` ORDER BY `frequency` DESC";
	$result = db_query($query);
	
	$rv = array();
	
	$baseline = 0;
	
	while ($row = db_fetch_assoc($result)) {
		if (!$baseline) {
			$baseline = $row["frequency"];
		}
		
		$rv[] = array("message" => $row["message"], "popularity" => number_format($row["frequency"] / $baseline, 2, ".", ""));
	}
	
	return $rv;
}
*/

/**
 * The same as api_translate_message, but checks all locales for possible translations.
 * 
 * @param string $message The original message.
 * @param string $from_locale_code The original locale code.
 * @return array An object, keyed on the locale codes that triggered an automatic translation.
 *         Each property is then an array of possible translations, starting with the most popular. Each 
 *         entry in the array has the properties "message" and "popularity," where popularity 
 *         is the ratio to the most popular message's frequency.
 */
/*
Deprecated
function api_translate_message_all($message, $from_locale_code) {
	$query = "SELECT `t`.`message`, `t`.`locale_code`, COUNT(`t`.`message`) `frequency` FROM `messages` `o` LEFT JOIN `messages` `t` ON `o`.`extension_id`=`t`.`extension_id` AND `o`.`name`=`t`.`name` AND `o`.`id` <> `t`.`id` WHERE `o`.`locale_code`='".db_escape($from_locale_code)."' AND `o`.`message` LIKE '".db_escape($message)."' AND `t`.`locale_code` <> '' GROUP BY `t`.`message`, `t`.`locale_code` ORDER BY `t`.`locale_code` ASC, `frequency` DESC";
	$result = db_query($query);
	$rv = array();
	
	$baseline = 0;
	
	while ($row = db_fetch_assoc($result)) {
		if (!isset($rv[$row["locale_code"]])) {
			$baseline = 0;
			$rv[$row["locale_code"]] = array();
		}
		
		if (!$baseline) {
			$baseline = $row["frequency"];
		}
		
		$rv[$row["locale_code"]][] = array("message" => $row["message"], "popularity" => number_format($row["frequency"] / $baseline, 2, ".", ""));
	}
	
	return $rv;
}
*/
/**
 * Resets a user's API key.
 *
 * @param int $user_id The user ID of the user to reset the key of.
 * @return array An array containing a status and either an error message or the new API key.
 */

function api_reset_api_key($user_id) {
	$rv["status"] = false;
	
	try {
		$user = new User($user_id, "id", true);
	} catch (Exception $e) {
		$rv["msg"] = __("api_error_invalid_parameter", "api_key");
		return $rv;
	}
	
	$user->reset_api_key();
	$user->save(); // @cache-safe
	
	$rv["status"] = true;
	$rv["api_key"] = $user->api_key;
	
	return $rv;
	
}

/**
 * Returns the translation history of a message.
 *
 * @param int $extension_id The extension ID of the message.
 * @param string $locale_code The locale code of the message.
 * @param string $name The name of the message.
 * @return array An array containing a status flag and either an array of history entries or an error message.
 */

function api_get_message_history($extension_id, $locale_code, $name) {
	$rv = array("status" => false);
	
	$locale_code = format_locale_code($locale_code);
	
	$extension = new Extension($extension_id);
	
	if (!is_valid_locale($locale_code, $extension->type)) {
		$rv["msg"] =  __("api_error_invalid_parameter", "locale_code");
		return $rv;
	}
	
	$rv["status"] = true;
	$rv["history"] = array();
	
	$query = "SELECT `mh`.`id` `history_id`, `mh`.`created_date` `date`, `mh`.`user_id`, `mh`.`message` FROM `message_index` `mi` LEFT JOIN `message_history` `mh` ON `mi`.`id`=`mh`.`message_index_id` WHERE `mi`.`extension_id`='".db_escape($extension_id)."' AND `mi`.`name`='".db_escape($name)."' AND `mh`.`locale_code`='".db_escape($locale_code)."' ORDER BY `mh`.`created_date` DESC";
	$result = db_query($query);
	
	while ($row = db_fetch_assoc($result)) {
		$user = new User($row["user_id"]);
		$row["user"] = $user->toJSON();
		unset($row["user_id"]);
		
		$rv["history"][] = $row;
	}
	
	return $rv;
}

/**
 * Reverts a message to a previous state. Basically a shortcut through api_save_translation
 * 
 * @param int $extension_id The extension ID of the message.
 * @param string $locale_code The locale code of the message.
 * @param string $name The name of the message.
 * @param int $history_id The ID of the history entry.
 * @return @see api_save_translation
 */

function api_revert_message($extension_id, $locale_code, $name, $history_id) {
	$rv = array("status" => false);
	
	try {
		$mh = new MessageHistory($history_id);
	} catch (Exception $e) {
		$rv["msg"] = __("api_error_invalid_parameter", "history_id");
		return $rv;
	}
	
	$rv = api_save_translation($extension_id, $locale_code, $name, $mh->message);
	
	if ($rv["status"]) {
		$rv["message"] = $mh->message;
	}
	
	return $rv;
}

/**
 * Edits a user's email preferences.
 */

function api_email_preferences($user_id, $params) {
	$rv = array("status" => false);
	
	try {
		$user = new User($user_id, "id", true);
	} catch (Exception $e) {
		$rv["msg"] = __("api_error_invalid_parameter", "user_id");
		return $rv;
	}
	
	$rv = array("status" => true);
	
	$user->email_preferences = 0;
	
	foreach ($params as $key => $value) {
		switch ($key) {
			case "EXTENSION_UPDATE":
				if ($value) $user->email_preferences += User::$EMAIL_FLAG_EXTENSION_UPDATE;
			break;
			case "LOCALE_COMPLETE":
				if ($value) $user->email_preferences += User::$EMAIL_FLAG_LOCALE_COMPLETE;
			break;
			case "MESSAGE_CHANGE":
				if ($value) $user->email_preferences += User::$EMAIL_FLAG_MESSAGE_CHANGE;
			break;
			case "EXTENSION_INSERT":
				if ($value) $user->email_preferences += User::$EMAIL_FLAG_EXTENSION_INSERT;
			break;
		}
	}
	
	$user->save();
	
	return $rv;
}

function api_user_properties($user_id, $params) {
	$rv = array("status" => false);
	
	try {
		$user = new User($user_id, "id", true);
	} catch (Exception $e) {
		$rv["msg"] = __("api_error_invalid_parameter", "user_id");
		return $rv;
	}
	
	
	if (isset($params["preferred_locale"])) {
		$locale_code = format_locale_code($params["preferred_locale"]);
		
		if (!$locale_code) {
			$rv["msg"] = __("api_error_invalid_parameter", "locale_code");
			return $rv;
		}
		
		$user->preferred_locale = $locale_code;
	}
	
	$user->save();
	
	$rv = array("status" => true);
	return $rv;
}

/**
 * Sends out email updates based on each user's preferences.
 */

function api_email_updates() {
	$rv = array("status" => true);
	
	$query = "SELECT `created_date` FROM `events` WHERE `type`='email' ORDER BY `created_date` DESC LIMIT 1";
	$result = db_query($query);
	
	if (db_num_rows($result) > 0) {
		$row = db_fetch_assoc($result);
		
		$query = "SELECT * FROM `events` WHERE `created_date` > '".db_escape($row["created_date"])."'";
	}
	else {
		$query = "SELECT * FROM `events`";
	}
	
	$query .= " GROUP BY `type`, `key` ORDER BY `created_date` ASC";
	$result = db_query($query);
	
	if (db_num_rows($result) > 0) {
		$e = new Event();
		$e->type = "email";
		$e->user_id = sess_id();
		$e->save();
	
		$emails = array();
	
		while ($row = db_fetch_assoc($result)) {
			$meta = json_decode($row["meta"]);
			
			switch ($row["type"]) {
				case "extension:insert":
					// Alert anyone who asked for notifications on all new extensions
					// except for the user who uploaded it.
					$user_query = "SELECT * FROM `users` WHERE `email_preferences` & ".db_escape(User::$EMAIL_FLAG_EXTENSION_INSERT)." AND `id` <> '".db_escape($row["user_id"])."'";
					$user_result = db_query($user_query);
					
					while ($user_row = db_fetch_assoc($user_result)) {
						$emails[$user_row["id"]][$row["type"]][] = $meta;
					}
				break;
				case "extension:update":
					// Alert anyone who asked for notifications on extension updates
					// when they've contributed to a locale.
					$user_query = "SELECT 
							`u`.*
						FROM `users` `u`
							LEFT JOIN `message_history` `mh` ON `u`.`id`=`mh`.`user_id`
							LEFT JOIN `message_index` `mi` ON `mh`.`message_index_id`=`mi`.`id`
						WHERE `mi`.`extension_id`='".db_escape($meta->extension_id)."'
							AND `u`.`id` <> '".db_escape($row["user_id"])."'
							AND `email_preferences` & ".db_escape(User::$EMAIL_FLAG_EXTENSION_UPDATE)."
						GROUP BY `u`.`id`";
					$user_result = db_query($user_query);
				
					while ($user_row = db_fetch_assoc($user_result)) {
						$emails[$user_row["id"]][$row["type"]][] = $meta;
					}
				break;
				case "locale:complete":
					// Alert anyone who asked for notifications when a locale is completed on their extension.
					$extension = new Extension($meta->extension_id);
					$user = new User($extension->user_id);
					
					if ($user->email_preferences & User::$EMAIL_FLAG_LOCALE_COMPLETE) {
						$emails[$user->id][$row["type"]][] = $meta;
					}
				break;
				case "message:update":
					// Alert anyone who asked for notifications when their translations are changed.
					$user = new User($meta->previous_user_id);
					
					if ($user->email_preferences & User::$EMAIL_FLAG_MESSAGE_CHANGE) {
						$mindex = new MessageIndex($meta->message_index_id);
						$meta->extension_id = $mindex->extension_id;
						$meta->name = $mindex->name;
						
						$emails[$user->id][$row["type"]][] = $meta;
					}
				break;
			}
		}
		
		$email_objects = array();
		
		$default_locale_code = get_locale();
		
		foreach ($emails as $user_id => $events) {
			// Emails default to English, even if the API call is made to a localized subdomain.
			set_locale("en_US");
			
			$user = new User($user_id);
			
			if ($user->preferred_locale) {
				set_locale($user->preferred_locale);
			}
			
			$email_object = array();
			$email_object["to"] = $user->email;
			$email_object["subject"] = __("email_notification_subject");
			
			ob_start();
			include INCLUDE_PATH . "/templates/email/notification.php";
			$html = ob_get_clean();
			ob_end_clean();
			
			$email_object["body"] = $html;
		
			$email_objects[] = $email_object;
		}
		
		set_locale($default_locale_code);
		
		foreach ($email_objects as $email) {
			email($email["to"], $email["subject"], $email["body"]);
		}
	}
	
	return $rv;
}

?>