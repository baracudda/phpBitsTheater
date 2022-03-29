<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\OutputToCSV;

//if no headers are sent, send some
if (!headers_sent()) {
	// disable caching
	$theExpDate = gmdate('D, d M Y H:i:s');
	$theModDate = gmdate('D, d M Y H:i:s');
	header("Expires: {$theExpDate} GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$theModDate} GMT");

	header("Content-Type: text/text; charset=utf-8");

	// disposition / encoding on response body
	header("Content-Disposition: attachment;filename={$v->output_filename}");
	header("Content-Transfer-Encoding: text");
	header('X-Content-Type-Options: nosniff'); //IE & Edge only
}
if ($v->results) {
	$theCSV = OutputToCSV::newInstance()->setInput($v->results);
	$theCSV->useInputForHeaderRow(false);
	if (isset($v->bUseUserAgentToDetermineLineEnding))
		$theCSV->determineClientLineEnding($v->bUseUserAgentToDetermineLineEnding);
	else
		$theCSV->determineClientLineEnding(true);
	$theCSV->setDelimiter(' ');
	$theCSV->setEnclosure('');
	$theCSV->setEnclosureReplacement('');
	if (!empty($v->csv_callbacks) && is_array($v->csv_callbacks))
		foreach ($v->csv_callbacks as $theColName => $theCallback)
			$theCSV->setCallback($theColName, $theCallback);
		
	$theCSV->setOutputStream(fopen('php://output', 'w'));
	$theCSV->generateCSV();
	fclose($theCSV->getOutputStream());
} else {
	$v->debugStr($v->_actor->mySimpleClassName . '::' . $v->_action . '() had no results.');
}
