<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;

/*********************************************************************
 * Sometimes process is too long to wait for, return something small
 * and let UI call us again later to see if/when finished for result.
 *********************************************************************/

$theProcessor = $v->results;
if ( !is_object($theProcessor) || !method_exists($theProcessor, 'startProcessAfter202') ||
		!method_exists($theProcessor, 'createProcessTask') || !method_exists($theProcessor, 'finishProcessTask') ||
		!( method_exists($theProcessor, 'printResponse202') || method_exists($theProcessor, 'getResponse202') ) )
{
	//programmer error
	$v->logErrors(__METHOD__, ' results object not compatible for 202 processing: ', $theProcessor);
	throw BrokenLeg::toss($v, BrokenLeg::ACT_SERVICE_UNAVAILABLE)
		->putExtra('reason', 'results object not compatible for 202 processing.');
}

//long process we want to finish eventually
ignore_user_abort(true);
set_time_limit(0);

/**
 * Common code used to output the quick response.
 * @param MyScene $v - the Scene object in use.
 * @param object $aProcessor - the processor object.
 * @param string $aTaskToken - the token used to ID this particular task.
 */
function printResponse202( MyScene $v, $aProcessor )
{
	$jsonEncodeOptions = null;
	if ( filter_var($v->UseJsonPrettyPrint, FILTER_VALIDATE_BOOLEAN) || filter_var($v->pretty, FILTER_VALIDATE_BOOLEAN) ) {
		$jsonEncodeOptions = $jsonEncodeOptions | JSON_PRETTY_PRINT;
	}
	if ( method_exists($aProcessor, 'printResponse202') ) {
		//custom response, may not be JSON encoding.
		$aProcessor->printResponse202($v, $jsonEncodeOptions);
	}
	else {
		//standard APIResponse with data returned via getResponse202() method
		$theResponse = APIResponse::resultsWithData($aProcessor->getResponse202($v));
		header('Content-Type: application/json; charset=utf-8');
		print(json_encode($theResponse, $jsonEncodeOptions));
	}
	http_response_code(202);
	if ( session_id() ) session_write_close();
}

$theProcessor->createProcessTask($v);
try {
	//quick response
	if (is_callable('fastcgi_finish_request')) {
		printResponse202($v, $theProcessor);
		fastcgi_finish_request();
	}
	else {
		ob_start();
		printResponse202($v, $theProcessor);
		header('Content-Encoding: none');
		header('Content-Length: ' . ob_get_length());
		header('Connection: close');
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		flush();
	}
	//now that client is satisfied, we can take our time to finish executing
	//start the long process
	$theProcessor->startProcessAfter202($v);
}
finally {
	$theProcessor->finishProcessTask($v);
}