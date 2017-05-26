#!/usr/bin/php
<?php
use BitsTheater\Regisseur;

//CLI options should be defined by the special function `process_cli_options($aStageManger)`
function process_cli_options($aStageManager)
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI(Regisseur::DEFAULT_CLI_SHORT_OPTIONS . 'f');
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once('cli_bootstrap.php');

//additional options we wish to parse besides the default -u -p and --host
$_POST['force_model_upgrade'] = isset($theCliOptions['f']);

//raiseCurtain on our actor/method to execute
$director->raiseCurtain('Admin', 'apiWebsiteUpgrade');

//end output with an EOL so CLI prompt will alway appear correctly on a fresh line
print(PHP_EOL);
