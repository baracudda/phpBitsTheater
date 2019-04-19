#!/usr/bin/php
<?php
use BitsTheater\Regisseur;
use BitsTheater\costumes\venue\TicketViaInstallPw;
use com\blackmoonit\Strings;

global $director;

//CLI options should be defined by the special function `process_cli_options($aStageManger)`
function process_cli_options($aStageManager)
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI(Regisseur::DEFAULT_CLI_SHORT_OPTIONS . '');
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

if ( !TicketViaInstallPw::checkInstallPwVsInput($theCliOptions['p']) ) {
	$theMsg = "401 Unrecognized Passphrase";
	print($theMsg . PHP_EOL); Strings::errorLog($theMsg);
	exit(1);
}

if ( $director->isInstalled() && $director->canConnectDb() ) {
	//if db exists, see if we need to migrate from v3.x to v4.x
	try {
		/* @var $dbSetup \BitsTheater\models\SetupDb */
		$dbSetup = $director->getProp('SetupDb');
	
		//start with Auth model
		$theAuthScene = new \BitsTheater\scenes\Account($director);
		$theModel = 'Auth';
		/* @var $dbAuth \BitsTheater\models\Auth */
		$dbAuth = $director->getProp($theModel);
		$theMsg = "Checking to see if {$theModel} migration from framework v3.x -> v4.x is needed...";
		print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
		if ( !$dbAuth->isExists($dbAuth->tnAuthAccounts) ||
				$dbAuth->isEmpty($dbAuth->tnAuthAccounts) )
		{
			$theAuthFeatureData = $dbSetup->getFeature($dbAuth::FEATURE_ID);
			if ( empty($theAuthFeatureData) ) {
				$theAuthFeatureData['version_seq'] = 0;
			}
			$dbAuth->upgradeFeatureVersion(
					$theAuthFeatureData, $theAuthScene
			);
		}
		else {
			$theMsg = "No migration of {$theModel} is needed.";
			print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
		}
	
		//next check the AuthGroups model
		$theAuthGroupsScene = new \BitsTheater\scenes\Rights($director);
		$theModel = 'AuthGroups';
		/* @var $dbAuthGroups \BitsTheater\models\AuthGroups */
		$dbAuthGroups = $director->getProp($theModel);
		$theMsg = "Checking to see if {$theModel} migration from framework v3.x -> v4.x is needed...";
		print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
		if ( !$dbAuthGroups->isExists($dbAuthGroups->tnGroups) ||
				$dbAuthGroups->isEmpty($dbAuthGroups->tnGroups) )
		{
			$theAuthGroupFeatureData = $dbSetup->getFeature($dbAuthGroups::FEATURE_ID);
			//if we get here, force v0 as table should always have at least default data in it
			$theAuthGroupFeatureData['version_seq'] = 0; //0->1 triggers migration
			$dbAuthGroups->upgradeFeatureVersion(
					$theAuthGroupFeatureData, $theAuthGroupsScene
			);
		}
		else {
			$theMsg = "No migration of {$theModel} is needed.";
			print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
		}
	} catch (\Exception $x) {
		$theMsg = $x->getMessage();
		print($theMsg . PHP_EOL); Strings::errorLog($theMsg);
		exit(1);
	}
	$theMsg = "All done!";
	print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
}
//end output with an EOL so CLI prompt will alway appear correctly on a fresh line
print(PHP_EOL);
