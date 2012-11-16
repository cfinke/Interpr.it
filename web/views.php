<?php

/**
 * Homepage. Contains an upload form, a search form, and some stat modules.
 */

function view_homepage() {
	global $meta;
	
	$query = "SELECT * FROM `extensions` ORDER BY `created_date` DESC LIMIT 5";
	$result = db_query($query);
	
	$extensions = array();
	
	while ($row = db_fetch_assoc($result)) {
		$extensions[] = new Extension($row);
	}
	
	$query = "SELECT * FROM `extensions` ORDER BY `modified_date` DESC LIMIT 5"; // WHERE `modified_date` <> `created_date`
	$result = db_query($query);
	
	$latest_updates = array();
	
	while ($row = db_fetch_assoc($result)) {
		$latest_updates[] = new Extension($row);
	}
	
	$cache_key = "ids:top_translators";
	$translator_user_ids = cache_get($cache_key);
	
	if ($translator_user_ids === false) {
		$query = "SELECT 
				`m`.`user_id`,
				COUNT(*) `num` 
			FROM `messages` `m`
				LEFT JOIN `message_index` `mi`
					ON `m`.`message_index_id`=`mi`.`id`
				LEFT JOIN `extensions` `e`
					ON `mi`.`extension_id`=`e`.`id`
			WHERE `m`.`user_id` <> `e`.`user_id`
			GROUP BY `m`.`user_id` 
			ORDER BY `num` DESC 
			LIMIT 5";
		$result = db_query($query);
		
		$translator_user_ids = array();
		
		while ($row = db_fetch_assoc($result)) {
			$translator_user_ids[] = $row["user_id"];
		}
		
		cache_set($cache_key, $translator_user_ids, 60 * 15);
	}
	
	$translators = array();
	
	foreach ($translator_user_ids as $_user_id) {
		$translators[] = new User($_user_id);
	}
	
	$query = "SELECT * FROM `users` ORDER BY `created_date` DESC LIMIT 5";
	$result = db_query($query);
	
	$newest_members = array();
	
	while ($row = db_fetch_assoc($result)) {
		$newest_members[] = new User($row);
	}
	
	$meta["title"] = __("homepage_title");
	$meta["canonical"] = "/";
	
	include INCLUDE_PATH . "/templates/homepage.php";
}

function view_extension($extension_id) {
	global $meta;
	
	try {
		$extension = new Extension($extension_id);
	} catch (Exception $e) {
		include INCLUDE_PATH . "/templates/404.php";
		return;
	}
	
	$breadcrumbs = array(
		"/" => __("homepage_link_label"),
		$extension->permalink() => $extension->name
	);
	
	$meta["title"] = $extension->name;
	$meta["description"] = $extension->description;
	$meta["canonical"] = $extension->permalink();
	
	include INCLUDE_PATH . "/templates/extension.php";
}

function view_locale($extension_id, $locale_code) {
	global $meta;
	
	try {
		$extension = new Extension($extension_id);
	} catch (Exception $e) {
		include INCLUDE_PATH . "/templates/404.php";
		return;
	}
	
	$locale_code = format_locale_code($locale_code);
	
	if (!is_valid_locale($locale_code, $extension->type)) {
		include INCLUDE_PATH . "/templates/404.php";
		return;
	}
	
	$locale = $extension->locale($locale_code);
	
	if ($locale_code != $extension->default_locale) {
		$meta["index"] = false;
	}
	
	$breadcrumbs = array(
		"/" => __("homepage_link_label"),
		$extension->permalink() => $extension->name,
		$extension->permalink() . "/" . $locale_code => $locale_code
	);
	
	$meta["title"] = __("locale_page_title", array($extension->name, locale_code_to_name($locale_code)));
	$meta["canonical"] = $locale->permalink();
	
	include INCLUDE_PATH . "/templates/locale.php";
}

function view_search() {
	global $meta;
	
	$q = $_GET["q"];
	
	if ($q) {
		$query = "SELECT * FROM `extensions` WHERE `name` LIKE '%".  db_escape($q) . "%' OR `description` LIKE '%" . db_escape($q). "%'";
		$result = db_query($query);
	
		$extensions = array();
	
		while ($row = db_fetch_assoc($result)) {
			$extensions[$row["id"]] = new Extension($row);
		}
	
		$search_results = $extensions;
	}
	
	$locales = array();
	
	if ($_GET["locale"]) {
		$locale_code = format_locale_code($_GET["locale"]);
	}
	
	if ($_GET["locale"] && ((count($extensions) > 0) || (!$q))) {
		$search_results = array();
		
		// Now prioritize by those that need work in $_GET["locale"]
		$query = "SELECT `extension_id`, `message_limit` - `message_count` AS `remaining` FROM `locales` WHERE ";
		if (count($extensions) > 0) $query .= " `extension_id` IN ('".implode("','", array_keys($extensions))."') AND ";
		$query .= " `is_default` <> 1 AND `locale_code`='".db_escape(format_locale_code($locale_code))."' ORDER BY `remaining` DESC";
		$result = db_query($query);
		
		while ($row = db_fetch_assoc($result)) {
			$locales[] = $row;
		}
		
		function sorter($a, $b) {
			if ($a["remaining"] > $b["remaining"]) {
				return -1;
			}
			
			return 1;
		}
		
		usort($locales, "sorter");
		
		foreach ($locales as $row) {
			$extension = new Extension($row["extension_id"]);
			$extension->pending_messages = $row["remaining"];
			$search_results[] = $extension;
		}
		
		$show_translator_actions = true;
		
		$locale_code = $_GET["locale"];
	}
	
	$meta["title"] = __("search_results_title", san($q));
	
	$breadcrumbs = array("/" => __("homepage_link_label"), "/search?q=".urlencode($q) . ($_GET["locale"] ? "&amp;locale=" . urlencode($_GET["locale"]) : "") => __("search_results_title", san($q)));
	
	include INCLUDE_PATH . "/templates/search.php";
}

function view_upload() {
	global $meta;
	
	require_login();
	
	if (isset($_FILES["package"])) {
		$rv = api_upload($_FILES["package"]);
		
		if ($rv["status"]) {
			$extension = new Extension($rv["extension_id"]);
			
			header("Location: ".$extension->permalink());
			exit;
		}
		else {
			$error = $rv["msg"];
		}
	}
	
	$meta["title"] = __("upload_page_title");
	$meta["canonical"] = "/upload";
	
	$breadcrumbs = array("/" => __("homepage_link_label"), "/upload" => __("upload_page_link_label"));
	
	include INCLUDE_PATH . "/templates/upload.php";
}

function view_extension_icon($extension_id) {
	try {
		$extension = new Extension($extension_id);
	} catch (Exception $e) {
		include INCLUDE_PATH . "/templates/404.php";
		return;
	}
	
	if ($extension->icon) {
		$icon_data = explode(",", $extension->icon, 2);
		$mime_data = explode(";", $icon_data[0]);
		$mime_type = str_replace("data:", "", $mime_data[0]);
		
		header("Content-Type: " . $mime_type);
		echo base64_decode($icon_data[1]);
		exit;
	}
	else {
		include INCLUDE_PATH . "/templates/404.php";
		return;
	}
}

function view_api_docs() {
	global $meta;
	
	$breadcrumbs = array("/" => __("homepage_link_label"), "/api" => __("api_docs_page_title"));
	
	$meta["title"] = __("api_docs_page_title");
	$meta["canonical"] = "/api";
	
	include INCLUDE_PATH . "/templates/api.php";
}

function view_dashboard() {
	global $meta;
	
	require_login();
	
	$user_id = sess_id();

	try {
		$user = new User($user_id);
	} catch (Exception $e) {
		include INCLUDE_PATH . "/templates/404.php";
		exit;
	}
	
	$query = "SELECT * FROM `extensions` WHERE `user_id`='".db_escape($user_id)."' ORDER BY `name` ASC";
	$result = db_query($query);
	
	$extensions = array();
	
	while ($row = db_fetch_assoc($result)) {
		$extensions[] = new Extension($row);
	}

	$query = "SELECT `mi`.`extension_id`, `m`.`locale_code` FROM `message_index` `mi` LEFT JOIN `messages` `m` ON `mi`.`id`=`m`.`message_index_id` WHERE `m`.`user_id`='".db_escape($user_id)."' GROUP BY `mi`.`extension_id`, `m`.`locale_code` ORDER BY `m`.`created_date` DESC";
	$result = db_query($query);
	
	$locales = array();
	
	while ($row = db_fetch_assoc($result)) {
		$locale = Locale::select(array("extension_id" => $row["extension_id"], "locale_code" => $row["locale_code"]));
		
		$locales[] = $locale;
	}
	
	$breadcrumbs = array(
		"/" => __("homepage_link_label")
	);
	
	if (sess_id() == $user_id) {
		$breadcrumbs["/dashboard"] = __("dashboard_page_title");
		$meta["title"] = __("dashboard_page_title");
		$meta["canonical"] = "/dashboard";
	}
	else {
		$breadcrumbs["/member/" . $user_id] = san($user->username);
		$meta["title"] = __("member_page_title", $user->username);
		$meta["canonical"] = $user->permalink();
	}
	
	include INCLUDE_PATH . "/templates/dashboard.php";
}

function view_terms() {
	global $meta;
	
	$breadcrumbs = array(
		"/" => __("homepage_link_label"),
		"/terms" => __("terms_of_use")
	);
	
	$meta["title"] = __("terms_of_use");
	$meta["canonical"] = "/terms";
	
	include INCLUDE_PATH . "/templates/terms.php";
}

?>