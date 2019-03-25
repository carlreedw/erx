<?php
/*
	PLUGIN NAME: erx
	DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
	VERSION: 1.0
	AUTHOR: carl.w.reed@vumc.org
*/

$profiling = time();

# includes
require_once "../../redcap_connect.php";
include_once('vendor/autoload.php');
require_once "base.php";

// # for quicker testing on dev:
// $filepath = "C:/vumc/plugins/erx/pdc_import-1-14.csv";
// $csv = file_get_contents($filepath);

\REDCap::logEvent('Running ERX plugin', null, null, null, null, PID);

# fetch data to be imported from SFTP server and process it
$host = 'sftp.vumc.org';
$port = 22;
$credFilepath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR . 'credentials' . DIRECTORY_SEPARATOR . 'adherence.txt';
$creds = file_get_contents($credFilepath);
preg_match('/user: (.+)$/m', $creds, $matches);
$username = substr($matches[1], 0, 7);
preg_match('/passwd: (.*)$/', $creds, $matches);
$password = $matches[1];
if(!$username or !$password or !$host){
	die("The ErX plugin on " . gethostname() . " was not able to determine the correct SFTP credentials for the Adherence Intervention Study project. Please contact datacore@vumc.org.");
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

# for copying import data to local and diagnostics
// exit($csv);

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
	
	# for diagnostics
	// die("<pre>" . print_r(\REDCap::getData(PID), true) . "</pre>");
	
	# $data is what we will send to redcap database
	$data = [];
	
	# $ignored to keep track of which/why records ignored
	$ignored = [];
	
	# separate import file's string data into lines
	$imported = [];
	$row = 0;
	
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $import) as $line) {
		# convert line string to csv array
		$line = str_getcsv($line);
		$row += 1;
		
		# for quicker dev testing, only process first n rows
		// if ($row == 16) break;
		
		# skip header and other non-record lines
		if (!is_numeric(substr($line[0], 0, 1))) { # header or trash/newline row
			continue;
		} elseif ($line[1] == 'pdc_measurement') { # pdc data row
			$mode = 'pdc';
			# convert dates
			$line[3] = (new DateTime($line[3]))->format("Y-m-d");
			$line[18] = (new DateTime($line[18]))->format("Y-m-d");
			$line[19] = (new DateTime($line[19]))->format("Y-m-d");
		} else { # baseline data row
			$mode = 'baseline';
			# convert dates
			$line[6] = (new DateTime($line[6]))->format("Y-m-d");
		}
		
		# get record id
		$rid = $line[0];
		$recordData = \REDCap::getData($pid, 'array', $rid);
		
		# should we ignore?
		$saveNeeded = true;
		if (!empty($recordData)) {
			# if already randomized, or newer data present, don't save
			# see if randomized by checking baseline.confirm and baseline.randomization_complete
			if ($recordData[$rid][$eid]["confirm"] == 1 && $recordData[$rid][$eid]["randomization_complete"] == 2) {
				$saveNeeded = false;
				$ignored[$rid] = "randomized already: [confirm] = 1, [randomization_complete] == 2";
			}
			
			if ($mode == 'pdc') {
				# if last_fill_date > import_data, don't save
				$existingFillDate = $recordData[$rid]["repeat_instances"][$eid][$line[1]][$line[2]]["last_fill_date"];
				if ($existingFillDate >= $line[18]) {
					$saveNeeded = false;
					$ignored[$rid][$line[2]] = "existing last_fill_date (" . $existingFillDate . ") is >= import last_fill_date (" . $line[18].") for this PDC data";
				}
			}
		}
		
		if ($saveNeeded === true) {
			if ($mode == 'pdc') {
				$data[$rid]['repeat_instances'][$eid][$line[1]][$line[2]] = [
					"import_date" => $line[3],
					"mrn_gpi" => $line[4],
					"oop" => $line[9],
					"med_name" => $line[11],
					"clinic" => $line[12],
					"clinic_level" => $line[13],
					"last_fill_date" => $line[18],
					"measure_date" => $line[19],
					"gap_days" => $line[20],
					"pdc_measurement_4mths" => $line[21],
					"pdc_measurement_12mths" => $line[22]
				];
			} elseif ($mode == 'baseline') {
				$data[$rid] = [];
				$data[$rid]["repeat_instances"] = [];
				$data[$rid][$eid] = [
					"mrn" => $rid,
					"sex" => $line[5],
					"date_birth" => $line[6],
					"insurance" => $line[8],
					"zip" => $line[10],
					"vsp_pat" => $line[14],
					"vumc_employee" => $line[16]
				];
			}
		}
		
		$line = null;
	}
	
	# if all PDC entries ignored, ignore baseline too
	foreach ($data as $rid => $record) {
		if (empty($record["repeat_instances"])) {
			unset($data[$rid]);
		}
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
	ob_start();
	echo "<pre>";
	if (empty($results['errors'])) {
		echo "Importing to REDCap project with project ID: " . $pid . "\n";
		echo "Import took a total of $profiling seconds to complete.\n";
		echo "Imported total of " . count($results['ids']) . " records.\n";
		echo "Record IDs imported:\n" . print_r($results['ids'], true) . "\n";
		echo "These (" . count($ignored) . ") records were ignored:\n" . print_r($ignored, true) . "\n";
		if (!empty($results['warnings'])) {
			echo "REDCap reported these warnings when trying to save import data\n" . print_r($results['warnings'], true) . "\n";
		}
		// echo "Here is the data sent to REDCap:\n" . print_r($data, true) . "\n";
	} else {
		echo "Import failed. See errors below:\n" . print_r($results['errors'], true) . "\n";
		if (!empty($results['warnings'])) {
			echo "See warnings below:\n" . print_r($results['warnings'], true) . "\n";
		}
		// echo "Here is the data sent to REDCap:\n" . print_r($data, true) . "\n";
	}
	echo "</pre>";
	$output = ob_get_contents();
	ob_end_clean();
	
	echo $output;
	
	if ($pid == 77551) {
		$headers = "From: carl.w.reed@vumc.org\r\n" .
		"Reply-To: carl.w.reed@vumc.org\r\n" .
		"X-Mailer: PHP/" . phpversion();
		mail('carl.w.reed@vumc.org', "ErX plugin output", $output, $headers);
	}
}