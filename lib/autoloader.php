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

namespace com\blackmoonit\bits_theater\lib;
{//begin namespace

/*
 * CLASS_AUTOLOAD_PATHS can be defined outside to whatever paths your scripts need.
 * It is a semicolon seperated path list relative to the script including this file.
 * IMPORTANT: Make sure you end all non-empty paths with a path seperator!
 * IMPORTANT: Relative paths are relative to the page that includes autoloader.php!
 */
if (!defined('CLASS_AUTOLOAD_PATHS')) {
	define('CLASS_AUTOLOAD_PATHS',
			BITS_PATH.'lib'.DIRECTORY_SEPARATOR.';'.
			BITS_PATH.'includes'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.';'.
			'');
}

/*
 * CLASS_FILENAME_FORMATS can be defined to match your filename conventions for classes.
 * It is a semicolon seperated string of filename formats where %s is the class name being referenced. 
 */
if (!defined('CLASS_FILENAME_FORMATS')) {
	define('CLASS_FILENAME_FORMATS','%s.php;%s.class.php;class.%s.php');
}

/*
 * @param string $aClassName
 * 		Class or Interface name automatically passed to this function by the PHP Interpreter.
 * 		Function is capable of handling the PEAR style of naming classes, but will not use the
 * 		list of CLASS_AUTOLOAD_PATHS or CLASS_FILENAME_FORMATS in that case.
 */
function autoloader($aClassName){
	$folder_list = explode(';',CLASS_AUTOLOAD_PATHS);

	if (!strpos($aClassName,'\\')) { //only try this section if namespace not detected
		//try the PEAR style of naming classes
		$theClassNamePath = str_ireplace('_', DIRECTORY_SEPARATOR, $aClassName);
		foreach ($folder_list as $theFolder) {
			$theClassFile = $theFolder.$theClassNamePath.'.php';
			//\app\debugLog(__NAMESPACE__.$theClassFile);
			if (is_file($theClassFile) && (include $theClassFile)) {
				return true;
			}
		}
	}
	
	//class not found as PEAR style, try namespace style
	
	$fileNameFormat_list = explode(';',CLASS_FILENAME_FORMATS);
	//convert namespace format ns\sub-ns\classname into folder paths
	$theClassNamePath = str_replace('\\', DIRECTORY_SEPARATOR, $aClassName);
	$theClassFolder = dirname($theClassNamePath).DIRECTORY_SEPARATOR;
	$theClassName = basename($theClassNamePath);
	foreach ($folder_list as $theFolder) {
		foreach ($fileNameFormat_list as $theFileNameFormat) {
			$theFileName = sprintf($theFileNameFormat,$theClassName);
			$theClassPath = $theFolder.$theClassFolder.$theFileName;
			$theClassPathAlt = $theFolder.$theFileName;
			//\app\debugLog(__NAMESPACE__.$theClassPath);
			//\app\debugLog(__NAMESPACE__.$theClassPathAlt);
			if (is_file($theClassPath) || is_file($theClassPathAlt)) {
				if (include $theClassPath) return true;
				if (include $theClassPathAlt) return true;
			}
		}
	}
	
	//class not found
	return false;
}

spl_autoload_register(__NAMESPACE__ .'\autoloader');
}//end namespace
