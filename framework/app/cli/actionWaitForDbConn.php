#!/usr/bin/php
<?php
use BitsTheater\Director; /* @var $director Director */
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\BrokenLeg;
use BitsTheater\Model as BasicModel;

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

global $director;

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

//waiting on MySQL service to be available means we cannot expect any
//  particular database to exist, yet (e.g. GDK waiting to create db).
//  Use a special "db admin connection" that does not specify any db.

$theModel = new BasicModel();
$theModel->director = $director;
$theWebAppDbConn = new DbConnInfo();
$theWebAppDbConn->loadDbConnInfo();
$theWebAppDbConn->cnvToAdminConn(
		getMyDbConnEnvVar('ADMINUSER'),
		getMyDbConnEnvVar('ADMINPSWD')
);

$bConnectionAvailable = false;
while ( !$bConnectionAvailable ) {
	try {
		// first, a new Model object with the default connection
		// then we have our model object connect to the new connection
		$theModel->connectTo($theWebAppDbConn);
		print('Database connection available!' . PHP_EOL);
		$bConnectionAvailable = true;
	}
	catch ( BrokenLeg $x ) {
		if ( $x->getCondition() == BrokenLeg::ACT_DB_CONNECTION_FAILED ) {
			//print(\com\blackmoonit\Strings::debugStr($x).PHP_EOL);
			print('Database connection unavailable, waiting for 3 seconds before trying again.' . PHP_EOL);
			sleep(3);
		}
		else {
			print($x->getMessage().PHP_EOL);
			break;
		}
	}
}
