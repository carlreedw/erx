<?php
/**
 * PLUGIN NAME: ERX
 * DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
 * VERSION: 1.0
 * AUTHOR: carl.w.reed@vumc.org
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";
require_once "base.php";

# import variables
$importDataFilename = "pdc_redcap_import_a.csv";
$imported = file($importDataFilename);
$csvColumns = str_getcsv($imported[0]);

# output/record variables
$baselineData = "record_id,import_date,mrn_gpi,sex,date_birth,age,insurance,oop,zip,med_name,clinic,clinic_level,vsp_pat,inclusion_pdc";		# pat_zip => zip
$pdcData = "last_fill_date,measure_date,gap_days,pdc_measurement_4mths,pdc_measurement_12mths";
$baselineHeaders = str_getcsv($baselineData);
$pdcHeaders = str_getcsv($pdcData);

# process imported data so that $baselineData and $pdcData will be ready to save

for ($i = 1; $i < count($imported); $i++){
	$line = str_getcsv($imported[$i]);
	if ($line[1] == 'pdc_measurement') {
		
	} else {
		// record_id
		$baselineData .= "\n" . $line[0] . ",";
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
		$baselineData .= $line[17] . "\r\n";
	}
}

// $results = \REDCap::saveData(
	// $project_id=PID, 
	// $dataFormat='csv',
	// $data=$baselineData,
	// $overwriteBehavior='normal',
	// $dateFormat='YMD',
	// $type='flat',
	// $dataAccessGroup=NULL,
	// $dataLogging=TRUE,
	// $performAutoCalc=FALSE,
	// $commitData=TRUE
// );
$results = \REDCap::saveData(
	PID, 
	'csv',
	$baselineData,
	'normal',
	'YMD',
	'flat',
	null,
	true,
	false,
	true
);
echo "<pre>" . print_r($results, true) . "</pre>";