<?php
require "base.php";
require RC_CONNECT_PATH;

file_put_contents("C:/vumc/log.txt", "logging:\n");
function _log($text) {
	file_put_contents("C:/vumc/log.txt", $text . "\n", FILE_APPEND);	
}

$params = [
	"project_id" => PID,
	"fields" => ["record_id", "non_adherence", "import_date", "importdatecopy1", "expnon_assessdate"]
];
$records = \REDCap::getData($params);
$project = new \Project(PID);
$eid = $project->firstEventId;

_log("records:\n" . print_r($records, true));

foreach($records as $rid => $record) {
	$import_date = null;
	$instances = &$records[$rid]['repeat_instances'][$eid]['pdc_measurement'];
	
	if (!empty($record[$eid]['non_adherence'])) {
		// get [import_date] value
		foreach($instances as $instance) {
			if (!empty($instance['import_date'])) {
				$import_date = $instance['import_date'];
				echo ("import date found for record[$rid]:<br>$import_date<br>");
				break;
			}
		}
		
		// copy to [importdatecopy1]
		if (!empty($import_date)) {
			$records[$rid][$eid]['importdatecopy1'] = $import_date;
			$records[$rid][$eid]['expnon_assessdate'] = $import_date;
		}
	}
}

$params = [
	"project_id" => PID,
	"data" => $records,
	"overwriteBehavior" => 'overwrite',
	"commitData" => false
];
$saved = \REDCap::saveData(PID, 'array', [$records[1]], 'overwrite');
echo(print_r($saved, true));