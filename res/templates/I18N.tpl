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

namespace com\blackmoonit\bits_theater\app\config;
{//begin namespace

final class I18N extends \stdClass {

	const LANG = '%lang%';
	const REGION = '%region%';
	
	const PATH_LANG = '%path_lang%';
	const PATH_REGION = '%path_region%';

	const DEFAULT_LANG = '%default_lang%';
	const DEFAULT_REGION = '%default_region%';
	
	const DEFAULT_PATH_LANG = '%default_path_lang%';
	const DEFAULT_PATH_REGION = '%default_path_region%';
	
	static public function findClassNamespace($aResourceClass) {
		/*
		$theClass = 'res\\i18n\\'.self::LANG.'\\'.self::REGION.'\\'.$aResourceClass;
		if (class_exists($theClass)) {
			return $theClass;
		} else {
			$theClass = 'res\\i18n\\'.self::LANG.'\\'.$aResourceClass;
			if (class_exists($theClass)) {
				return $theClass;
			} else {
				return 'res\\'.$aResourceClass;
			}
		}
		*/
		if (file_exists(self::PATH_REGION.$aResourceClass.'.php'))
			$theClass = self::LANG.'\\'.self::REGION.'\\'.$aResourceClass;
		elseif (file_exists(self::PATH_LANG.$aResourceClass.'.php'))
			$theClass = self::LANG.'\\'.$aResourceClass;
		elseif (file_exists(BITS_RES_PATH.$aResourceClass.'.php'))
			$theClass = $aResourceClass;
		else
			$theClass = 'Resources';
		return BITS_BASE_NAMESPACE.'\\res\\'.$theClass;
	}

	static public function findDefaultClassNamespace($aResourceClass) {
		/*
		$theClass = 'res\\'.self::DEFAULT_LANG.'\\'.self::DEFAULT_REGION.'\\'.$aResourceClass;
		if (class_exists($theClass)) {
			return $theClass;
		} else {
			$theClass = 'res\\'.self::DEFAULT_LANG.'\\'.$aResourceClass;
			if (class_exists($theClass)) {
				return $theClass;
			} else {
				return 'res\\'.$aResourceClass;
			}
		}
		*/
		if (file_exists(self::DEFAULT_PATH_REGION.$aResourceClass.'.php'))
			$theClass = self::DEFAULT_LANG.'\\'.self::DEFAULT_REGION.'\\'.$aResourceClass;
		elseif (file_exists(self::DEFAULT_PATH_LANG.$aResourceClass.'.php'))
			$theClass = self::DEFAULT_LANG.'\\'.$aResourceClass;
		elseif (file_exists(BITS_RES_PATH.$aResourceClass.'.php'))
			$theClass = $aResourceClass;
		else
			$theClass = 'Resources';
		return BITS_BASE_NAMESPACE.'\\res\\'.$theClass;
	}

}//end class

}//end namespace

