<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace com\blackmoonit\bits_theater;
use com\blackmoonit\bits_theater\app\Director;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\FourOhFourExit;
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
function route_request(Director $aDirector, $aUrl) {
	//passing in the ?url= (which .htaccess gives us) rather than $_SERVER['REQUEST_URI']
	if ($aDirector->isDebugging()) Strings::debugLog('aUrl='.$aUrl);
	//if ($aDirector->isDebugging() && $aUrl=='phpinfo') { print(phpinfo()); return; }
	if (!empty($aUrl)) {
		$urlPathList = explode("/",$aUrl);
		$theActorClass = array_shift($urlPathList);
		$theAction = array_shift($urlPathList);
		$theQuery = $urlPathList; //whatever is left
	}
	if (!empty($theActorClass)) {
		$theActorClass = BITS_NAMESPACE_ACTOR.Strings::getClassName($theActorClass);
		$theAction = Strings::getMethodName($theAction);
		if (!$aDirector->raiseCurtain($theActorClass,$theAction,$theQuery)) {
			throw new FourOhFourExit($aUrl);
		}
	} elseif (!$aDirector->isInstalled() && class_exists(BITS_NAMESPACE_ACTOR.'Install')) {
		$aDirector->raiseCurtain(BITS_NAMESPACE_ACTOR.'Install', 'install');
	} elseif ($aDirector->isInstalled() && empty($aUrl)) {
		$theSettingsClass = BITS_NAMESPACE_CFG.'Settings';
		header('Location: '.$theSettingsClass::getLandingPage());
	} else {
		throw new FourOhFourExit($aUrl);
	}
}

removeMagicQuotes();
unregisterGlobals();
if (is_file(BITS_CFG_PATH . 'Settings.php')) {
	$theSettingsClass = BITS_NAMESPACE_CFG.'Settings';
	define('WEBAPP_NAMESPACE',$theSettingsClass::getAppNamespace());
}
global $director; //exposed as a Global Var so legacy systems can interface with us
$director = new Director();
route_request($director, REQUEST_URL);
}//end namespace
