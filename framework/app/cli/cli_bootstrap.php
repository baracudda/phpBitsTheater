<?php
use BitsTheater\Regisseur; /* @var $theStageManager Regisseur */
use BitsTheater\Director;

//start the State Manager which sets up our run-time environment
$theAppPath = dirname(__DIR__);
global $theStageManager, $argv, $director;
require_once( $theAppPath . DIRECTORY_SEPARATOR . 'Regisseur.php' );
$theStageManager = Regisseur::requisition();
// Bail out if this is not running under CLI mode. We don't want these
// CLI operations to be invoked through HTTP.
if ( !$theStageManager->isRunningUnderCLI() ) {
    $current_filename = basename($argv[0]);
    print("ERROR: Attempting to run {$current_filename} under HTTP. Use the CLI." . PHP_EOL);
    exit(1);
}
//process command arguments
$theCliOptions = (function_exists('process_cli_options'))
	? process_cli_options($theStageManager)
	: $theStageManager->processOptionsForCLI();
$theStageManager->defineConstants()->registerClassLoaders();

//start the Director
$director = Director::requisition();

//define some generic functions
function dumpvar($x)
{ global $director; print( PHP_EOL . $director->logStuff($x) ); }

//IMPORTANT!!!
//  returns the processed CLI options
//  @see http://www.php.net/manual/en/function.getopt.php
return $theCliOptions;

/************************************************

PHP's CLI mode does not respect the additional *.ini folder like Apache does.
If you need to enable additional modules using that mechanism you need to
concatenate all of them into a single php.ini file for use with CLI. The
following is a sample command to concatenate the *.ini files using OSX with
MAMP, depositing the result into the home folder:

$ sudo paste -s -d "\n" /Applications/mampstack-5.6.20-0/php/etc/php.ini /usr/local/etc/php/5.6/conf.d/*.ini > ~/php.ini

************************************************/
/************************************************
EXAMPLE CLI ACTION FILE NAMED: someCliAction.php

<?php
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');
$director->raiseCurtain('Actor/method');

************************************************/
/************************************************
IF YOUR Actor::method() REQUIRES AUTHENTICATION, CALL WITH ARGUMENTS:

$ someCliAction -uUser -pMyPassword

************************************************/
/************************************************
BitsTheaters\costumes\WornForCLI trait may contain useful methods for your
classes that need to operate in CLI mode.
************************************************/
