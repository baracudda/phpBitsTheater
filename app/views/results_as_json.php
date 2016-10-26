<?php
//if no headers are sent, send some
if (!headers_sent()) {
	header('Content-Type: application/json; charset=utf-8');
}
$jsonEncodeOptions = null;
if (filter_var($v->UseJsonPrettyPrint, FILTER_VALIDATE_BOOLEAN) || filter_var($v->pretty, FILTER_VALIDATE_BOOLEAN))
	$jsonEncodeOptions = $jsonEncodeOptions | JSON_PRETTY_PRINT;
if (is_object($v->results) && method_exists($v->results, 'printAsJson'))
	$v->results->printAsJson($jsonEncodeOptions);
else if (is_object($v->results) && method_exists($v->results, 'toJson'))
	print($v->results->toJson($jsonEncodeOptions));
else
	print(json_encode($v->results, $jsonEncodeOptions));
