<?php
namespace res;
use app\config\I18N;
use com\blackmoonit\Strings;
{//begin namespace

function autoloader($aClassName) {
	if (!Strings::beginsWith($aClassName,__NAMESPACE__)) return;
	//convert namespace format ns\sub-ns\classname into folder paths
	$theClassFile = str_replace('\\', DIRECTORY_SEPARATOR, Strings::strstr_after($aClassName,__NAMESPACE__.'\\')).'.php';
	if ($theClassFile{2}==DIRECTORY_SEPARATOR)
		$theClassNamePath = BITS_RES_PATH.'i18n'.DIRECTORY_SEPARATOR.$theClassFile;
	else
		$theClassNamePath = BITS_RES_PATH.$theClassFile;
	if (is_file($theClassNamePath)) {
		return include_once($theClassNamePath);
	}
}

spl_autoload_register(__NAMESPACE__ .'\autoloader');
}//end namespace
