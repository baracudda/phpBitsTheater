<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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
use com\blackmoonit\Strings;
{//namespace begin

/*
 * @param string $aClassName
 * 		Class or Interface name automatically passed to this function by the PHP Interpreter.
 */
function autoloader($aClassName) {
	//debugLog('al1: '.$aClassName);
	//if (!Strings::beginsWith($aClassName,BITS_NAMESPACE_BASE)) return;
	if (Strings::beginsWith($aClassName,BITS_NAMESPACE_CFG)) {
		//cfg_path incorporates $_SERVER['SERVER_NAME'] so that live config and localhost sandbox can coexist and avoids
		//  getting overwritten accidentally if checked into a source code control mechanism
		$theClassNamePath = BITS_CFG_PATH.str_replace('\\', ¦, Strings::strstr_after($aClassName,BITS_NAMESPACE_CFG)).'.php';
	} elseif (Strings::beginsWith($aClassName,BITS_NAMESPACE_APP)) {
		//convert namespace format ns\sub-ns\classname into folder paths
		$theClassNamePath = BITS_APP_PATH.str_replace('\\', ¦, Strings::strstr_after($aClassName,BITS_NAMESPACE_APP)).'.php';
	} elseif (Strings::beginsWith($aClassName,BITS_NAMESPACE_RES)) {
		//convert namespace format ns\sub-ns\classname into folder paths
		$theClassFile = str_replace('\\', ¦, Strings::strstr_after($aClassName,BITS_NAMESPACE_RES)).'.php';
		if ($theClassFile{2}==¦) //en, de, es, etc. 2 letter language codes get directed to the i18n folder
			$theClassNamePath = BITS_RES_PATH.'i18n'.¦.$theClassFile;
		else
			$theClassNamePath = BITS_RES_PATH.$theClassFile;
	} else {
		$theClassNamePath = $aClassName;
	}
	
	//debugLog('al: '.$theClassNamePath);
	if (is_file($theClassNamePath)) {
		return include_once($theClassNamePath);
	}
}

spl_autoload_register(BITS_NAMESPACE_BASE .'\autoloader');
}//end namespace
