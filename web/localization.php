<?php

function get_locale() {
	if (isset($GLOBALS["LOCALE"])) {
		return $GLOBALS["LOCALE"];
	}
	
	return false;
}

function set_locale($locale_code) {
	if ((!isset($GLOBALS["LOCALE"]) || $locale_code != $GLOBALS["LOCALE"]) && is_valid_locale($locale_code)) {
		$GLOBALS["LOCALE"] = $locale_code;
		load_strings($locale_code);
		
		// Set PHP's locale.
		// setlocale(LC_ALL, $locale_code);
	}
}

function load_strings($locale_code) {
	$default_strings = json_decode(file_get_contents(INCLUDE_PATH . "/_locales/en_US/messages.json"));
	
	if ($locale_code != "en_US" && is_translated_locale($locale_code)) {
		$locale_strings = json_decode(file_get_contents(INCLUDE_PATH . "/_locales/" . $locale_code . "/messages.json"));

		if ($locale_strings) {
			foreach ($locale_strings as $name => $message) {
				$default_strings->{$name} = $message;
			}
		}
	}
	
	$GLOBALS["strings"] = $default_strings;
}

function is_valid_locale($locale_code, $extension_type = null) {
	if ($extension_type) {
		return in_array($locale_code, $GLOBALS["locale_codes"][$extension_type]);
	}
	else {
		foreach ($GLOBALS["locale_codes"] as $codes) {
			if (in_array($locale_code, $codes)) {
				return true;
			}
		}
	}
}

function is_translated_locale($locale_code) {
	return is_dir(INCLUDE_PATH . "/_locales/" . $locale_code . "/");
}

function is_rtl_locale($locale_code) {
	return in_array($locale_code, array("ar", "fa", "ur", "he"));
}

$GLOBALS["locale_codes"] = array(
	"xpi" => array("af", "af_ZA", "ar_SA", "ast_ES", "az_AZ", "be_BY", "bg_BG", "bn_BD", "bn_IN", "br_FR", "ca", "ca_AD", "cs", "cy_GB", "da", "da_DK", "de_AT", "de_CH", "dsb", "dsb_DE", "el", "en_AU", "en_GB", "en_US", "eo", "es", "es_AR", "es_CL", "es_ES", "es_MX", "et", "et_EE", "eu", "eu_ES", "fa", "fa_IR", "fi_FI", "fr", "fr_FR", "fy_NL", "ga_IE", "gd", "gl_ES", "gu_IN", "he", "he_IL", "hi_IN", "hr_HR", "hsb", "hsb_DE", "hu", "hu_HU", "hy", "hy_AM", "id", "id_ID", "is", "is_IS", "ja_JP", "ja_JP_mac", "ka", "ka_GE", "kk_KZ", "km_KH", "kn_IN", "ko", "ko_KR", "kw_GB", "lt", "lt_LT", "lv_LV", "mk_MK", "ml_IN", "mn_MN", "ms_MY", "mt_MT", "nb_NO", "nn_NO", "pa_IN", "pl", "pt", "rm", "ro", "ro_RO", "ru", "si_LK", "sk", "sk_SK", "sl", "sl_SI", "sq", "sq_AL", "sr_CS", "sr_RS", "sv", "ta_IN", "te_IN", "th", "uk", "ur_PK", "uz_UZ", "vi", "vi_VN", "wa_BE", "wo", "yi_YI", "zh", "zh_MY", "zh_SG", "zh_TW"),
	"crx" => array("am", "ar", "bg", "bn", "ca", "cs", "da", "de", "el", "en", "en_GB", "en_US", "es", "es_419", "et", "fi", "fil", "fr", "gu", "he", "hi", "hr", "hu", "id", "it", "ja", "kn", "ko", "lt", "lv", "ml", "mr", "nb", "nl", "or", "pl", "pt", "pt_BR", "pt_PT", "ro", "ru", "sk", "sl", "sr", "sv", "sw", "ta", "te", "th", "tr", "uk", "vi", "zh", "zh_CN", "zh_TW")
);

// Check if the user is on a valid locale subdomain.
$subdomain_parts = explode(".", $_SERVER["SERVER_NAME"]);

if (count($subdomain_parts) > 2) {
	$locale_subdomain = array_shift($subdomain_parts);
	
	$formatted_locale_subdomain = format_locale_code($locale_subdomain);
	
	if (is_valid_locale($formatted_locale_subdomain) && is_translated_locale($formatted_locale_subdomain)) {
		// The user is on a locale subdomain, *and* we have messages in that locale.
		set_locale($formatted_locale_subdomain);
	}
	else {
		header("Location: http://" . implode(".", $subdomain_parts). $_SERVER["REQUEST_URI"], true, 302);
		exit;
	}
}

if (!get_locale() && empty($_COOKIE["lr"]) && isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
	// The user arrived on the default page and we've never redirected them to a locale.
	
	// lr = locale_redirect, but we save 13 bytes on each request.
	setcookie("lr", "1", time() + (60*60*24*30));
	
	$user_locale_codes = parse_accept_language_header($_SERVER["HTTP_ACCEPT_LANGUAGE"]);
	
	foreach ($user_locale_codes as $locale_code => $priority) {
		$locale_code = format_locale_code($locale_code);
		
		if (is_valid_locale($locale_code) && is_translated_locale($locale_code) && $locale_code != 'en-US') {
			
			// Redirect the user to a different locale - this time.
			header("Location: http://" . str_replace("_", "-", $locale_code) . "." . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"], true, 302);
			exit;
		}
	}
}

if (!get_locale()) {
	// en_US is the default locale.
	set_locale("en_US");
}

// Enable a set of non-empty locales for the website.
$GLOBALS["website_locales"] = array("de", "en_US", "es", "ja", "nl", "te");

// Save the user's locale preference.
if (!sess_anonymous() && $_GET["a"] != "api" && get_locale() != $GLOBALS["user"]->preferred_locale) {
	api_user_properties(sess_id(), array("preferred_locale" => get_locale()));
}

?>