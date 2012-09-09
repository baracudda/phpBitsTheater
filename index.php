<?php
namespace com\blackmoonit\bits_theater;
use com\blackmoonit\Strings;
{//namespace begin

try { 
	require_once('bootstrap.php');
} catch (app\FourOhFourExit $e404) {
	//Strings::debugLog('404 on '.$e404->getMessage());
	header("HTTP/1.0 404 Not Found");
	header("Status: 404 Not Found");
	$_SERVER['REDIRECT_STATUS'] = 404;
	header('Location: '.SERVER_URL.'error.php?url='.$e404->url); //if custom 404 page, found! else generates a 404 =)
} catch (app\SystemExit $se) {
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
