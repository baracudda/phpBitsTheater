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

namespace BitsTheater\res;
use \stdClass as BaseI18N;
{//begin namespace

class ResI18N extends BaseI18N {
	const DEFAULT_LANG = 'en';
	const DEFAULT_REGION = 'US';
	public $userLang;
	public $userRegion;
	public $resPathBase = BITS_RES_PATH;
	public $resPathLang;
	public $resPathRegion;
	public $resDefaultPathLang;
	public $resDefaultPathRegion;
	
	public function __construct($aUserI18n=null) {
		$this->userLang = static::DEFAULT_LANG;
		$this->userRegion = static::DEFAULT_REGION;
		$this->resDefaultPathLang = $this->resPathBase.'i18n'.¦.static::DEFAULT_LANG.¦;
		$this->resDefaultPathRegion = $this->resDefaultPathLang.static::DEFAULT_REGION.¦;
		$this->resPathLang = $this->resPathBase.'i18n'.¦.$this->userLang.¦;
		$this->resPathRegion = $this->resPathLang.$this->userRegion.¦;
		if (!empty($aUserI18n))
			$this->setUserI18n($aUserI18n);
	}
	
	public function setUserI18n($aUserI18n) {
		$theUserI18nParts = explode('/',$aUserI18n);
		$this->userLang = array_shift($theUserI18nParts);
		$this->userRegion = array_shift($theUserI18nParts);
		$this->resPathLang = $this->resPathBase.'i18n'.¦.$this->userLang.¦;
		$this->resPathRegion = $this->resPathLang.$this->userRegion.¦;
	}
	
	public function isUsingDefault() {
		return ($this->userLang==static::DEFAULT_LANG && $this->userRegion==static::DEFAULT_REGION);
	}
	
	public function includeResClass($aResClass) {
		$theResClassFile = $aResClass.'.php';
		if (file_exists($this->resPathRegion.$theResClassFile) && (include_once($this->resPathRegion.$theResClassFile))) {
			return BITS_NAMESPACE_RES.$this->userLang.'\\'.$this->userRegion.'\\'.$aResClass;
		} elseif (file_exists($this->resPathLang.$theResClassFile) && (include_once($this->resPathLang.$theResClassFile))) {
			return BITS_NAMESPACE_RES.$this->userLang.'\\'.$aResClass;
		} elseif (file_exists($this->resPathBase.$theResClassFile) && (include_once($this->resPathBase.$theResClassFile))) {
			return BITS_NAMESPACE_RES.$aResClass;
		} elseif ((include_once($this->resPathBase.'Resources.php'))) {
			return BITS_NAMESPACE_RES.'Resources';
		}
	}
	
	public function includeDefaultResClass($aResClass) {
		$theResClassFile = $aResClass.'.php';
		if (file_exists($this->resDefaultPathRegion.$theResClassFile) && (include_once($this->resDefaultPathRegion.$theResClassFile))) {
			return BITS_NAMESPACE_RES.static::DEFAULT_LANG.'\\'.static::DEFAULT_REGION.'\\'.$aResClass;
		} elseif (file_exists($this->resDefaultPathLang.$theResClassFile) && (include_once($this->resDefaultPathLang.$theResClassFile))) {
			return BITS_NAMESPACE_RES.static::DEFAULT_LANG.'\\'.$aResClass;
		} elseif (file_exists($this->resPathBase.$theResClassFile) && (include_once($this->resPathBase.$theResClassFile))) {
			return BITS_NAMESPACE_RES.$aResClass;
		} elseif ((include_once($this->resPathBase.'Resources.php'))) {
			return BITS_NAMESPACE_RES.'Resources';
		}
	}
	
}//end class

}//end namespace
