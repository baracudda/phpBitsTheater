#!/usr/bin/php
<?php
use BitsTheater\Regisseur;

global $director;

//CLI options should be defined by the special function `process_cli_options($aStageManger)`
function process_cli_options($aStageManager)
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI(Regisseur::DEFAULT_CLI_SHORT_OPTIONS, array(
			'dbport::', 'dbname:', 'dbuser:', 'dbpswd:', 'hostlist::',
	));
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

$theDbHostsToTry = array(
		'0.0.0.0',
		'127.0.0.1',
		'172.17.0.1',
		'172.18.0.1',
		'172.19.0.1',
		'172.20.0.1',
        '172.21.0.1',
		'localhost',
		'docker.for.mac.localhost',
);
if (!empty($theCliOptions['hostlist']))
	array_merge($theDbHostsToTry, explode(',', $theCliOptions['hostlist']));

$theResult = null;
$theDbPort = (!empty($theCliOptions['dbport'])) ? $theCliOptions['dbport'] : 3306;
$theDbName = $theCliOptions['dbname'];
$theDbUser = $theCliOptions['dbuser'];
$theDbPswd = $theCliOptions['dbpswd'];
foreach($theDbHostsToTry as $theHost) {
	try {
		$director->debugLog("Attempting DBHOST [{$theHost}]...");
		$theDns = "mysql:host={$theHost};port={$theDbPort};dbname={$theDbName};charset=utf8";
		new \PDO($theDns, $theDbUser, $theDbPswd);
		$theResult = $theHost;
		break;
	} catch (\PDOException $x) {
		$director->debugLog("The DBHOST [{$theHost}] failed to connect: {$x->getMessage()}");
	}
}
if (!empty($theResult))
	print($theResult);
else
	$director->debugLog("The DBHOST [{$theHost}] connected!");

