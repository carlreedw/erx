<?php
require "base.php";
require RC_CONNECT_PATH;

// file_put_contents("C:/vumc/log.txt", "logging:\n");
echo("<pre>");
function _log($text) {
	// file_put_contents("C:/vumc/log.txt", $text . "\n", FILE_APPEND);	
	echo("$text\n");
}

$params = [
	"project_id" => PID,
	"fields" => ["record_id", "non_adherence", "import_date", "importdatecopy1", "expnon_assessdate"]
];
$records = \REDCap::getData($params);
$project = new \Project(PID);
$eid = $project->firstEventId;

// _log("records:\n" . print_r($records, true));

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
			_log("RECORD #$rid had [non_adherence] raw value == $non_adherence, [import_date] copied to [importdatecopy1] and [expnon_assessdate].");
			$records[$rid][$eid]['importdatecopy1'] = $import_date;
			$records[$rid][$eid]['expnon_assessdate'] = $import_date;
		} else {
			_log("RECORD #$rid had [non_adherence] raw value == $non_adherence, but [import_date] is blank.");
		}
	} else {
		_log("RECORD #$rid had a blank [non_adherence] value, [import_date] not copied.");
	}
}

$saved = \REDCap::saveData(
	PID,
	'array',
	$records
);
if (!empty($saved['errors'])) {
	_log(print_r($saved, true));
}
echo("</pre>");