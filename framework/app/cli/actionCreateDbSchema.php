#!/usr/bin/php
<?php
use BitsTheater\Director; /* @var $director Director */
use BitsTheater\Regisseur; /* @var $theStageManager Regisseur */
use BitsTheater\costumes\DbAdmin;
use BitsTheater\models\Auth as AuthDB;
use com\blackmoonit\Strings;

//CLI options should be defined by the special function `process_cli_options($aStageManger)`
function process_cli_options($aStageManager)
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI(Regisseur::DEFAULT_CLI_SHORT_OPTIONS . 'b');
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

/**
 * Get my DBConn value from potential Environment var(s).
 * @param string $aSuffix - the specific var to get.
 * @param string $aDefault - (optional) value if none found.
 * @return string Returns the value to use.
 * @throws \InvalidArgumentException if no value found to use.
 */
function getMyDbConnEnvVar( $aSuffix, $aDefault=null )
{
	$s = getenv('DBCONN_' . strtoupper(APP_DB_CONN_NAME) . '_' . $aSuffix);
	if ( empty($s) ) {
		$s = getenv('DBCONN_' . $aSuffix);
	}
	if ( empty($s) ) {
		$s = $aDefault;
	}
	if ( empty($s) ) {
		throw new \InvalidArgumentException();
	}
	return $s;
}

//why check for -b?  so we know you really mean it.
if ( isset($theCliOptions['b']) )  {
	try {
		//data needed to create database
		$theData = new \stdClass();
		$theData->admin_dbuser = getMyDbConnEnvVar('ADMINUSER');
		$theData->admin_dbpass = getMyDbConnEnvVar('ADMINPSWD');
		$theData->dbtype = getMyDbConnEnvVar('DBTYPE', AuthDB::DB_TYPE_MYSQL);
		$theData->dbhost = getMyDbConnEnvVar('DBHOST');
		$theData->dbport = getMyDbConnEnvVar('DBPORT');
		$theData->dbname = getMyDbConnEnvVar('DBNAME');
		$theData->dbuser = getMyDbConnEnvVar('DBUSER');
		$theData->dbpass = getMyDbConnEnvVar('DBPSWD');
		$theMsg = "Creating database [{$theData->dbname}]";
		print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
		$theDbAdmin = new DbAdmin($director);
		$theDbAdmin->createDbFromUserInput($theData);
	}
	catch (\InvalidArgumentException $iax) {
		//no data found to use to create a database, skip to trying
		//  to create tables in a possibly existing database
		$theMsg = "Not enough info to create a database, skipping creation.";
		print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
	}
	//once db exists, we can create all the model tables
	$dbSetup = $director->getProp('SetupDb');
	try {
		$theMsg = "Attempting to create tables and default data.";
		print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
		$dbSetup->setupModels($theData);
		$theMsg = "All done!";
		print($theMsg . PHP_EOL); Strings::debugLog($theMsg);
	} catch (\Exception $x) {
		$theMsg = $x->getMessage();
		print($theMsg . PHP_EOL); Strings::errorLog($theMsg);
		exit(1);
	}
}

//end output with an EOL so CLI prompt will alway appear correctly on a fresh line
print(PHP_EOL);
