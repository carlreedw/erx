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





# process imported data so that $baselineData and $pdcData will be ready to save

for ($i = 1; $i < count($imported); $i++){
	$line = str_getcsv($imported[$i]);
	if ($line[1] == 'pdc_measurement') {
		// record_id
		$pdcData .= "\r\n" . $line[0] . ",";
		// redcap_repeat_instrument
		$pdcData .= $line[1] . ",";
		// redcap_repeat_instance
		$pdcData .= $line[2] . ",";
		// last_fill_date
		$pdcData .= (new DateTime($line[18]))->format("Y-m-d") . ",";
		// measure_date
		$pdcData .= (new DateTime($line[19]))->format("Y-m-d") . ",";
		// gap_days
		$pdcData .= $line[20] . ",";
		// pdc_measurement_4mths
		$pdcData .= $line[21] . ",";
		// pdc_measurement_12mths
		$pdcData .= $line[22];
	} else {
		// record_id
		$baselineData .= "\r\n" . $line[0] . ",";
		// import_date
		$baselineData .= (new DateTime($line[3]))->format("Y-m-d") . ",";
		// mrn_gpi
		$baselineData .= $line[4] . ",";
		// sex
		$baselineData .= $line[5] . ",";
		// date_birth
		$baselineData .= (new DateTime($line[6]))->format("Y-m-d") . ",";
		// age
		$baselineData .= $line[7] . ",";
		// insurance
		$baselineData .= $line[8] . ",";
		// oop
		$baselineData .= $line[9] . ",";
		// zip
		$baselineData .= $line[10] . ",";
		// med_name
		$baselineData .= $line[11] . ",";
		// clinic
		$baselineData .= $line[12] . ",";
		// clinic_level
		$baselineData .= $line[13] . ",";
		// vsp_pat
		$baselineData .= $line[14] . ",";
		// inclusion_pdc
		$baselineData .= $line[17];
	}
}



# output/record variables
$baselineData = "record_id,import_date,mrn_gpi,sex,date_birth,age,insurance,oop,zip,med_name,clinic,clinic_level";		# pat_zip => zip
$pdcData = "record_id,redcap_repeat_instrument,redcap_repeat_instance,last_fill_date,measure_date,gap_days,pdc_measurement_4mths,pdc_measurement_12mths";
$baselineHeaders = str_getcsv($baselineData);
$pdcHeaders = str_getcsv($pdcData);




# test get
// $testData = \REDCap::getData(PID, 'csv');
// echo gettype($testData) . "<br \>";
// echo "<pre>" . print_r($testData, true) . "</pre>";
// exit;

# test insert
// $testData = 'record_id,insurance\r\n2,1';
// $results = \REDCap::saveData(
	// PID, 
	// 'csv',
	// $testData
// );
// echo "<pre>" . print_r($results, true) . "</pre>";
// exit;