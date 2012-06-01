<?php
namespace app;
use com\blackmoonit\Strings;
{//namespace begin

/*
 * @param string $aClassName
 * 		Class or Interface name automatically passed to this function by the PHP Interpreter.
 */
function autoloader($aClassName){
	//debugLog('al1: '.$aClassName);
	if (!Strings::beginsWith($aClassName,__NAMESPACE__)) return;
	//convert namespace format ns\sub-ns\classname into folder paths
	$theClassNamePath = BITS_PATH.str_replace('\\', DIRECTORY_SEPARATOR, $aClassName).'.php';
	//debugLog('al: '.$theClassNamePath);
	if (is_file($theClassNamePath)) {
		return include_once $theClassNamePath;
	}
}

spl_autoload_register(__NAMESPACE__ .'\autoloader');
}//end namespace
