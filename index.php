<?php
/*
	PLUGIN NAME: ERX
	DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
	VERSION: 1.0
	AUTHOR: carl.w.reed@vumc.org
*/

$profiling = time();

# includes
require_once "../../redcap_connect.php";
include_once('vendor/autoload.php');
require_once "base.php";

# for testing on dev:
// $filepath = "C:/pdc_redcap_import.csv";
// $csv = file_get_contents($filepath);

# fetch data to be imported from SFTP server and process it
$host = 'sftp.vumc.org';
$port = 22;
$credFilepath = substr(APP_PATH_DOCROOT, 0, strpos(APP_PATH_DOCROOT, 'www')) . 'credentials' . DIRECTORY_SEPARATOR . 'adherence.txt';
$creds = file_get_contents($credFilepath);

echo "credfilepath: $credFilepath<br />";
echo "creds 5: " . substr($creds, 0, 5) . "<br />";
exit;

preg_match('/user: (.+)$/m', $creds, $matches);
$username = substr($matches[1], 0, 7);
preg_match('/passwd: (.*)$/', $creds, $matches);
$password = $matches[1];
if(!$username or !$password or !$host){
	die("The ErX plugin was not able to find the correct SFTP credentials for the Adherence Intervention Study project. Please contact your REDCap administrator.");
}
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
$filesystem = new Filesystem(new SftpAdapter([
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
	}
}

# process import file contents
processImport($csv);

function processImport($import) {
	if (empty($import) || !$import) {
		die("ErX plugin failed to find the pdc_redcap_import.csv data");
	}
	
	global $profiling;
	
	# project variables
	$project = new \Project(PID);
	$eid = $project->firstEventId;
	$pid = PID;
	
	# $data is what we will send to redcap database
	$data = [];
	# $ignored to keep track of which/why records ignored
	$ignored = [];
	
	# separate import file's string data into lines
	$imported = [];
	$rows = count($rows);
	$row = 0;
	$baseline = false;
	$pdc = false;
	
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $import) as $line) {
		# convert line string to csv array
		$line = str_getcsv($line);
		$row += 1;
		
		# skip header and other non-record lines
		if (!is_numeric(substr($line[0], 0, 1))) continue;
		
		# detect baseline data row
		if ($line[3] and $line[6]) {
			$baseline = &$line;
			continue;
		# pdc data does not have import date
		} else {
			if (!$baseline) continue; # we don't have a baseline data row to pair with this pdc row
			$pdc = &$line;
		}
		# we now have a baseline/pdc row pair to evaluate
		
		# convert dates
		$baseline[3] = (new DateTime($baseline[3]))->format("Y-m-d");
		$baseline[6] = (new DateTime($baseline[6]))->format("Y-m-d");
		$pdc[18] = (new DateTime($pdc[18]))->format("Y-m-d");
		$pdc[19] = (new DateTime($pdc[19]))->format("Y-m-d");
		
		# get record_id for this pair of imported rows
		$rid = $baseline[0];
		
		$newRecord = [
			$eid => [
				"import_date" => $baseline[3],
				"mrn_gpi" => $baseline[4],
				"sex" => $baseline[5],
				"date_birth" => $baseline[6],
				"insurance" => $baseline[8],
				"oop" => $baseline[9],
				"zip" => $baseline[10],
				"med_name" => $baseline[11],
				"clinic" => $baseline[12],
				"clinic_level" => $baseline[13],
				"vsp_pat" => $baseline[14]
			],
			"repeat_instances" => [
				$eid => [
					# "pdc_measurement"
					$pdc[1] => [
						# 1 or int
						$pdc[2] => [
							"last_fill_date" => $$pdc[18],
							"measure_date" => $pdc[19],
							"gap_days" => $pdc[20],
							"pdc_measurement_4mths" => $pdc[21],
							"pdc_measurement_12mths" => $pdc[22],
						]
					]
				]
			]
		];
		
		# get record's existing data if applicable
		$recordData = \REDCap::getData($pid, 'array', $rid);
		
		$saveNeeded = true;
		if (!empty($recordData)) {
			# if already randomized, or newer data present, don't save
			# see if randomized by checking baseline.confirm and baseline.randomization_complete
			if ($recordData[$rid][$eid]["confirm"] == 1 && $recordData[$rid][$eid]["randomization_complete"] == 2) {
				$saveNeeded = false;
				$ignored[$rid] = "randomized already / confirm = 1 and randomization_complete = 2";
			}
			
			# if last_fill_date > import_data, don't save
			$existingFillDate = $recordData[$rid]["repeat_instances"][$eid][$pdc[1]][$pdc[2]]["last_fill_date"];
			if ($existingFillDate >= $pdc[18]) {
				$saveNeeded = false;
				$ignored[$rid] = "existing last_fill_date (" . $existingFillDate . ") is >= import_date (" . $baseline[3].")";
			}
			
			# if existing record has same data as newRecord, don't bother pushing to db
			// if (compareRecords($newRecord, $recordData)) {
				// $saveNeeded = false;
				// $ignored[$rid] = "existing record has same data values as import rows";
			// }
		}
		
		if ($saveNeeded) {
			$data[$rid] = &$newRecord;
		}
		
		# collect next baseline row
		$baseline = false;
		$pdc = false;
	}
	
	# save data to REDCap db
	$results = \REDCap::saveData(
		$project_id = $pid,
		$dataFormat = 'array',
		$data,
		$overwriteBehavior = 'normal',
		$dateFormat = 'YMD',
		$type = 'flat',
		$dataAccessGroup = NULL,
		$dataLogging = TRUE,
		$performAutoCalc = TRUE,
		$commitData = TRUE
	);
	
	# reporting
	$profiling = time() - $profiling;
	echo "<pre>";
	if (empty($results['errors'])) {
		echo "Importing to REDCap project with project ID: " . $pid . "\n";
		echo "Import took a total of $profiling seconds to complete.\n";
		echo "Imported total of " . count($results['ids']) . " records.\n";
		echo "Record IDs imported:\n" . print_r($results['ids'], true) . "\n";
		echo "These (" . count($ignored) . ") records were ignored:\n" . print_r($ignored, true) . "\n";
		if (!empty($results['warnings'])) {
			echo "REDCap reported these warnings when trying to save import data\n" . print_r($results['warnings'], true) . "\n";
			echo "Here is the data sent to REDCap:\n" . print_r($data, true) . "\n";
		}
	} else {
		echo "Import failed. See errors below:\n" . print_r($results['errors'], true) . "\n";
		if (!empty($results['warnings'])) {
			echo "See warnings below:\n" . print_r($results['warnings'], true) . "\n";
		}
		echo "Here is the data sent to REDCap:\n" . print_r($data, true) . "\n";
	}
	echo "</pre>";
}

// function compareRecords($old, $new) {
	// # check baseline values
	// foreach ($old[$eid] as $name => $value) {
		// if ($value != $new[$eid][$name]) {
			// echo "\$value: $value != \$new[\$eid][\$name]: " . $new[$eid][$name] . "<br />";
			// return false;
		// }
	// }
	
	// # check pdc values
	// foreach ($old['repeat_instances'][$eid][$pdc[1]][$pdc[2]] as $name => $value) {
		// if ($value != $new['repeat_instances'][$eid][$pdc[1]][$pdc[2]][$name]) {
			// echo "\$value: $value != \$new['repeat_instances'][\$eid][\$pdc[1]][\$pdc[2]][\$name]: " . $new['repeat_instances'][$eid][$pdc[1]][$pdc[2]][$name] . "<br />";
			// return false;
		// }
	// }
	
	// return true;
// }