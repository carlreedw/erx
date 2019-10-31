<?php
require('../base.php');
require(RC_CONNECT_PATH);
$test_patients_json = file_get_contents("erx_test_patients_10_31.json");
$result = \REDCap::saveData(PID, 'json', $test_patients_json);
echo "<pre>";
print_r($result);
echo "</pre>";

/* the following code was used to generate erx_test_patients file given erx_reset_data_all_10_31.json */

// $mrns = [
	// 31552086,
	// 32783185,
	// 40811580,
	// 15213796,
	// 14645592,
	// 26086397,
	// 160205,
	// 11896339,
	// 30171151,
	// 46387756,
	// 32813248,
	// 46387756,
	// 23533011,
	// 34943373,
	// 39195201,
	// 15961543,
	// 22364673,
	// 46451283,
	// 21710538,
	// 7629694,
	// 12322640,
	// 14551014,
	// 9112947,
	// 15049224,
	// 27653815,
	// 46399149,
	// 39135587
// ];
// foreach ($data as $record) {
	// echo $record['mrn'] . "<br>";
	
	// if ($record['mrn'] == 046387756) {
		// echo "<br>special:<br>";
		// echo "int: " . (int) $record['mrn'] . "<br>";
		// echo "int: " . (int) $record['mrn'] . "<br>";
	// }
	
	// $key = array_search((int) $record['mrn'], $mrns);
	// if ($key !== false) {
		// array_splice($mrns, $key, 1);
		// $found[] = $record;
	// }
// }
// file_put_contents("erx_test_patients_10_31.json", json_encode($found));

// echo "<br>" . print_r($mrns, true) . "<br>";
// exit("mrns count: " . count($mrns) . " -- found count: " . count($found));

// $result = \REDCap::saveData(PID, 'json', );
// exit(print_r($result, true));
