#!/usr/bin/php
<?php
use com\blackmoonit\Strings;
use Joka\models\Pipeline; /* @var $dbPipeline Pipeline */

require_once(__DIR__.DIRECTORY_SEPARATOR.'cli_bootstrap.php');
$theOptions = getopt('u:p:f');
if (!empty($theOptions['u']))
	$_SERVER['PHP_AUTH_USER'] = $theOptions['u'];
if (!empty($theOptions['p']))
	$_SERVER['PHP_AUTH_PW'] = $theOptions['p'];
$_POST['force_model_upgrade'] = ($theOptions['f']);

$director->raiseCurtain('Admin', 'apiWebsiteUpgrade');
print(PHP_EOL);
