#!/usr/bin/php
<?php
use BitsTheater\costumes\DbAdmin;
use BitsTheater\scenes\Install as InstallScene;
use com\blackmoonit\FileUtils;
use com\blackmoonit\Strings;
	
global $director;

/**
 * CLI options should be defined by the special function `process_cli_options($aStageManger)`
 * @param Regisseur $aStageManager
 * @return string[] Returns the CLI arguments.
 */
function process_cli_options( $aStageManager )
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI('', array(
        'bootstrap',
 		'help',
		'schema',
		'admin',
		'verbose',
        'adminuser:',
        'adminpass:',
		'appid:',
		'config:',
        'dbhost:',
		'dbname:',
        'dbpass:',
        'dbuser:',
		'email:',
		'host:',
        'locale:',
		'password:',
		'username:',
	));
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

define('CONFIG_PATH', getenv('CONFIG_PATH') ?: BITS_CFG_PATH);
define('TEMPLATE_PATH', getenv('TEMPLATE_PATH') ?: BITS_RES_PATH.'templates'. DIRECTORY_SEPARATOR);

define('VERBOSE', isset($theCliOptions['verbose']));
if (!VERBOSE) {
	error_reporting(E_ALL ^ E_WARNING);
}

function usage()
{
	$theReadMeText = <<<EOF

This script provides the ability to initialize the config files and database for a new Pulse installation.

usage: php actionInstallSite.php [options]

global options:
	
	--help		Display this help message and exit
	--host=		Set the Pulse config path folder; defaults to 'localhost'
	--bootstrap	If enabled, creates or overwrites config files
	--config X=Y	Set Pulse config variables; may be repeated
	--schema	If enabled, initializes the database schema
	--admin		If enabled, creates a new admin account
	--verbose	If enabled, display additional logging output

bootstrap options:

	--dbname=	Set the SQL database name
	--dbhost=	Set the SQL host
	--dbuser=	Set the SQL account username
	--dbpass=	Set the SQL account password
	--locale=	Set the Pulse locale; defaults to 'en/US'
	--appid=	Set the Pulse appid; defaults to auto-generated value
	--adminuser=	Admin (root) SQL username for initializing database
	--adminpass=	Admin (root) SQL password for initializing database

titan options:

	--username	Set the account username
	--email		Set the account email
	--password	Set the account password

If '--bootstrap' is specified, '--db*' config settings are required; '--adminuser' and
'--adminpass' options are not required if the configured user and database already exist.

EOF;
	print($theReadMeText . PHP_EOL);
}

function make_config( $aConfigFilename, $aTemplateFilename, $vars )
{
	$theNewFile = CONFIG_PATH . $aConfigFilename;
	print('creating ' . $theNewFile . PHP_EOL);
	FileUtils::copyFileContents(TEMPLATE_PATH . $aTemplateFilename, $theNewFile, $vars);
}

function install_settings( $aCliOptions )
{
	global $director;
	$vars = array();
	$vars = ['landing_page'] = $director->getRes('install/landing_page');
	$vars = ['app_id'] = Strings::createUUID();
	if ( !empty($aCliOptions['appid']) ) {
		$vars = ['app_id'] = $aCliOptions['appid'];
	}
	make_config('Settings.php', 'Settings.tpl', $vars);
}

function install_lang( $aCliOptions )
{
	//default locale to en/US
	if ( empty($aCliOptions['locale']) ) {
		$aCliOptions['locale'] = 'en/US';
	}
	//verify locale is in the expected format "xx/yy"
	$theLocale = explode('/', $aCliOptions['locale']);
	if ( count($theLocale) != 2 ) {
		throw new \Exception('Invalid locale: ' . $aCliOptions['locale']);
	}
	$vars = [
		'default_lang' => $locale[0],
		'default_region' => $locale[1],
	];			
	make_config('I18N.php', 'I18N.tpl', $vars);
}

define('SQL_ERR_USER', 1045);
define('SQL_ERR_DB', 1049);

function install_dbconn( $aCliOptions )
{
	$vars = array(
		'dns_scheme' => 'ini',
		'dbtype' => 'mysql',
		'dbhost' => $aCliOptions['dbhost'],
		'dbname' => $aCliOptions['dbname'],
		'dbuser' => $aCliOptions['dbuser'],
		'dbpwrd' => $aCliOptions['dbpass'],
		'dns_alias' => '',
		'dns_uri' => '',
		'dns_custom' => '',
	);

	$vars['table_prefix'] = 'webapp_';
	make_config('dbconn-webapp.ini', 'dbconn-webapp.tpl', $vars);
	
	$vars['table_prefix'] = APP_DB_CONN_NAME . '_';
	$theFilename = 'dbconn-' . APP_DB_CONN_NAME;
	make_config($theFilename . '.ini', $theFilename . '.tpl', $vars);
}

function install_db( $aCliOptions, $err = 0 )
{
	global $director;
	//create the $dbAdmin class here, before the Install scene class, to avoid
	//  a strange PHP error:
	//  PHP Fatal error:  Cannot use BitsTheater\costumes\DbConnInfo as DbConnInfo
	//      because the name is already in use in
	//      /opt/www/site/app/costumes/Wardrobe/DbAdmin.php on line 22
	$dbAdmin = new DbAdmin($director);
	try {
		$theDbConnInfo = $director->getDbConnInfo();
		$theDbConnInfo->loadDbConnInfoFromIniFile(CONFIG_PATH.'dbconn-webapp.ini');
		$theDbConnInfo->connect();
		print('creating database tables'.PHP_EOL);
		$setupDb = $director->getProp('SetupDb');
		$setupDb->setupModels(new InstallScene($director));
	}
	catch ( \PDOException $x ) {
		$code = $x->getCode();
		if ( $code == $err ) {
			$blx = BrokenLeg::tossException($director, $x);
			print($blx->getExtendedErrMsg() . PHP_EOL);
			exit(1);
		}		
		switch ( $code ) {
			case SQL_ERR_USER:
			case SQL_ERR_DB:
			{
				if ( empty($aCliOptions['adminuser']) || empty($aCliOptions['adminpass']) ) {
					print('Error: cannot initialize DB without --adminuser and --adminpass');
					exit(1);
				}
				print('initializing database' . PHP_EOL);
				$dbAdmin->admin_dbuser = $aCliOptions['adminuser'];
				$dbAdmin->admin_dbpass = $aCliOptions['adminpass'];
				$dbAdmin->dbname = $theDbConnInfo->dbConnSettings->dbname;
				$dbAdmin->dbtype = $theDbConnInfo->dbConnSettings->driver;
				$dbAdmin->dbhost = $theDbConnInfo->dbConnSettings->host;
				$dbAdmin->dbport = $theDbConnInfo->dbConnSettings->port;
				$dbAdmin->dbuser = $theDbConnInfo->dbConnSettings->username;
				$dbAdmin->dbpass = base64_decode($theDbConnInfo->dbConnSettings->password);
				$dbAdmin->dbcharset = $theDbConnInfo->dbConnSettings->charset;
				$dbAdmin->table_prefix = $theDbConnInfo->dbConnOptions->table_prefix;

				$dbAdmin->createDbFromUserInput($dbAdmin, $theDbConnInfo);

				//retry, passing in error code for loop limiting
				install_db($aCliOptions, $code);
				break;
			}
			default:
			{
				$blx = BrokenLeg::tossException($director, $x);
				print($blx->getExtendedErrMsg() . PHP_EOL);
				exit(1);
			}
		}
	}
}

function install_admin( $aCliOptions )
{
	global $director;
	//check for required fields	
	$err = [];
	if ( empty($aCliOptions['username']) ) {
		$err[] = '--username required';
	}
	if ( empty($aCliOptions['email']) ) {
		$err[] = '--email required';
	}
	if ( empty($aCliOptions['password']) ) {
		$err[] = '--password required';
	}
	if ( !empty($err) ) {
		print(implode(PHP_EOL, $err));
		exit(1);
	}
	//create the first account which becomes admin by default
	print('creating admin account' . PHP_EOL);	
	$dbAuth = $director->getProp('Auth');
	$dbAuthGroups = $director->getProp('AuthGroups');
	$theAdminGroupID = $dbAuthGroups->findGroupIdByRegCode($director->app_id);
	$theAccount = array(
		$dbAuth::KEY_userinfo => $aCliOptions['username'],
		'email' => $aCliOptions['email'],
		$dbAuth::KEY_pwinput => $aCliOptions['password'],
	);
	$dbAuth->registerAccount($theAccount, [$theAdminGroupID]);
}

function install_configs( $aCliOptions )
{
	global $director;
	$dbConfig = $director->getProp('Config');
	$theConfigs = is_array($aCliOptions['config']) ? $aCliOptions['config'] : array($aCliOptions['config']);
	foreach( $theConfigs as $conf) {
		try {
			@list($kk, $vv) = explode('=', $conf);
			$old = $dbConfig[$kk];
			if (VERBOSE) {
				print("Setting '$kk': '$vv' (was: '$old')".PHP_EOL);
			}
			$dbConfig[$kk] = $vv;
			
		}
		catch ( \Exception $x) {
			$blx = BrokenLeg::tossException($director, $x);
			print($blx->getExtendedErrMsg() . PHP_EOL);
			exit(1);
		}
	}
}

/**************************************************************************************
 *     SCRIPT FLOW STARTS HERE
 **************************************************************************************/

if (isset($theCliOptions['help'])) {
	usage();
	exit(0);
}
try {
	if ( isset($theCliOptions['bootstrap']) ) {
		install_settings($theCliOptions);
		install_lang($theCliOptions);
		install_dbconn($theCliOptions);
	}
	if ( isset($theCliOptions['schema']) ) {
		install_db($theCliOptions);
	}
	if ( isset($theCliOptions['admin']) ) {
		install_admin($theCliOptions);
	}
	if ( isset($theCliOptions['config']) ) {
		install_configs($theCliOptions);
	}
}
catch( \Exception $x ) {
	$blx = BrokenLeg::tossException($director, $x);
	print($blx->getExtendedErrMsg() . PHP_EOL);
	exit(1);
}
print('OK'.PHP_EOL);
