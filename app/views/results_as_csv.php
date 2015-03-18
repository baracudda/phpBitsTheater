<?php
use BitsTheater\Scene;
/* @var $recite Scene */
/* @var $v Scene */
use com\blackmoonit\Strings;
use com\blackmoonit\OutputToCSV;
use \DateTime;

// disable caching
$theExpDate = gmdate('D, d M Y H:i:s');
$theModDate = gmdate('D, d M Y H:i:s');
header("Expires: {$theExpDate} GMT");
header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
header("Last-Modified: {$theModDate} GMT");

// force download
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");

// disposition / encoding on response body
header("Content-Disposition: attachment;filename={$v->output_filename}");
header("Content-Transfer-Encoding: binary");

$theCSV = new OutputToCSV();
if ($v->results) {
	//TODO set all the various CSV options
	$w = $theCSV->useInputForHeaderRow()->generateCSV($v->results);
	print($w);
} else {
	//not sure
}
die();