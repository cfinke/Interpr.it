<?php

function stripslashes_deep($value) {
	$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
	return $value;
}

function trim_deep($value) {
	$value = is_array($value) ? array_map('trim_deep', $value) : trim($value);
	return $value;
}

if (get_magic_quotes_gpc()) {
	$_POST = array_map('stripslashes_deep', $_POST);
	$_GET = array_map('stripslashes_deep', $_GET);
	$_COOKIE = array_map('stripslashes_deep', $_COOKIE);
	$_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}

$_POST = array_map('trim_deep', $_POST);
$_GET = array_map('trim_deep', $_GET);
$_COOKIE = array_map('trim_deep', $_COOKIE);
$_REQUEST = array_map('trim_deep', $_REQUEST);

require_once "./vendor/google.openid.php";
require_once "./vendor/orm.php";

require_once "./config.php";
require_once "./util.php";
require_once "./db.php";
require_once "./cache.php";
require_once "./classes.php";
require_once "./api-functions.php";
require_once "./views.php";
require_once "./sessions.php";
require_once "./localization.php";

ob_start();

$meta = array("index" => true);

try {
	switch ($_GET["a"]) {
		case "upload": 
			view_upload();
		break;
		case "home":
			view_homepage();
		break;
		case "extension":
			view_extension($_GET["extension_id"]);
		break;
		case "extension-icon":
			view_extension_icon($_GET["extension_id"]);
		break;
		case "locale":
			$_GET["locale_code"] = format_locale_code($_GET["locale_code"]);
			
			view_locale($_GET["extension_id"], $_GET["locale_code"]);
		break;
		case "terms":
			view_terms();
		break;
		case "api":
			$rv = null;
		
			switch ($_GET["sa"]) {
				case "upload":
					$rv = api_upload($_FILES["package"]);
				break;
				case "download":
					if (!$_GET["extension_id"]) {
						header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
						exit;
					}
					else {
						api_download($_GET["extension_id"]);
					}
				break;
				case "translate":
					if (sess_anonymous()) {
						$rv = array("status" => false, "msg" => __("api_error_noauth_translate"));
					}
					else {
						$rv = api_save_translation($_POST["extension_id"], $_POST["locale_code"], $_POST["name"], $_POST["message"], $_POST["file"]);
					}
				break;
				/*
				case "auto-translate":
					$from_locale_code = format_locale_code($_GET["from_locale_code"]);
					$to_locale_code = format_locale_code($_GET["to_locale_code"]);
					
					if (!is_valid_locale($from_locale_code)) {
						$rv = array("status" => false, "msg" => __("api_error_invalid_parameter", "from_locale_code"));
					}
					else if ($to_locale_code && !is_valid_locale($to_locale_code)) {
						$rv = array("status" => false, "msg" => __("api_error_invalid_parameter", "to_locale_code"));
					}
					else {
						if ($to_locale_code) {
							$rv = api_translate_message($_GET["message"], $from_locale_code, $to_locale_code);
						}
						else {
							$rv = api_translate_message_all($_GET["message"], $from_locale_code);
						}
					}
				break;
				*/
				case "api-key":
					$rv["status"] = false;
				
					if ($_POST["action"] === "reset") {
						if (sess_anonymous()) {
							$rv["msg"] = __("api_error_noauth_reset_key");
						}
						else {
							$rv = api_reset_api_key(sess_id());
						}
					}
				break;
				case "history":
					$rv = api_get_message_history($_GET["extension_id"], $_GET["locale_code"], $_GET["name"]);
				break;
				case "revert":
					if (sess_anonymous()) {
						$rv = array("status" => false, "msg" => __("api_error_noauth_translate"));
					}
					else {
						$rv = api_revert_message($_POST["extension_id"], $_POST["locale_code"], $_POST["name"], $_POST["history_id"]);
					}
				break;
				case "email-updates":
					if (sess_anonymous()) {
						echo "NOAUTH";
						die;
					}
					else {
						$rv = api_email_updates();
					}
				break;
				case "email-preferences":
					if (sess_anonymous()) {
						$rv = array("status" => false, "msg" => __("api_error_invalid_parameter", "user_id"));
					}
					else {
						$rv = api_email_preferences(sess_id(), $_POST);
					}
				break;
			}
		break;
		case "api-docs":
			view_api_docs();
		break;
		case "search":
			view_search();
		break;
		case "signin":
			$cache_key = "google_association_handle";
			
			$association_handle = cache_get($cache_key);
			
			if (!$association_handle) {
				$association_handle = GoogleOpenID::getAssociationHandle();
				cache_set($cache_key, $association_handle, 60 * 60 * 24 * 7);
			}
			
			$return_url = "/signin-return";
			
			if (isset($_GET["next"])) {
				$return_url .= "?next=" . urlencode($_GET["next"]);
			}
			
			$googleLogin = GoogleOpenID::createRequest($return_url, $association_handle, true);
			$googleLogin->redirect();
		break;
		case "signin-return":
			$googleLogin = GoogleOpenID::getResponse();
			
			if ($googleLogin->success()) {
				try {
					$user = new User($googleLogin->email(), "email");
				} catch (Exception $e) {
					$user = new User();
					$user->email = $googleLogin->email();
					$user->save(); // @cache-safe
					
					try {
						// Email the user a welcome email.
						$email_object = array();
						$email_object["to"] = $user->email;
						$email_object["subject"] = __("email_welcome_subject");
					
						ob_start();
						include INCLUDE_PATH . "/templates/email/welcome.php";
						$html = ob_get_clean();
						ob_end_clean();
					
						$email_object["body"] = $html;
					
						$headers = array();
						$headers["From"] = 'welcome@interpr.it';
						
						email($email_object["to"], $email_object["subject"], $email_object["body"], $headers);
					} catch (Exception $e) {
						error_email($e, __FILE__, __LINE__);
					}
				}
				
				$_SESSION["id"] = $user->email;
				
				if (isset($_GET["next"])) {
					$next = preg_replace("%/+%", "/", $_GET["next"]);
					
					header("Location: " . $next);
					exit;
				}
				else {
					header("Location: /");
					exit;
				}
			}
			else {
				header("Location: /");
				exit;
			}
		break;
		case "signout":
			unset($_SESSION["id"]);
			session_destroy();
			$_SESSION = array();
			header("Location: /");
			exit;
		break;
		case "dashboard":
			view_dashboard();
		break;
		default:
			include INCLUDE_PATH . "/templates/404.php";
			die;
		break;
	}
	/*
	
	class User { }
	class Translater extends User { }
	class Developer extends User { }
	
	*/
} catch (Exception $e) {
	/**
	 * Show a generic error page if DEBUG is not on.
	 */

	if (ERROR_EMAILS) {
		error_email($e, __FILE__, __LINE__);
	}
	
	if (DEBUG) {
		throw $e;
	}
	else {
		ob_end_clean();
		
		if ($_GET["format"] === "json") {
			echo json_encode(array("status" => false, "msg" => "We are experiencing technical difficulties."));
		}
		else {
			include INCLUDE_PATH . "/templates/500.php";
		}
		
		die;
	}
}

if (empty($_GET["format"])) {
	$_GET["format"] = "html";
}

if ($_GET["format"] === "image") {
}
else {
	header("Content-Language: ".str_replace("_", "-", get_locale()));
	
	if ($_GET["format"] === "json") {
		header("Content-Type: text/json; charset=utf-8");
		echo convert_unicode_escapes(json_encode($rv));
	}
	else {
		header("Content-Type: text/html; charset=utf-8");
	}
}

ob_end_flush();

?>