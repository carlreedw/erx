<?php

$baselineHeaders = str_getcsv($baselineData);
$pdcHeaders = str_getcsv($pdcData);

# determine which column indices to use to point to imported data
$baselineIndices = [];

$pdcIndices = [];

$rows = count($imported);
for ($i = 1; $i < $rows; $i++) {
	$line = str_getcsv($imported[$i]);
	if ($line[1] == "pdc_measurement") {
		// $pdcData = $pdcData . "\n" . implode(",", array_slice($line, 18, 5));
	} else {
		
	}
}

$results = \REDCap::saveData(PID, 'array', $baselineData);
print_r($results);


$recordCount = (count($csv) + 1)/2;
for ($i = 1; $i < $recordCount; $i++) {
	$line1 = str_getcsv($csv[$i*2-1]);
	$line2 = str_getcsv($csv[$i*2]);
	
	$recordId = $line1[0];
	$instrument = $line2[1];
	$instance = $line2[2];
	$mrn_gpi = $line1[4];
	$sex = $line1[5];
	$dob = $line1[6];
	$age = $line1[7];
	$ins = $line1[8];
	$oop = $line1[9];
	$zip = $line1[10];
	$med = $line1[11];
	$clinic = $line1[12];
	$clinicLevel = $line1[13];
	$vsp = $line1[14];
	$inclusion = $line1[17];
	$lastFillDate = $line2[18];
	$measureDate = $line2[19];
	$gapDays = $line2[20];
	$pdc4mo = $line2[21];
	$pdc12mo = $line2[22];
	
	
}

function saveRecord() {
	$data = 
	$results = \REDCap::saveData(PID, 'array', $data);
	
	print_r($results);
	echo "\n\n";
	var_dump($results);
}

# make indices
$baselineColIndices = [];
$pdcColIndices = [];
foreach ($baselineHeaders as $i => $name) {
	$index = array_search($name, $csvColumns);
	if ($index===false && $name == "zip") {
		$index = array_search("pat_zip", $csvColumns);
	}
	
	# if still no index found, imported data is missing column(s)
	if ($index===false) {
		echo "ERROR: The imported data (from $importDataFilename) seems to be missing one or more columns.\n" . "Couldn't find column for field name: $name\n";
		exit;
	}
	
	$baselineColIndices[] = $index;
}
foreach ($pdcHeaders as $i => $name) {
	$index = array_search($name, $csvColumns);
	
	# if still no index found, imported data is missing column(s)
	if (!$index) {
		echo "ERROR: The imported data (from $importDataFilename) seems to be missing one or more columns.\n" . "Couldn't find column for field name: $name\n";
		exit;
	}
	
	$$pdcColIndices[] = $index;
}



# project variables
$project = new \Project(PID);
$eid = (int) $project->firstEventId;

($name == "zip" ? "pat_zip" : $name)