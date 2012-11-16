<?php

function sess_anonymous() {
	return !isset($GLOBALS["user"]);
}

function sess_id() {
	return $GLOBALS["user"]->id;
}

session_start();

if ($_GET["a"] === "api" && (isset($_POST["api_key"]) || isset($_GET["api_key"]))) {
	if (isset($_POST["api_key"])) {
		$api_key = $_POST["api_key"];
	}
	else {
		$api_key = $_GET["api_key"];
	}
	
	try {
		$GLOBALS["user"] = new User($api_key, "api_key");
	} catch (Exception $e) {
		session_destroy();
		unset($GLOBALS["user"]);
	}
	
	unset($api_key);
}

if (isset($_SESSION["id"])) {
	try {
		$GLOBALS["user"] = new User($_SESSION["id"], "email");
	} catch (Exception $e) {
		unset($_SESSION["id"]);
		session_destroy();
	}
}

?>