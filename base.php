<?php
if(!defined("ENVIRONMENT")) {
	include_once(dirname(__DIR__)."/Core/bootstrap.php");
    $GLOBALS["Core"]->getEnvironment();

    if (ENVIRONMENT == "PROD") {
		define("PID", 96070);
		// define("PID", 77551);
		// define("PID", 92938);	// test project on prod");
	    define("CREDENTIALS_PATH", "/app001/credentials/adherence.txt");
	    define("RC_CONNECT_PATH", "/app001/www/redcap/redcap_connect.php");
	    define("AUTOLOAD_PATH", "/app001/www/redcap/plugins/erx/vendor/autoload.php");
	} elseif (ENVIRONMENT == "TEST") {
		define("PID", 1307);
	    define("CREDENTIALS_PATH", "/app001/credentials/adherence.txt");
	    define("RC_CONNECT_PATH", "/app001/www/redcap/redcap_connect.php");
	    define("AUTOLOAD_PATH", "/app001/www/redcap/plugins/erx/vendor/autoload.php");
	}
	else {
		// define("PID", 32);	// @able
		define("CREDENTIALS_PATH", "C:\\xampp\\credentials\\adherence.txt");
		define("RC_CONNECT_PATH", "C:\\xampp\\htdocs\\redcap\\redcap_connect.php");
		define("AUTOLOAD_PATH", "C:\\xampp\\htdocs\\redcap\\plugins\\erx\\vendor\\autoload.php");
		define("PID", 53);
	}
}
