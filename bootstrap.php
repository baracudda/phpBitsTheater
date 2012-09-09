<?php
/**
 * You are highly encouraged to find/replace this namespace with your own. (search for *.php and *.tpl)
 */
namespace com\blackmoonit\bits_theater;
{//namespace begin

/**********************************
 * define required CONSTANTS
 **********************************/
define('BITS_BASE_NAMESPACE',__NAMESPACE__);
define('¦',DIRECTORY_SEPARATOR);
//paths
define('BITS_ROOT',dirname(__FILE__));
define('BITS_PATH',BITS_ROOT.¦);
define('BITS_LIB_PATH',BITS_PATH.'lib'.¦);
define('BITS_RES_PATH',BITS_PATH.'res'.¦);
define('BITS_APP_PATH',BITS_PATH.'app'.¦);
define('BITS_CFG_PATH',BITS_APP_PATH.'config'.¦);

//domain url
define('SERVER_URL',((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS']=='on')?'https':'http').'://'.$_SERVER['SERVER_NAME'].
		(($_SERVER["SERVER_PORT"]=="80")?'':':'.$_SERVER["SERVER_PORT"]).'/');
//relative urls
define('REQUEST_URL',array_key_exists('url',$_GET)?$_GET['url']:'');
define('BITS_URL',dirname($_SERVER['PHP_SELF']));
define('BITS_RES',BITS_URL.'/res');
define('BITS_LIB',BITS_URL.'/lib');
//no need for app url as that is where all the urls normally get routed towards.

define('BITS_DB_INFO',BITS_CFG_PATH.'_dbconn_.ini');


/**********************************
 * load required modules
 **********************************/
//lib autoloader first
require_once(BITS_LIB_PATH.'autoloader.php');
//app autoloader next most frequent & priority
require_once(BITS_APP_PATH.'autoloader.php');
//res autoloader last
include_once(BITS_RES_PATH.'autoloader.php');

require_once('router.php');

}//end namespace