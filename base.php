<?php
if(!defined("ENVIRONMENT")) {
	if (is_file('/app001/victrcore/lib/Victr/Env.php'))
		include_once('/app001/victrcore/lib/Victr/Env.php');

	if (class_exists("Victr_Env")) {
		$envConf = Victr_Env::getEnvConf();

		if ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_PROD) {
			define("ENVIRONMENT", "PROD");
			define("PID", 77551);
			define("LOG_PATH", "/app001/www/redcap/plugins/erx/erx_cron_log.log");
			define("DATA_FILE_PATH", "\\bigdatavuhcifs.mc.vanderbilt.edu\phr\LIBRARY\Outpatient\SPECIALTY PHARMACY SERVICES\AdherenceClinic\pdc_redcap_import.csv");
		} elseif ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_DEV) {
			define("ENVIRONMENT", "TEST");
			define("PID", 1147);
			define("LOG_PATH", "/app001/www/redcap/plugins/erx/erx_cron_log.log");
			define("DATA_FILE_PATH", "\\bigdatavuhcifs.mc.vanderbilt.edu\phr\LIBRARY\Outpatient\SPECIALTY PHARMACY SERVICES\AdherenceClinic\pdc_redcap_import.csv");
		}
	} else {
		define("ENVIRONMENT", "DEV");
		define("PID", 14);
		define("LOG_PATH", dirname(__FILE__) . "/erx_cron_log.log");
		define("DATA_FILE_PATH", dirname(__FILE__) . "/sample_pdc_redcap_import.csv");
	}
}