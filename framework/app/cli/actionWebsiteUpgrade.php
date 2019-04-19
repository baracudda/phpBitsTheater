#!/usr/bin/php
<?php
use BitsTheater\BrokenLeg;
use BitsTheater\Director; /* @var $director Director */
use BitsTheater\Regisseur; /* @var $theStageManager Regisseur */
use BitsTheater\costumes\SiteUpdater;
use BitsTheater\costumes\venue\TicketViaInstallPw;
use BitsTheater\models\Auth as AuthDB; /* @var $dbAuth AuthDB */
use com\blackmoonit\exceptions\IDebuggableException;

//CLI options should be defined by the special function `process_cli_options($aStageManger)`
function process_cli_options($aStageManager)
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI(Regisseur::DEFAULT_CLI_SHORT_OPTIONS . 'f');
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

//additional options we wish to parse besides the default -u -p and --host
$_POST['force_model_upgrade'] = isset($theCliOptions['f']);

//if using the install pw, bypass the normal auth mechanism and upgrade the schema using
//  the costume.
if ( TicketViaInstallPw::checkInstallPwVsInput($theCliOptions['p']) ) {
	$theParams = new \stdClass() ;
	$theParams->force_model_upgrade = isset($theCliOptions['f']);
	$theUpdater = new SiteUpdater($director, $theParams, 'SetupDb');
	try {
	    if ($theParams->force_model_upgrade) print("Forcing model upgrade" . PHP_EOL);
		$theUpdater->upgradeAllFeatures();
	}
	catch( BrokenLeg $blx )
	{ print $blx->toJson( JSON_PRETTY_PRINT ) ; }
	catch( \Exception $x )
	{ print BrokenLeg::tossException($director,$x)->toJson(JSON_PRETTY_PRINT) ; }
	exit(0);
}

//raiseCurtain on our actor/method to execute
try {
	if ( $director->isInstalled() ) {
		$dbAuth = $director->getProp('Auth');
	}
	if ( !empty($dbAuth) && $dbAuth->isExists($dbAuth->tnAuthAccounts) ) {
		$director->raiseCurtain('Admin/apiWebsiteUpgrade');
	}
}
catch (IDebuggableException $dx) {
	print($dx->getMessage().PHP_EOL);
	print($dx->getContextMsg().PHP_EOL);
}
catch (\Exception $x) {
	print($x->getMessage().PHP_EOL);
}

//end output with an EOL so CLI prompt will alway appear correctly on a fresh line
print(PHP_EOL);
