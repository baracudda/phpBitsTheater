<?php
use BitsTheater\Regisseur; /* @var $theStageManager Regisseur */
use BitsTheater\Director;

global $theStageManager, $argv, $director;

$theSitePath = getenv('SITE_PATH');
if ( empty($theSitePath) ) {
	$theSitePath = $argv[1];
	if ( !file_exists($theSitePath) || !is_dir($theSitePath) ) {
		$theSitePath = null;
	}
}
if ( empty($theSitePath) ) {
	print('SITE_PATH undefined, first arg required to be path to [site]/app folder.' . PHP_EOL);
	exit(1);
}
$fullBootstrapPath = $theSitePath . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once($fullBootstrapPath);

//start the State Manager which sets up our run-time environment
$theStageManager = Regisseur::requisition();
// Bail out if this is not running under CLI mode. We don't want these
// CLI operations to be invoked through HTTP.
if ( !$theStageManager->isRunningUnderCLI() ) {
    $current_filename = basename($argv[0]);
    print("ERROR: Attempting to run {$current_filename} under HTTP. Use the CLI." . PHP_EOL);
    exit(1);
}

$theCliOptions = (function_exists('process_cli_options'))
	? process_cli_options($theStageManager)
	: $theStageManager->processOptionsForCLI();

//start the Director
$director = Director::requisition();

//define some generic functions
function dumpvar($x)
{ global $director; print( PHP_EOL . $director->logStuff($x) ); }

//IMPORTANT!!!
//  returns the processed CLI options
//  @see http://www.php.net/manual/en/function.getopt.php
return $theCliOptions;
