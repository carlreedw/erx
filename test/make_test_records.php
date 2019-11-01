<?php
require_once("../base.php");
require_once(RC_CONNECT_PATH);

$rids = [
	101,
	8535394,
	11649569,
	40772491,
	64196522,
	74488697,
	80958738,
	123028512,
	183036877,
	198961743,
	307409425,
	363433973,
	492172390,
	503312358,
	507697552,
	616554590,
	746949878,
	751732019,
	963261563,
	1042113590,
	1146923544,
	1425868675,
	1479454305,
	1581566001,
	2078118038
];

$data = \REDCap::getData(PID, 'array', $rids);
file_put_contents("test_records.json", json_encode($data));
exit('done');