<?php
/**
 * PLUGIN NAME: ERX
 * DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
 * VERSION: 1.0
 * AUTHOR: carl.w.reed@vumc.org
 */

# includes
require_once "../../redcap_connect.php";
include_once('vendor/autoload.php');
require_once "base.php";

# auth
$credFilepath = substr(APP_PATH_DOCROOT, 0, strpos(APP_PATH_DOCROOT, 'www')) . 'credentials\adherence.txt';
$creds = file_get_contents($credFilepath);
$host = 'sftp.vumc.org';
$port = 22;
preg_match('/user: (.*)$/m', $creds, $matches);
$username = $matches[1];
preg_match('/passwd: (.*)$/', $creds, $matches);
$password = $matches[1];
if(!$username or !$password or !$host){
	die("The ErX plugin was not able to find the correct SFTP credentials for the Adherence Intervention Study project. Please contact your REDCap administrator.");
}

# fetch data to be imported and process it
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
		processImport($filesystem->read($file['path']));
	}
}

function processImport($import){
	# project variables
	$project = new \Project(PID);
	$eid = $project->firstEventId;
	$pid = PID;
	
	# optionally import from local storage
	// $import = file(DATA_FILE_PATH);
	
	# make sure we have import data
	if (empty($import) || !$import) {
		exit;
	}
	
	$data = [];
	$ignored = [];
	$imported = [];
	
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $import) as $line){
		$imported[] = $line;
	}
	
	// # process imported data line-by-line
	$rows = (count($imported) / 2);
	// for ($i = 1; $i < $rows; $i++) {
	for ($i = 1; $i < 5; $i++) {
		# convert csv to array, baseline data line first, then pdc data line
		$line1 = str_getcsv($imported[$i*2-1]);
		$line2 = str_getcsv($imported[$i*2]);
		# convert dates
		$line1[3] = (new DateTime($line1[3]))->format("Y-m-d");
		$line1[6] = (new DateTime($line1[6]))->format("Y-m-d");
		$line2[18] = (new DateTime($line2[18]))->format("Y-m-d");
		$line2[19] = (new DateTime($line2[19]))->format("Y-m-d");
		
		# get record_id for this pair of imported rows
		$rid = $line1[0];
		
		# see if already randomized
		$recordData = \REDCap::getData($pid, 'array', $rid);
		
		$saveNeeded = true;
		if (!empty($recordData)) {
			# if already randomized, or newer data present, don't save
			# see if randomized by checking baseline.confirm and baseline.randomization_complete
			if ($recordData[$rid][$eid]["confirm"] == 1 && $recordData[$rid][$eid]["randomization_complete"] == 2) {
				$saveNeeded = false;
				$ignored[$rid] = "ignored: randomized already / confirm = 1 and randomization_complete = 2";
			}
			
			# if last_fill_date > import_data, don't save
			$existingFillDate = $recordData[$rid]["repeat_instances"][$eid][$line2[1]][$line2[2]]["last_fill_date"];
			if ($existingFillDate >= $line2[18]) {
				$saveNeeded = false;
				$ignored[$rid] = "ignored: existing last_fill_date (" . $existingFillDate . ") is >= import_date (" . $line1[3].")";
			}
		}
		
		if ($saveNeeded) {
			$data[$rid] = [
				$eid => [
					"import_date" => $line1[3],
					"mrn_gpi" => $line1[4],
					"sex" => $line1[5],
					// "ethnicity" => $line1[asoidufhaisduf],
					// "race" => $line1[asoidufhaisduf],
					"date_birth" => $line1[6],
					// "age" => $line1[7],
					"insurance" => $line1[8],
					"oop" => $line1[9],
					"zip" => $line1[10],
					"med_name" => $line1[11],
					"clinic" => $line1[12],
					"clinic_level" => $line1[13],
					"vsp_pat" => $line1[14]
				],
				"repeat_instances" => [
					$eid => [
						# "pdc_measurement"
						$line2[1] => [
							# 1 or int
							$line2[2] => [
								"last_fill_date" => $$line2[18],
								"measure_date" => $line2[19],
								"gap_days" => $line2[20],
								"pdc_measurement_4mths" => $line2[21],
								"pdc_measurement_12mths" => $line2[22],
							]
						]
					]
				]
			];
		}
	}
	
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
	
	$output = "ERX plugin ran on: " . (new DateTime())->format("Y-m-d h:m:s") . "\n";
	$output .= "\tAttempting to read from data file: pdc_redcap_import.csv\n";
	$output .= "\tTargeting REDCap project with project ID: " . $pid . "\n";
	$output .= "\tCSV data row pairs to import: ".($rows - 1)."\n";
	$output .= "\tTotal records added/updated: " . count($results['ids']) . "\n";
	$output .= "\tTotal records ignored: " . count($ignored) . "\n";
	$output .= "\tIDs of records added:\n" . print_r($results['ids'], true) . "\n";
	$output .= "\tIgnored:\n" . print_r($ignored, true) . "\n";
	
	// # check output
	$output .= "\tresults:\n" . print_r($results, true). "\n";
	$output .= "\tdata:\n" . print_r($data, true). "\n";
	
	// # echo to client
	echo "<pre>$output</pre>";
}