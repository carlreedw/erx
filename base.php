<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 11/1/2017
 * Time: 1:37 PM
 */
# Define the environment: options include "DEV", "TEST" or "PROD"
if(!defined("ENVIRONMENT")) {
	if (is_file('/app001/victrcore/lib/Victr/Env.php'))
		include_once('/app001/victrcore/lib/Victr/Env.php');

	if (class_exists("Victr_Env")) {
		$envConf = Victr_Env::getEnvConf();

		if ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_PROD) {
			define("ENVIRONMENT", "PROD");
		} elseif ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_DEV) {
			define("ENVIRONMENT", "TEST");
		}
	} else {
		define("ENVIRONMENT", "DEV");
		define("PID", 16);
	}
}

// # Define REDCap path
// if (ENVIRONMENT == "PROD") {
	// define("DATA_REPORT_PATH","/ori/redcap_plugins/patient_tracking/");
	// define("CONNECT_FILE_PATH", dirname(dirname(dirname(__FILE__))));
	// define("TRACKING_PROJECT_ID","71908");
// }
// elseif (ENVIRONMENT == "TEST") {
	// define("DATA_REPORT_PATH","/ori/redcap_plugins/patient_tracking/");
	// define("CONNECT_FILE_PATH", dirname(dirname(dirname(__FILE__))));
	// define("TRACKING_PROJECT_ID","902");
// }
// else {
	// define("CONNECT_FILE_PATH", dirname(dirname(dirname(__FILE__))));
	// define("TRACKING_PROJECT_ID","47");
	// define("DATA_REPORT_PATH","");
// }
// $userArray = array("moorejr5","ketelcr","pilonba","pemberjs","plaxicj","scottaw"); // NO LONGER USED

// ###################################
// # Section of constant definitions
// ###################################
// define("DATA_REPORT_FILE","report_export.csv"); // Data file that contains end-of-month statistics
// define("FULL_EXPORT_FILE","full_export.csv"); // NO LONGER USED

// define("BASELINE_FORM","baseline_data");
// define("PSYCH_FORM","psychiatric_case_review");
// define("MRN","mrn");
// define("FIRST_NAME","first_name");
// define("LAST_NAME","last_name");
// define("DATE_OF_BIRTH","dob");
// // Define the Treatment Status dropdown field, and its enumerated values
// define("TREATMENT_STATUS","treatment_status");
// define("TREATMENT_ACTIVE","1");
// define("TREATMENT_RELAPSE","2");
// define("TREATMENT_INACTIVE","3");
// define("TREATMENT_LTF","4");
// // Define the Follow Schedule dropdown field, and its enumerated values
// define("FOLLOWUP_SCHEDULE","followup_schedule");
// define("SCHEDULE_1WEEK","1");
// define("SCHEDULE_2WEEK","2");
// define("SCHEDULE_3WEEK","3");
// define("SCHEDULE_1MONTH","4");
// // Define the Psych Flags dropdown field, and its enumerated values
// define("PSYCH_FLAGS","flags");
// define("PSYCH_SAFETY","1");
// define("PSYCH_DISCUSS","2");
// define("PSYCH_BOTH","3");
// define("PSYCH_NO","4");

// define("VISIT_NUMBER","visit_number");
// define("EPISODE_NUMBER","episode_number");
// // Define the FollowUp Contact dropdown field, and its enumerated values
// define("FOLLOWUP_CONTACT_NUMBER","followup_contact_number");
// define("FOLLOWUP_CURRENT","1");
// define("FOLLOWUP_INITIAL","2");
// define("FOLLOWUP_CONTACT","3");
// define("CONTACT_NUMBER","contact_number");
// define("CONTACT_OCCUR","contact_occur");
// // Define the values of 'Yes' and 'No' for all fields of that type in REDCap
// define("YES","1");
// define("NO","2");
// define("CONTACT_DATE","actual_contact_date");
// // Define the Type of Contact dropdown field, and its enumerated values
// define("TYPE_OF_CONTACT","type_contact");
// define("CONTACT_PHONE","1");
// define("CONTACT_GROUP","2");
// define("CONTACT_HOME","3");
// define("CONTACT_CLINIC","4");
// define("CONTACT_OTHER","99");
// define("OTHER_TYPE_OF_CONTACT","other_contact_type");
// define("PHQ9_SCORE","phq9_score");
// define("PHQ9_DATE","phq9_date");
// define("GAD7_SCORE","gad7_score");
// define("GAD7_DATE","gad7_date");
// define("MANAGER_NOTES","manager_notes");
// define("PSYCH_DATE","psych_review_date");

// define("DAST_DATE","dast_date");
// define("ALCOHOL_DATE","alcohol_date");
// define("PTSD_DATE","ptsd_date");
// define("ADHD_DATE","adhd_date");
// define("NICHQ_DATE","nichq_date");
// define("CIDI_DATE","cidi_date");
// define("ACE_DATE","ace_date");
// define("SAFET_DATE","safet_date");

// # Base required files to get REDCap, Plugin Core, and PHPMailer functionality to work.
// require_once(CONNECT_FILE_PATH."/redcap_connect.php");
// require_once(CONNECT_FILE_PATH."/plugins/Core/bootstrap.php");
// require_once("functions.php");

// echo "<link rel='stylesheet' href='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/css/jquery-ui.min.css'>
	// <link rel='stylesheet' href='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/css/jquery-ui.theme.min.css'>
	// <link rel='stylesheet' href='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/css/bootstrap.min.css'>
	// <link rel='stylesheet' href='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/css/bootstrap-theme.min.css'>
	// <link href='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/css/styles.css' rel='stylesheet'>
	// <script type='text/javascript' src='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/js/jquery.min.js'></script>
	// <script type='text/javascript' src='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/js/jquery-ui.min.js'></script>
	// <script type='text/javascript' src='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/js/jquery.tablesorter.min.js'></script>
	// <script type='text/javascript' src='".APP_PATH_WEBROOT_FULL."/plugins/patient_tracking/js/bootstrap.min.js'></script>";

// // Define the Core object, and load the libraries it will be using
// global $Core;
// $Core->Libraries(array("Project","Record","ProjectSet","RecordSet","Passthru", "Metadata"));

// // Names for the three different events in the REDCap project. These MUST MATCH the name of the
// // events in the REDCap project
// define("BASELINE_EVENT","Baseline");
// define("VISIT_EVENT","Visit Surveys");
// define("PSYCH_EVENT","Psych Review");

// // Retrieve the event IDs for the events in the REDCap project with keys of their name (defined above)
// $eventArray = array();
// $sql = "SELECT d2.event_id, d2.descrip
		// FROM redcap_events_arms d
		// JOIN redcap_events_metadata d2
			// ON d.arm_id=d2.arm_id
		// WHERE d.project_id=".TRACKING_PROJECT_ID;
// //echo "$sql<br/>";
// $result = db_query($sql);
// while ($row = db_fetch_assoc($result)) {
	// $eventArray[$row['descrip']] = $row['event_id'];
// }

// // The Plugins Core code treats each event within a project as its own Project Object, so must define each below
// $trackingProject = new \Plugin\Project(TRACKING_PROJECT_ID,$eventArray[BASELINE_EVENT]);
// $visitProject = new \Plugin\Project(TRACKING_PROJECT_ID,$eventArray[VISIT_EVENT]);
// $psychProject = new \Plugin\Project(TRACKING_PROJECT_ID,$eventArray[PSYCH_EVENT]);