<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\OutputToICS;
use com\blackmoonit\ICalendarEntry;

//if no headers are sent, send some
if (!headers_sent()) {
	// disable caching
	$theExpDate = gmdate('D, d M Y H:i:s');
	$theModDate = gmdate('D, d M Y H:i:s');
	header("Expires: {$theExpDate} GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$theModDate} GMT");
	/* not sure we want to force download, yet
	// force download
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	*/
	// disposition / encoding on response body
	if (empty($v->output_filename)) $v->output_filename = 'event.ics';
	header("Content-Disposition: inline;filename={$v->output_filename}");
	header('Content-type: text/calendar');
	//header("Content-Transfer-Encoding: binary");
}
if ($v->results instanceof ICalendarEntry) {
	$theICS = OutputToICS::newInstance()->setProductId($v->product_id);
	$theICS->setOutputStream(fopen('php://output', 'w'));
	$theICS->generateICS($v->results);
	//fclose($theICS->getOutputStream());
} else {
	$v->errorLog('OutputToICS fail; results not ICalendarEntry');
	//not sure
}
