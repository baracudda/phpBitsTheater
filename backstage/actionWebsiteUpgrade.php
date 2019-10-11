#!/usr/bin/php
<?php
use com\blackmoonit\Strings ;
use BitsTheater\Regisseur ;
use BitsTheater\BrokenLeg ;
use BitsTheater\Scene ;
use BitsTheater\costumes\SiteUpdater ;

global $director;

/**
 * The short argument whose presence specifies that updates should be forced.
 * @var string
 * @since BitsTheater [NEXT]
 */
define( 'ARG_SHORT_FORCE_UPDATE' , 'f' ) ;

/**
 * Handles processing of CLI options.
 *
 * By the time this function is executed, Regisseur class will have been loaded
 * and usable.
 *
 * @param Regisseur $aStageManager provides context and option processing
 * @return array the option/value pairs obtained by <code>getopt()</code>
 */
function process_cli_options( $aStageManager )
{
	$theOptions = $aStageManager->processOptionsForCLI(
			Regisseur::DEFAULT_CLI_SHORT_OPTIONS . ARG_SHORT_FORCE_UPDATE
	);	
	return $theOptions ;
}

// Standard means of setting up the environment to access the website via CLI.
// The processed CLI options returned by the process_cli_options() are returned
// as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

$theParams = new \stdClass() ;
$theParams->force_model_upgrade = isset(theCliOptions[ARG_SHORT_FORCE_UPDATE]);

$theUpdater = new SiteUpdater($director, $theParams, 'SetupDb');
try {
    if ( $theParams->force_model_upgrade ) {
    	print('Forcing model upgrade' . PHP_EOL);
	}
	$theUpdater->upgradeAllFeatures();
	print(PHP_EOL);
}
catch ( \Exception $x ) {
	$blx = BrokenLeg::tossException($director,$x);
	print($blx->getExtendedErrMsg().PHP_EOL);
	print($blx->toJson(JSON_PRETTY_PRINT).PHP_EOL);
	exit(1);
}
