<?php
/*
	PLUGIN NAME: erx
	DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
	VERSION: 1.2.0
	AUTHOR: carl.w.reed@vumc.org
*/

require "base.php";
require RC_CONNECT_PATH;
require_once AUTOLOAD_PATH;
\REDCap::logEvent('Running ERX plugin', null, null, null, null, PID);

// file_put_contents("C:/vumc/log.txt", "logging:\n");
echo("<pre>");
function _log($text) {
	// file_put_contents("C:/vumc/log.txt", $text . "\n", FILE_APPEND);	
	echo($text . "\n");
}

function getLabel($field_name, $raw_value) {
	global $project;
	if (empty($project) or empty($project->metadata)) {
		throw new \Exception("Tried to get label without a valid project variable or metadata entry.");
	}
	$field_label_string = $project->metadata[$field_name]["element_enum"];
	$pieces = explode("\\n", $field_label_string);
	$labels = [];
	foreach ($pieces as $piece) {
		$arr = explode(", ", $piece);
		// $labels[$arr[0]] = $arr[1];
		if ($arr[0] == $raw_value)
			return $arr[1];
	}
}
function getRandomRecordID() {
	global $projectRecordList;
	while (true) {
		$rid = mt_rand();
		if(!isset($projectRecordList[$rid])) {
			$projectRecordList[$rid] = (int) $rid;
			return $rid;
		}
	}
}
function importNewPatient(&$row) {
	global $columns;
	global $eid;
	global $baseline_fields;
	global $pdc_fields;
	global $row_index;
	
	$rid = getRandomRecordID();
	$mrn = $row[$columns['mrn']];
	// _log("Importing new patient (MRN: $mrn) / RID:$rid from row pair (row " . ($row_index+1) . " - " . ($row_index+2) . ") to record ID: $rid...");
	
	
	// build array data for saveData
	$data = [];
	$data[$rid] = [];
	$data[$rid][$eid] = [];
	foreach ($baseline_fields as $field) {
		// skip calculated fields
		if ($field == 'age' or $field == 'inclusion_pdc') {
			continue;
		}
		if ($field == "last_fill_date" or $field == "measure_date" or $field == "import_date" or $field == 'date_birth') {
			$data[$rid][$eid][$field] = (new DateTime($row[$columns[$field]]))->format("Y-m-d");
		} else {
			$data[$rid][$eid][$field] = $row[$columns[$field]];
		}
	}
	
	// add record_id value to new record
	$data[$rid][$eid]['record_id'] = $rid;
	
	$data[$rid]["repeat_instances"] = [];
	$data[$rid]["repeat_instances"][$eid] = [];
	$data[$rid]["repeat_instances"][$eid]["pdc_measurement"] = [];
	$data[$rid]["repeat_instances"][$eid]["pdc_measurement"][1] = [];
	foreach ($pdc_fields as $field) {
		// skip calculated fields
		if ($field == 'age' or $field == 'inclusion_pdc') {
			continue;
		}
		if ($field == "last_fill_date" or $field == "measure_date" or $field == "import_date" or $field == 'date_birth') {
			$data[$rid]["repeat_instances"][$eid]["pdc_measurement"][1][$field] = (new DateTime($row[$columns[$field]]))->format("Y-m-d");
		} else {
			$data[$rid]["repeat_instances"][$eid]["pdc_measurement"][1][$field] = $row[$columns[$field]];
		}
	}
	
	// execute save
	$results = \REDCap::saveData(PID, 'array', $data);
	if (!empty($results['errors'])) {
		_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: IMPORT NEW PATIENT FAILED -- ERX plugin couldn't add a new record for this data because REDCap failed to save the data, see errors below:\n" . print_r($results, true));
	} else {
		_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: IMPORT NEW PATIENT SUCCESS");
	}
}
function addPdcInstance(&$row, &$record) {
	global $columns;
	global $eid;
	global $rid;
	global $pdc_fields;
	global $row_index;
	global $new_inst_count;
	global $rp1;
	global $rp2;
	
	$mrn = $row[$columns['mrn']];
	if (empty($record[$rid]["repeat_instances"][$eid]["pdc_measurement"])) {
		_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: ADD PDC INSTANCE FAILED -- ERX plugin couldn't add a PDC instance because it couldn't find existing PDC Measurement instances.");
		return;
	}
	
	$pdc_instances = &$record[$rid]["repeat_instances"][$eid]["pdc_measurement"];
	$instance_id = max(array_keys($pdc_instances));
	$last_instance = &$pdc_instances[$instance_id];
	
	
	// ignore import rows if this matches last PDC instance
	$exact_match = true;
	foreach ($pdc_fields as $field) {
		// skip calculated fields
		if ($field == 'age' or $field == 'inclusion_pdc') {
			continue;
		}
		
		if ($field == "last_fill_date" or $field == "measure_date" or $field == 'import_date' or $field == 'date_birth') {
			$new_date = (new DateTime($row[$columns[$field]]))->format("Y-m-d");
			// _log($last_instance[$field] . ' vs. ' . $new_date);
			if (strcmp($last_instance[$field], $new_date) !== 0) {
				// _log("not exact match -- \$field: $field -- " . $last_instance[$field] . ' -- ' . $new_date);
				$exact_match = false;
				break;
			}
		} else {
			// _log($last_instance[$field] . ' vs. ' . $row[$columns[$field]]);
			if (strcmp($last_instance[$field], $row[$columns[$field]]) !== 0) {
				// _log("not exact match -- \$field: $field -- " . $last_instance[$field] . ' -- ' . $row[$columns[$field]]);
				$exact_match = false;
				break;
			}
		}
	}
	if ($exact_match === true) {
		_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: ADD PDC INSTANCE FAILED -- ERX plugin didn't import this data because it is an exact match of the last PDC Measurement instance for this patient record.");
		return;
	}
	
	$data = [];
	$data[$rid] = [];
	$data[$rid]["repeat_instances"] = [];
	$data[$rid]["repeat_instances"][$eid] = [];
	$data[$rid]["repeat_instances"][$eid]["pdc_measurement"] = [];
	
	$instance_id = $instance_id + 1;
	$data[$rid]["repeat_instances"][$eid]["pdc_measurement"][$instance_id] = [];
	$instance = &$data[$rid]["repeat_instances"][$eid]["pdc_measurement"][$instance_id];
	
	foreach ($pdc_fields as $field) {
		// skip calculated fields
		if ($field == 'age' or $field == 'inclusion_pdc') {
			continue;
		}
		if ($field == "last_fill_date" or $field == "measure_date" or $field == 'import_date' or $field == 'date_birth') {
			$instance[$field] = (new DateTime($row[$columns[$field]]))->format("Y-m-d");
		} else {
			$instance[$field] = $row[$columns[$field]];
		}
	}
	
	// execute save
	$results = \REDCap::saveData(PID, 'array', $data);
	if (!empty($results['errors'])) {
		_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: ADD PDC INSTANCE FAILED -- ERX plugin couldn't add a PDC instance because REDCap failed to save the data, see errors below:\n" . print_r($results, true));
	} else {
		$new_inst_count++;
		_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: ADD PDC INSTANCE SUCCESS.");
	}
}
function copyImportDates() {
	_log("\nNow copying [import_date] to [importdatecopy1] for all records:");
	global $project;
	global $eid;
	unset($params);
	unset($records);
	$params = [
		"project_id" => PID,
		"fields" => ["record_id", "non_adherence", "import_date", "importdatecopy1"]
	];
	$records = \REDCap::getData($params);
	
	// for all records
	foreach($records as $rid => $record) {
		$import_date = null;
		$instances = &$records[$rid]['repeat_instances'][$eid]['pdc_measurement'];
		$non_adherence = $record[$eid]['non_adherence'];
		if ($non_adherence !== "") {
			// get [import_date] value
			foreach($instances as $instance) {
				if (!empty($instance['import_date'])) {
					$import_date = $instance['import_date'];
					break;
				}
			}
			
			// copy to [importdatecopy1]
			if (!empty($import_date)) {
				_log("RECORD #$rid had [non_adherence] raw value == $non_adherence, [import_date] copied to [importdatecopy1]");
				$records[$rid][$eid]['importdatecopy1'] = $import_date;
			} else {
				_log("RECORD #$rid had [non_adherence] raw value == $non_adherence, but [import_date] is blank.");
			}
		} else {
			_log("RECORD #$rid had a blank [non_adherence] value, [import_date] not copied.");
		}
	}
	
	$saved = \REDCap::saveData(PID, 'array', $records);
	if (empty($saved['errors'])) {
		_log("Saved [importdatecopy1] changes successfully.");
	} else {
		_log("There were errors saving changes to [importdatecopy1]:\n" . print_r($saved, true));
	}
}
function getImportData() {
	return file_get_contents("C:/vumc/projects/erx/import 11-22.csv");
	/*
	----
	fetch import file from other server
	----
	*/
	$host = 'sftp.vumc.org';
	$port = 22;
	$creds = file_get_contents(CREDENTIALS_PATH);
	preg_match('/user: (.+)$/m', $creds, $matches);
	$username = substr($matches[1], 0, 7);
	preg_match('/passwd: (.*)$/', $creds, $matches);
	$password = $matches[1];
	if(!$username or !$password or !$host){
		echo("The ErX plugin on " . gethostname() . " was not able to determine the correct SFTP credentials for the Adherence Intervention Study project. Please contact datacore@vumc.org.");
		return;
	}
	$filesystem = new \League\Flysystem\Filesystem(new \League\Flysystem\Sftp\SftpAdapter([
		'host' => $host,
		'port' => $port,
		'username' => $username,
		'password' => $password,
		'privateKey' => null,
		'root' => '/files/',
		'timeout' => 10,
	]));
	$importFilename = 'pdc_redcap_import.csv';

	$contents = $filesystem->listContents(".", true);
	foreach($contents as $i => $file) {
		if($file['path'] == $importFilename){
			$csv = $filesystem->read($file['path']);
			// file_put_contents("C:/vumc/projects/erx/import 11-22.csv", $csv);
			// exit();
			return $csv;
		}
	}
}

/*
----
declare some globals like project, field arrays
----
*/
$project = new \Project(PID);
$eid = $project->firstEventId;
$projectRecordList = \Records::getRecordList(PID);
$baseline_fields = [
	"record_id",
	"mrn",
	"sex",
	"date_birth"
];
$pdc_fields = [
	"import_date",
	"mrn_gpi",
	"med_name",
	"clinic",
	"age",
	"insurance",
	"oop",
	"zip",
	"clinic_level",
	"vsp_pat",
	"inclusion_pdc",
	"last_fill_date",
	"measure_date",
	"gap_days",
	"pdc_measurement_4mths",
	"pdc_measurement_12mths"
];

/*
----
process import text
----
*/

$csv = getImportData();
if (empty($csv)) {
	_log("Failed to get import data from pdc_redcap_import.csv");
	_log("</pre>");
	return;
}
$import_rows = [];
foreach(preg_split("/((\r?\n)|(\r\n?))/", $csv) as $i => $row) {
	// convert line string to csv array
	$import_rows[] = str_getcsv($row);
}
_log("Collected " . count($import_rows) . " rows from $import_filepath\n");

// Columns array will hold [field name] => column number (of import file)
$columns = [];
$new_inst_count = 0;
_log("Processing import by line...");
foreach($import_rows as $row_index => $row) {
	$rp1 = $row_index + 1;
	$rp2 = $rp1 + 1;
	if ($row_index === 0) {
		// handle header
		foreach($row as $column_index => $field_name) {
			$columns[$field_name] = $column_index;
		}
		// _log("Generated columns array: " . print_r($columns, true));
	}
	
	$mrn = null;
	$mrngpi = null;
	$rid = null;
	$mrn = (int) $row[$columns['mrn']];
	if (!empty($mrn) and $mrn > 0) {
		// this is a top row, copy next row information into this row
		foreach($row as $index => $value) {
			if ($value !== 0 and empty($value)) {
				$row[$index] = $import_rows[$row_index+1][$index];
			}
		}
		$mrngpi = $row[$columns['mrn_gpi']];
		
		// fetch record id by MRN from db
		$query = db_query("SELECT * FROM redcap_data WHERE project_id=" . PID . " AND field_name='mrn_gpi' AND value='$mrngpi'");
		while ($db_row = db_fetch_assoc($query)) {
			$rid = (int) $db_row['record'];
		}
		
		if (empty($rid)) {
			// _log("Ignoring an import row pair (row $row_index - " . $row_index+1 . ") for MRN:$mrn since no matching record ID was found in REDCap.");
			importNewPatient($row);
			continue;
		}
		
		// fetch all relevant records from REDCap via getData using record ID
		$record = \REDCap::getData(PID, 'array', $rid);
		$baseline = &$record[$rid][$eid];
		if ((int) $baseline['record_id'] !== $rid) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- Baseline data found is not as expected (no matching record_id field).");
			continue;
		}
		
		// check business logic!
		$randomization_label = getLabel('treat', $baseline['treat']);
		if ($randomization_label == "Group A" or $randomization_label == "Group B") {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient has been randomized into $randomization_label.");
			continue;
		}
		
		// find most recent PDC measurement instance
		if (!isset($record[$rid]["repeat_instances"][$eid]["pdc_measurement"])) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- ERX plugin could find no PDC Measurement instances");
			continue;
		}
		$instance_id = max(array_keys($record[$rid]["repeat_instances"][$eid]["pdc_measurement"]));
		$pdc_instance = $record[$rid]["repeat_instances"][$eid]["pdc_measurement"][$instance_id];
		
		if (!empty($pdc_instance['deceased'])) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient is deceased.");
			continue;
		}
		if ($pdc_instance["pdc_measurement_4mths"] >= 90) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient's last PDC measurement had a [pdc_measurement_4mths] value >= 90.");
			continue;
		}
		if (!empty($pdc_instance["ibd_clinic"])) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This is an IBD patient.");
			continue;
		}
		if (!empty($pdc_instance["vumc_employee"])) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient is a VUMC employee.");
			continue;
		}
		$assess_date = $baseline['expnon_assessdate'];
		if (!empty($assess_date)) {
			$today_date = date("Y-m-d");
			$diff_in_days = round((strtotime($today_date) - strtotime($assess_date))/60/60/24);
			if ($diff_in_days < 30) {
				_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient's expected non-adherence date was less than 30 days ago ([expnon_assessdate] = $assess_date).");
				continue;
			}
		}
		if ($baseline["clinical"] == 7) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient's [clinical] != 7, expected non-adherence due to MD supervision.");
			continue;
		}
		
		if ($baseline["non_adherence"] == 1 and ($baseline["non_adherence_yes"][3] or $baseline["non_adherence_yes"][4])) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient has been discontinued (or there is an incongruency of claims).");
			continue;
		}
		if (!empty($pdc_instance['gap_days']) and $pdc_instance["gap_days"] < 5) {
			_log("ROWS:[$rp1 - $rp2] / MRN:$mrn / RID:$rid / ACTION: Ignore -- This patient has less than 5 gap days ([gap_days]=" . $pdc_instance["gap_days"] . ")");
			continue;
		}
		
		// _log("Adding PDC Measurement instance from row pair (row " . ($row_index+1) . " - " . ($row_index+2) . ") for patient MRN:$mrn / RID:$rid");
		addPdcInstance($row, $record);
	}
}

_log('New instance count: ' . $new_inst_count);
_log("Import successful.");

copyImportDates();
echo("</pre>");