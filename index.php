<?php
/*
	PLUGIN NAME: erx
	DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
	VERSION: 1.0
	AUTHOR: carl.w.reed@vumc.org
*/

$profiling = time();

# includes
if (file_exists("base.php")) {
	require_once "base.php";
} else {
	require_once "/app001/www/redcap/plugins/erx/base.php";
}
require_once RC_CONNECT_PATH;
include_once AUTOLOAD_PATH;
// use League\Flysystem\Filesystem;
// use League\Flysystem\Sftp\SftpAdapter;

file_put_contents('log.txt', "new log\r\n");

function localLog($txt) {
	file_put_contents('log.txt', $txt . "\r\n", FILE_APPEND);
}

$useLocalImportFile = false;
if ($useLocalImportFile) {
	// for quicker testing on dev:
	$filepath = "C:/vumc/plugins/erx/testImport1.csv";
	$csv = file_get_contents($filepath);
	
	localLog("Pulling test import from filepath: $filepath");
	
	// for diagnostics
	// exit($csv);
} else {
	\REDCap::logEvent('Running ERX plugin', null, null, null, null, PID);

	// fetch data to be imported from SFTP server and process it
	$host = 'sftp.vumc.org';
	$port = 22;
	$creds = file_get_contents(CREDENTIALS_PATH);
	preg_match('/user: (.+)$/m', $creds, $matches);
	$username = substr($matches[1], 0, 7);
	preg_match('/passwd: (.*)$/', $creds, $matches);
	$password = $matches[1];
	if(!$username or !$password or !$host){
		die("The ErX plugin on " . gethostname() . " was not able to determine the correct SFTP credentials for the Adherence Intervention Study project. Please contact datacore@vumc.org.");
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
			// file_put_contents("C:/temp/new_pdc_import.csv", $csv);
			// exit();
		}
	}
}

echo("<pre>");

// process import file contents
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
	$rid = 1;
	
	# for diagnostics
	// die("<pre>" . print_r(\REDCap::getData(PID), true) . "</pre>");
	
	# $data is what we will send to redcap database
	$data = [];
	
	# $ignored to keep track of which/why records ignored
	$ignored = [];
	
	# separate import file's string data into lines
	$imported = [];
	$lines = [];
	
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $import) as $line) {
		// convert line string to csv array
		$lines[] = str_getcsv($line);
	}
	foreach($lines as $lineIndex => $line) {
		# for quicker dev testing, only process first n rows
		// if ($lineIndex == 16) break;
		
		// skip header row
		if ($lineIndex == 0) continue;
		
		// add pdc measurement information to baseline info $line
		if (is_numeric($line[1])) {
			$mode = 'baseline';
			$nextLine = $lines[$lineIndex+1];
			for ($i = 0; $i <= 21; $i++) {
				if ($line[$i] == null) $line[$i] = $lines[$lineIndex+1][$i];
			}
		} else {
			continue;
		}
		
		// convert dates
		if ($line[4]) $line[4] = (new DateTime($line[4]))->format("Y-m-d");
		if ($line[9]) $line[9] = (new DateTime($line[9]))->format("Y-m-d");
		if ($line[17]) $line[17] = (new DateTime($line[17]))->format("Y-m-d");
		if ($line[18]) $line[18] = (new DateTime($line[18]))->format("Y-m-d");
		
		# skip header and other non-record lines
		// if ($line[2] == 'pdc_measurement') { # pdc data row
			// $mode = 'pdc';
			// convert dates
			// $line[4] = (new DateTime($line[4]))->format("Y-m-d");
			// $line[17] = (new DateTime($line[17]))->format("Y-m-d");
			// $line[18] = (new DateTime($line[18]))->format("Y-m-d");
		// } elseif (is_numeric($line[1])) { # baseline data row
		
		// see if this data is already in REDCap project
		$mrn = $line[1];
		$recordDataParams = [
			'project_id' => $pid,
			'filterLogic' => "[mrn]=\"$mrn\""
		];
		$recordData = \REDCap::getData($recordDataParams);
		
		// print_r($recordData);
		echo("\n");
		echo("eid: $eid\n");
		
		// re-fetching by rid is necessary to capture repeated instances data
		$foundRecordID = current($recordData)[$eid]['record_id'];
		$recordData = \REDCap::getData($pid, 'array', $foundRecordID);
		
		// echo("<pre>");
		// echo("recordDataParams:\n");
		echo("found record ID: " . $foundRecordID . "\n");
		print_r($recordData);
		// echo("\$rid: $rid");
		// echo("\n");
		// print_r($recordData);
		echo("</pre>");
		exit();
		
		// should we ignore?
		$saveNeeded = true;
		if (!empty($recordData)) {
			localLog("Found record data for record ID: $foundRecordID -- MRN: $mrn");
			echo("record data found for RID: $foundRecordID\r\n");
			// if already randomized, or newer data present, don't save
			// see if randomized by checking baseline.confirm and baseline.randomization_complete
			$record = $recordData[$foundRecordID];
			
			if ($record[$eid]["confirm"] == 1 && $record[$eid]["randomization_complete"] == 2) {
				localLog("	This patient has been randomized.");
				$saveNeeded = false;
				$ignored[$rid] = "randomized already: [confirm] = 1, [randomization_complete] == 2";
			}
			
			# if last_fill_date > import_data, don't save
			$existingFillDate = $record["repeat_instances"][$eid][$line[2]][$line[3]]["last_fill_date"];
			if ($existingFillDate >= $line[17]) {
				localLog("	Last fill date >= import's last fill date.");
				$saveNeeded = false;
				$ignored[$rid][$line[3]] = "existing last_fill_date (" . $existingFillDate . ") is >= import last_fill_date (" . $line[17].") for this PDC data";
			}
		}
		
		if ($saveNeeded === true) {
			localLog("	Adding record data to be saved to REDCap project");
			$data[$rid] = [];
			$data[$rid]["repeat_instances"] = [];
			$data[$rid][$eid] = [
				"mrn" => $mrn,
				"import_date" => $line[4],
				"mrn_gpi" => $line[5],
				"med_name" => $line[6],
				"clinic" => $line[7],
				"sex" => $line[8],
				"date_birth" => $line[9],
				"insurance" => $line[11],
				"oop" => $line[12],
				"zip" => $line[13],
				"clinic_level" => $line[14],
				"vsp_pat" => $line[15]
			];
			$data[$rid]['repeat_instances'][$eid][$line[2]][$line[3]] = [
				"last_fill_date" => $line[17],
				"measure_date" => $line[18],
				"gap_days" => $line[19],
				"pdc_measurement_4mths" => $line[20],
				"pdc_measurement_12mths" => $line[21]
			];
		}
		$rid++;
		$line = null;
	}
	
	echo("</pre>");
	
	# if all PDC entries ignored, ignore baseline too
	// foreach ($data as $rid => $record) {
		// if (empty($record["repeat_instances"])) {
			// unset($data[$rid]);
		// }
	// }
	
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
		echo "Here is the data sent to REDCap:\n" . print_r($data, true) . "\n";
	}
	echo "</pre>";
	$output = ob_get_contents();
	ob_end_clean();
	
	echo $output;
}