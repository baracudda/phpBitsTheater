<?php
use BitsTheater\Scene; /* @var $v Scene */
/*
 * In the move to try and utilize costumes as much as possible, this output type calls upon
 * the object itself to deal with headers and actual output rather than creating several
 * different views with slightly different options (looking at you apk/file/mimetype).
 */
if (is_object($v->results) && method_exists($v->results, 'printOutput'))
	$v->results->printOutput($v);
else {
	http_response_code(501);
	die('results object has no printOutput() method');
}
