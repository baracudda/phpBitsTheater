<?php
header('Content-Type: application/json; charset=utf-8');
$jsonEncodeOptions = null;
if (filter_var($v->UseJsonPrettyPrint, FILTER_VALIDATE_BOOLEAN) || filter_var($v->pretty, FILTER_VALIDATE_BOOLEAN))
	$jsonEncodeOptions = $jsonEncodeOptions | JSON_PRETTY_PRINT;
print(json_encode($v->results, $jsonEncodeOptions));
