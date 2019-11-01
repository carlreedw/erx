<?php
/*
	test the import process with test records, test import file
*/

$profiling = time();
require "../base.php";
require RC_CONNECT_PATH;

function _log($text) {
	file_put_contents("log.txt", $text . "\n", FILE_APPEND);	
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

file_put_contents("log.txt", "logging:\n");
$project = new \Project(PID);

// reset test record data
$test_records = json_decode(file_get_contents("test_records.json"), true);
_log(count($test_records) . " test records created from test_records.json.");

$save_results = \REDCap::saveData(PID, 'array', $test_records, 'overwrite');
_log("Saving test records to REDCap...\n");
if (!empty($save_results['errors'])) {
	_log("save_results:\n" . print_r($save_results, true));
	_log("Errors occured while saving, aborting import.");
	exit("<pre>" . file_get_contents("log.txt") . "</pre>");
}
_log("Save successful.");

$import_filepath = "mock_import.csv";
_log("Fetching import file contents from path: $import_filepath");
$import_file_contents = file_get_contents($import_filepath);

$import_rows = [];
foreach(preg_split("/((\r?\n)|(\r\n?))/", $import_file_contents) as $i => $row) {
	// convert line string to csv array
	$import_rows[] = str_getcsv($row);
}
_log("Collected " . count($import_rows) . " rows from $import_filepath\n");
// _log("import_rows: \n" . print_r($import_rows, true));

// Columns array will hold [field name] => column number (of import file)
$columns = [];
_log("Processing import by line...");
foreach($import_rows as $row_index => $row) {
	if ($row_index === 0) {
		// handle header
		foreach($row as $column_index => $field_name) {
			$columns[$field_name] = $column_index;
		}
		_log("Generated columns array: " . print_r($columns, true));
	}
	
	$mrn = (int) $row[$columns['mrn']];
	if ($mrn > 0) {
		// this is a top row, copy next row information into this row
		foreach($row as $index => $value) {
			if ($value !== 0 and empty($value)) {
				$row[$index] = $import_rows[$row_index+1][$index];
			}
		}
		
		// $row now contains all relevant imported baseline and pdc information
		
		// fetch record id by MRN from db
		$query = db_query("SELECT * FROM redcap_data WHERE project_id=53 AND field_name='mrn' AND value=$mrn");
		while ($row = db_fetch_assoc($query)) {
			$rid = (int) $row['record'];
		}
		
		if (empty($rid)) {
			_log("Ignoring an import row pair (row $row_index - " . $row_index+1 . ") for MRN:$mrn since no matching record ID was found in REDCap.");
			continue;
		}
		
		// fetch all relevant records from REDCap via getData using record ID
		$record = \REDCap::getData(PID, 'array', $rid);
		$eid = array_keys($record[$rid])[0];
		$baseline = &$record[$rid][$eid];
		if ((int) $baseline['record_id'] !== $rid) {
			_log("Ignoring an import row pair (row $row_index - " . $row_index+1 . ") for MRN:$mrn since baseline data found is not as expected (no matching record_id field).");
			continue;
		}
		
		// check business logic!
		$randomization_label = getLabel('treat', $baseline['treat']);
		if ($randomization_label == "Group A" or $randomization_label == "Group B") {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient has been randomized into $randomization_label");
			continue;
		}
		if (!empty($baseline['deceased'])) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient is deceased.");
			continue;
		}
		if ($baseline["pdc_measurement_4mths"] >= 90) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient's [pdc_measurement_4mths] value is >= 90.");
			continue;
		}
		if (!empty($baseline["ibd_clinic"])) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this is an IBD patient.");
			continue;
		}
		if (!empty($baseline["vumc_employee"])) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient is a VUMC employee.");
			continue;
		}
		$assess_date = $baseline['expnon_assessdate'];
		if (!empty($assess_date)) {
			$today_date = date("Y-m-d");
			$diff_in_days = round((strtotime($today_date) - strtotime($assess_date))/60/60/24);
			if ($diff_in_days < 30) {
				_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient's expected non-adherence date was less than 30 days ago ([expnon_assessdate] = $assess_date).");
				continue;
			}
		}
		if (!empty($baseline["vumc_employee"])) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient is a VUMC employee.");
			continue;
		}
		if ($baseline["clinical"] != 7) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient's [clinical] != 7, expected non-adherence due to MD supervision");
			continue;
		}
		if ($baseline["non_adherence"] == 1 and ($baseline["non_adherence_yes"] == 3 or $baseline["non_adherence_yes"] == 4)) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient has been discontinued (incongruency of claims).");
			continue;
		}
		if ($baseline["gap_days"] < 5 and $baseline["gap_days"] !== null) {
			_log("Ignoring an import row pair (row $row_index - " . ($row_index+1) . ") for MRN:$mrn since this patient has less than 5 gap days ([gap_days]=" . $baseline["gap_days"] . ")");
			continue;
		}
		break;
		
		_log("Importing MRN:$mrn");
		
	}	// otherwise skip row as it's handled above ^
}

_log("Import successful.");
exit("<pre>" . file_get_contents("log.txt") . "</pre>");