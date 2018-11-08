#!/usr/bin/php
<?php
use com\blackmoonit\Strings;

//additional options we wish to parse besides the default -u -p and --host
//nothing additional to do

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

print(Strings::createUUID());

//end output with an EOL so CLI prompt will alway appear correctly on a fresh line
print(PHP_EOL);
