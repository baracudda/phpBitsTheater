<?php
namespace com\blackmoonit\bits_theater;
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
	//passing in the ?url= (which .htaccess gives us) rather than $_SERVER['REQUEST_URI']
	if ($director->isDebugging()) {
		Strings::debugLog('aUrl='.$aUrl);
	}
	$theActorClass = array_shift($urlPathList);
	$theAction = array_shift($urlPathList);
	$theQuery = $urlPathList; //whatever is left
	//last one would have the ?queryvar=1&var2="blah" stuff to parse... somehow
	if (!empty($theActorClass)) {
		$theActorClass = BITS_BASE_NAMESPACE.'\\app\\actor\\'.Strings::getClassName($theActorClass);
		$theAction = Strings::getMethodName($theAction);
		if (!$director->raiseCurtain($theActorClass,$theAction,$theQuery)) {
			throw new app\FourOhFourExit($aUrl);
		}
	} elseif (!$director->isInstalled() && class_exists(BITS_BASE_NAMESPACE.'\\app\\actor\\Install')) {
		app\actor\Install::perform($director,'install',array());
	} elseif ($director->isInstalled() && empty($aUrl)) {
		header('Location: '.BITS_URL.app\config\Settings::PAGE_Landing);
	} else {
		throw new app\FourOhFourExit($aUrl);
	}
}

//Strings::debugLog('uri:'.$_SERVER['REQUEST_URI']);
removeMagicQuotes();
unregisterGlobals();
global $director;
$director = new app\Director();
route_request(REQUEST_URL);
}//end namespace
