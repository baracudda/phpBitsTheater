<?php
use BitsTheater\Regisseur; /* @var $theStageManager Rigisseur */
use BitsTheater\Director;
use com\blackmoonit\Strings;

//initial defines before command line options are checked
$theSitePath = dirname(dirname(dirname(__FILE__)));
require_once( $theSitePath . DIRECTORY_SEPARATOR . 'bootstrap.php');

//NOTE: at this point, classes can now be autoloaded.

//start the Director
$director = Director::requisition();

//define some generic functions
function dumpvar($x)
{ print( PHP_EOL . Strings::debugStr($x,null) ); }

/* EXAMPLE CLI OPTIONS

//check for command line options - can be done at any time
$theOptions = getopt('u:p:h:');
if (!empty($theOptions['u']))
	$_SERVER['PHP_AUTH_USER'] = $theOptions['u'];
if (!empty($theOptions['p']))
	$_SERVER['PHP_AUTH_PW'] = $theOptions['p'];
//probably not needed anymore as it guesses based on non-localhost folders in configs folder.
if (!empty($theOptions['h']))
{
	global $theStageManager;
	$theStageManager->defineConfigPath($theOptions['h']);
}

 */



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
require_once('cli_bootstrap.php');
$director->raiseCurtain('Actor', 'method');

************************************************/
/************************************************
IF YOUR Actor::method() REQUIRES AUTHENTICATION, CALL WITH ARGUMENTS:

$ someCliAction -uUser -pMyPassword

************************************************/
/************************************************
BitsTheaters\costumes\WornForCLI trait may contain useful methods for your
classes that need to operate in CLI mode.
************************************************/
