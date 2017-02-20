<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Strings;
use com\blackmoonit\OutputToCSV;

//if no headers are sent, send some
if (!headers_sent()) {
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
}
if ($v->results) {
	$theCSV = OutputToCSV::newInstance()->setInput($v->results);
	//default to "true" for backward compatibility
	$bGenerateHeaderRow = (isset($v->bUseResultsForHeaderRow))
			? $v->bUseResultsForHeaderRow
			: true
			;
	$theCSV->useInputForHeaderRow($bGenerateHeaderRow);
	if (isset($v->bUseUserAgentToDetermineLineEnding))
		$theCSV->determineClientLineEnding($v->bUseUserAgentToDetermineLineEnding);
	//TODO set all the various CSV options
	$theCSV->setOutputStream(fopen('php://output', 'w'));
	$theCSV->generateCSV();
	fclose($theCSV->getOutputStream());
} else {
	//not sure
}
