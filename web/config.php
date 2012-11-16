<?php

ini_set("display_errors", 0); 

define("INCLUDE_PATH", "/path/to/web");

$GLOBALS["DATABASE_SLAVES"] = array(
	// "127.0.0.1"
);

define('DB_SLAVE_NAME', '');
define('DB_SLAVE_USER', '');
define('DB_SLAVE_PASSWORD', '');

$GLOBALS["DATABASE_MASTERS"] = array(
	"127.0.0.1"
);

define('DB_MASTER_NAME', '');
define('DB_MASTER_USER', '');
define('DB_MASTER_PASSWORD', '');

$GLOBALS["MEMCACHE_SERVERS"] = array(
	array("127.0.0.1", "11211")
);

define('MEMCACHE', true);

define('DEBUG', false);
define('ERROR_EMAILS', true);

define('EMAIL_OVERRIDE', '');

define('CONTENT_SERVER', "http://" . $_SERVER["SERVER_NAME"]);
define('HOST', "http://" . $_SERVER["SERVER_NAME"]);

define('ADMIN_EMAIL', 'test@example.com');

?>