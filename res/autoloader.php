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

namespace com\blackmoonit\bits_theater\res;
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
