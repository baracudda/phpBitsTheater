<?php
namespace app;
use app\Director;
use app\config\Settings;
use com\blackmoonit\Strings;
{//begin namespace

/*
 * Check for Magic Quotes and remove them.
 */
function stripSlashesDeep($value) {
	$value = is_array($value) ? array_map('stripSlashesDeep', $value) : stripslashes($value);
	return $value;
}

function removeMagicQuotes() {
	if ( get_magic_quotes_gpc() ) {
		$_GET    = stripSlashesDeep($_GET   );
		$_POST   = stripSlashesDeep($_POST  );
		$_COOKIE = stripSlashesDeep($_COOKIE);
	}
}

/*
 * Check register globals and remove them since they are copies 
 * of the PHP global vars and are security risks.
 */
function unregisterGlobals() {
    if (ini_get('register_globals')) {
        $array = array('_SESSION', '_POST', '_GET', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');
        foreach ($array as $value) {
            foreach ($GLOBALS[$value] as $key => $var) {
                if ($var === $GLOBALS[$key]) {
                    unset($GLOBALS[$key]);
                }
            }
        }
    }
}

/*
 * Route the URL requested to the approprate actor.
 */
function route_request($aUrl) {
	global $director; //exposed as a Global Var so legacy systems can interface with us
	$urlPathList = array();
	$urlPathList = explode("/",$aUrl);
	/*passing in the ?url= (which .htaccess gives us) rather than $_SERVER['REQUEST_URI']
	//remove static path segments until we reach virtual sections
	$staticPath = explode("/",BITS_URL);
	foreach ($staticPath as $pathSegment) {
		array_shift($urlPath);
	}
	*/
	//what is left in urlPath is the virtual sections
	if ($director->isDebugging())
		Strings::debugLog('aUrl='.implode('/',$urlPathList));
		
	$theActorClass = array_shift($urlPathList);
	$theAction = array_shift($urlPathList);
	$theQuery = $urlPathList; //whatever is left
	if (!empty($theActorClass)) {
		$theActorClass = 'app\\actor\\'.Strings::getClassName($theActorClass);
		$theAction = Strings::getMethodName($theAction);
		if (!$director->raiseCurtain($theActorClass,$theAction,$theQuery)) {
			throw new FourOhFourExit($aUrl);
		}
	} elseif (!$director->isInstalled() && class_exists('\\app\\actor\\Install')) {
		\app\actor\Install::perform($director,'install',array());
	} elseif ($director->isInstalled() && empty($aUrl)) {
		header('Location: '.BITS_URL.Settings::PAGE_Landing);
	} else {
		throw new FourOhFourExit($aUrl);
	}
}

//Strings::debugLog('uri:'.$_SERVER['REQUEST_URI']);
removeMagicQuotes();
unregisterGlobals();
global $director;
$director = new Director();
route_request(REQUEST_URL);
}//end namespace
