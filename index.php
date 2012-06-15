<?php
namespace app;
use com\blackmoonit\Strings;
{
//paths
define('BITS_ROOT',dirname(__FILE__));
define('BITS_PATH',BITS_ROOT.DIRECTORY_SEPARATOR);
define('BITS_LIB_PATH',BITS_PATH.'lib'.DIRECTORY_SEPARATOR);
define('BITS_RES_PATH',BITS_PATH.'res'.DIRECTORY_SEPARATOR);

//domain url
define('SERVER_URL',((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS']=='on')?'https':'http').'://'.$_SERVER['SERVER_NAME'].
		(($_SERVER["SERVER_PORT"]=="80")?'':':'.$_SERVER["SERVER_PORT"]).DIRECTORY_SEPARATOR);
//relative urls
define('REQUEST_URL',array_key_exists('url',$_GET)?$_GET['url']:'');
define('BITS_URL',dirname($_SERVER['PHP_SELF']));
define('BITS_RES',BITS_URL.'/res');
define('BITS_LIB',BITS_URL.'/lib');
//define('BITS_LIB','lib');

define('BITS_DB_INFO',BITS_PATH.'app'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'_dbconn_.ini');

class FourOhFourExit extends \Exception { 
	public $url = '';
	function __construct($aUrl,$code=404,$previous=NULL) {
		parent::__construct("Page Not Found: ".$aUrl,$code,$previous);
		$this->url = $aUrl;
	}
}
class MigrateRoute extends \Exception {}
class SystemExit extends \Exception {}
try { 
	require_once (BITS_LIB_PATH.'bootstrap.php');
} catch (FourOhFourExit $e404) {
	//Strings::debugLog('404 on '.$e404->getMessage());
	header("HTTP/1.0 404 Not Found");
	header("Status: 404 Not Found");
	$_SERVER['REDIRECT_STATUS'] = 404;
	header('Location: '.SERVER_URL.'error.php?url='.$e404->url); //if custom 404 page, found! else generates a 404 =)
} catch (MigrateRoute $eMR) {
	//require_once('migrate_router.php');
} catch (SystemExit $se) {
	/* do nothing */
} catch (\Exception $e) { 
	if (is_callable(array($e,'debugPrint'))) {
		$e->debugPrint();
	} else if (ini_get('display_errors')) {
		print $e->getMessage()."<br />\n";
		print str_replace("\n","<br />\n",$e->getTraceAsString());
	}
	if (ini_get('log_errors')) {
		Strings::debugLog($e->getMessage());
	}
	header("HTTP/1.0 500 Internal Server Error"); 
	die(); 
}

}//end namespace
