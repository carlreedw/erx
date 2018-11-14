<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 2/12/2018
 * Time: 9:01 AM
 */
#############################################################################################
# This is an automated algorithm that runs at the end of first day of every month, storing
# some metrics for the previous month into a report file for access through a dashboard on
# the REDCap project, which is accessed on the reports.php page
#############################################################################################
require_once("base.php");

// Make sure the file is being run as a cron on the server, not being accessed through a web browser
$cronRunning = $argv[1];
if ($cronRunning != "cron") {
	exit;
}

$activeCount = 0;
$relapseCount = 0;
$stableCount = 0;
$ltfCount = 0;
$contactsCount = 0;
$phqCount = 0;
$twoMonthsCount = 0;

$projectRecords = getProjectRecords($trackingProject);
//$projectMetaData = $trackingProject->getMetadata();
// Path to the report file
$filePath = DATA_REPORT_PATH.DATA_REPORT_FILE;

$currentDate = new DateTime('now');
date_sub($currentDate,date_interval_create_from_date_string('1 day'));

$twoMonths = new DateInterval('P2M');
$compareDate = clone $currentDate;
$compareDate->sub($twoMonths);

foreach ($projectRecords as $projectRecord) {
	$recordData = $projectRecord->getDetails();

	// Due to how the Plugins Core code works, the different events for the
	// same record are viewed as different Project objects
	$visitRecord = \Plugin\Record::createRecordFromId($visitProject, $recordData[$trackingProject->getFirstFieldName()]);
	$psychRecord = \Plugin\Record::createRecordFromId($psychProject, $recordData[$trackingProject->getFirstFieldName()]);
	// Try / Catch is in case there are no data points for the record in these events.
	// This is because the 'getDetails' function throws an exception if it runs on a non-existent record
	try {
		$visitData = $visitRecord->getDetails();
	} catch (Exception $e) {
		$visitData = array();
	}
	try {
		$psychData = $psychRecord->getDetails();
	} catch (Exception $e) {
		$psychData = array();
	}

	$currentMRN = $recordData[MRN];
	$recentEpisode = "";
	$recentInstance = "";
	$initialInstance = "";
	$phq9Instance = 0;
	$gad7Instance = 0;
	if ($currentMRN == "") continue;

	// Tracks the numbers of the various status and data points
	switch ($recordData[TREATMENT_STATUS]) {
		case TREATMENT_ACTIVE:
			$activeCount++;
			break;
		case TREATMENT_RELAPSE:
			$relapseCount++;
			break;
		case TREATMENT_INACTIVE:
			$stableCount++;
			break;
		case TREATMENT_LTF:
			$ltfCount++;
			break;
		default:
			break;
	}

	// Go through the visits and determine what the instance is for the newest patient episode,
	// which is the latest visit with an initial contact number
	foreach ($visitData[FOLLOWUP_CONTACT_NUMBER] as $instance => $iData) {
		if ($visitData[CONTACT_OCCUR][$instance] == NO) continue;
		if ($visitData[EPISODE_NUMBER][$instance] != "" && ($visitData[EPISODE_NUMBER][$instance] >= $recentEpisode || $recentEpisode == "")) {
			if ($visitData[EPISODE_NUMBER][$instance] > $recentEpisode) {
				$recentInstance = "";
				$initialInstance = "";
			}
			$recentEpisode = $visitData[EPISODE_NUMBER][$instance];
			if ($iData == FOLLOWUP_INITIAL) {
				$initialInstance = $instance;
			}
			if ($iData == FOLLOWUP_CONTACT && ($instance >= $recentInstance || $recentInstance = "")) {
				$recentInstance = $instance;
			}
		}
	}

	$numberContacts = 0;

	// Get a count on the number of visits that had a valid contact date for the previous month
	foreach ($visitData[CONTACT_DATE] as $instance => $iData) {
		if ($iData != "") {
			$contactDate = DateTime::createFromFormat("Y-m-d",$iData);
			if ($currentDate->format("Y-m") == $contactDate->format("Y-m")) {
				//$numberContacts++;
				$contactsCount++;
				break;
			}
		}
	}

	/*if ($numberContacts > 0) {
		$contactsCount++;
	}*/

	// Get the latest PHQ9 score's instance
	foreach ($visitData[PHQ9_DATE] as $instance => $iData) {
		if (strtotime($visitData[PHQ9_DATE][$instance]) > strtotime($visitData[PHQ9_DATE][$phq9Instance]) || $phq9Instance === 0) {
			$phq9Instance = $instance;
		}
	}

	// If the PHQ9 score for this instance is low enough, or is lower than the initial PHQ9 score by enough,
	// then count it
	if ($visitData[PHQ9_SCORE][$phq9Instance] < 5 || (round((($tableData[$currentMRN]['recent_phq9'] - $tableData[$currentMRN]['initial_phq9']) / $tableData[$currentMRN]['initial_phq9']), 2) * 100) < -50) {
		$phqCount++;
	}
	// For anyone who didn't have a sufficiently low PHQ9 score, perform an extra check that they
	// have been receiving psych reviews
	else {
		$psychFlag = true;
		foreach ($psychData[PSYCH_DATE] as $instance => $iData) {
			if ($iData != "") {
				$psychDate = DateTime::createFromFormat("Y-m-d",$iData);
				if ($compareDate->format("Y-m") < $psychDate->format("Y-m")) {
					$psychFlag = false;
					break;
				}
			}
		}
		if ($psychFlag) {
			$twoMonthsCount++;
		}
	}
}

// If the file doesn't exist, create it and put the required headers in it
if (!file_exists($filePath)) {
	file_put_contents($filePath,"Date,'Active' Patients,'Inactive - Stable' Patients,'Inactive - LTF' Patients,'Relapse Prevention' Patients,1 or More Contacts,PHQ Score <5 or 50% Improved,Unimproved and No Psych Consult in 2 Months\r\n",FILE_APPEND);
}

file_put_contents($filePath,$currentDate->format("M Y").",".$activeCount.",".$stableCount.",".$ltfCount.",".$relapseCount.",".$contactsCount.",".$phqCount.",".$twoMonthsCount."\r\n",FILE_APPEND);