<?php
/**
 * PLUGIN NAME: ERX
 * DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
 * VERSION: 1.0
 * AUTHOR: carl.w.reed@vumc.org
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";
$csv = file('pdc_redcap_import_a.csv');

function addRecord($data) {
	
}
function updateRecord($data) {
	
}

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
	
	// if mrn-gpi not in project
		// add record
	// else
		// if not randomized
			// update record
		// end
	// end
	
	
}