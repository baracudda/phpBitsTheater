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
	$theCSV->bUseBOM = !empty($v->bUseBOM);
	//default to "true" for backward compatibility
	$bGenerateHeaderRow = (isset($v->bUseResultsForHeaderRow))
			? $v->bUseResultsForHeaderRow
			: true
			;
	$theCSV->useInputForHeaderRow($bGenerateHeaderRow);
	if (isset($v->bUseUserAgentToDetermineLineEnding))
		$theCSV->determineClientLineEnding($v->bUseUserAgentToDetermineLineEnding);
		if ( !empty($v->csv_opt_col_names_to_prepend_equal) ) {
		$theCSV->setColNamesToPrependEqual($v->csv_opt_col_names_to_prepend_equal);
	}
	if (isset($v->csv_opt_delimiter))
		$theCSV->setDelimiter($v->csv_opt_delimiter);
	if (isset($v->csv_opt_enclosure))
		$theCSV->setEnclosure($v->csv_opt_enclosure);
	if (isset($v->csv_opt_enclosure_left) && isset($v->csv_opt_enclosure_right))
		$theCSV->setEnclosure($v->csv_opt_enclosure_left, $v->csv_opt_enclosure_right);
	if (isset($v->csv_opt_enclosure_replacement))
		$theCSV->setEnclosureReplacement($v->csv_opt_enclosure_replacement);
	if (isset($v->csv_opt_enclosure_replacement_left)
			&& isset($v->csv_opt_enclosure_replacement_right))
		$theCSV->setEnclosureReplacement( $v->csv_opt_enclosure_replacement_left,
				$v->csv_opt_enclosure_replacement_right
		);
	if (!empty($v->csv_callbacks) && is_array($v->csv_callbacks))
		foreach ($v->csv_callbacks as $theColName => $theCallback)
			$theCSV->setCallback($theColName, $theCallback);
		
	$theCSV->setOutputStream(fopen('php://output', 'w'));
	$theCSV->generateCSV();
	fclose($theCSV->getOutputStream());
} else {
	$v->debugStr($v->_actor->mySimpleClassName . '::' . $v->_action . '() had no results.');
}
