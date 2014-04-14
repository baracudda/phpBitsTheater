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

namespace BitsTheater;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\IDebuggableException;
use com\blackmoonit\exceptions\FourOhFourExit;
use com\blackmoonit\excpetions\SystemExit;
use \Exception;
{//namespace begin

try { 
	require_once('bootstrap.php');
	//now let us get on with it
	require_once('router.php');
} catch (FourOhFourExit $e404) {
	//Strings::debugLog('404 on '.$e404->getMessage());
	header("HTTP/1.0 404 Not Found");
	header("Status: 404 Not Found");
	$_SERVER['REDIRECT_STATUS'] = 404;
	header('Location: '.SERVER_URL.'error.php?url='.$e404->url); //if custom 404 page, found! else generates a 404 =)
} catch (SystemExit $se) {
	/* do nothing */
} catch (IDebuggableException $e) {
	$e->setDebugCheck(function() {
			return (!class_exists(BITS_NAMESPACE_CFGS.'Settings') || _DEBUG_APP);
		})->setCssFileUrl(BITS_RES.'/style/bits.css')->setFileRoot(BITS_ROOT);
	$e->debugPrint();
	if (ini_get('log_errors')) {
		Strings::debugLog($e->getMessage().' c_stk: '.$e->getTraceAsString());
	}
	header("HTTP/1.0 500 Internal Server Error");
	die();
} catch (Exception $e) { 
	if (is_callable(array($e,'debugPrint'))) {
		$e->debugPrint();
	} else if (ini_get('display_errors')) {
		print($e->getMessage()."<br />\n");
		$theTrace = str_replace("\n","<br />\n",$e->getTraceAsString());
		$theTrace = str_replace(BITS_ROOT,'[%site]',$theTrace);
		print($theTrace);
	}
	if (ini_get('log_errors')) {
		Strings::debugLog($e->getMessage().' cs: '.$e->getTraceAsString());
	}
	header("HTTP/1.0 500 Internal Server Error"); 
	die(); 
}

}//end namespace
