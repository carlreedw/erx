<?php
require_once("../base.php");
require_once(RC_CONNECT_PATH);

$data = json_decode(\REDCap::getData(PID, 'json', [101]), true);
echo "<pre>";
print_r($data);
echo "</pre>";

exit("\n\nfield count: " . count(array_keys($data[0])));