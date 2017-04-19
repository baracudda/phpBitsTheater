<?php
use com\blackmoonit\Strings;
use BitsTheater\Director;
//initial defines before command line options are checked
$theSitePath = dirname(dirname(dirname(__FILE__)));
define('BITS_URL', str_replace(DIRECTORY_SEPARATOR, '/', $theSitePath) );
define('VIRTUAL_HOST_NAME', 'local-cli');
//now check for command line options
$theOptions = getopt('u:p:h:');
if (!empty($theOptions['u']))
	$_SERVER['PHP_AUTH_USER'] = $theOptions['u'];
if (!empty($theOptions['p']))
	$_SERVER['PHP_AUTH_PW'] = $theOptions['p'];
if (!empty($theOptions['h']))
	$_SERVER['SERVER_NAME'] = $theOptions['h'];
//after command line options are checked, Bootstrap time
require_once(__DIR__.DIRECTORY_SEPARATOR.'../../bootstrap.php');
Strings::debugPrefix( '[local-cli-dbg] ');
Strings::errorPrefix( '[local-cli-err] ');
//start the Director
$director = new Director();
//define some generic functions
function dumpvar($x)
{ print( PHP_EOL . Strings::debugStr($x,null) ); }


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
