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
	const DEFAULT_LANG = '%default_lang%';
	const DEFAULT_REGION = '%default_region%';
	private $userLang = self::DEFAULT_LANG;
	private $userRegion = self::DEFAULT_REGION;
	private $resPathBase = BITS_RES_PATH;
	private $resPathLang;
	private $resPathRegion;
	private $resDefaultPathLang;
	private $resDefaultPathRegion;
	
	public function __construct($aUserI18n=null) {
		$this->resDefaultPathLang = $this->resPathBase.'i18n'.¦.self::DEFAULT_LANG.¦;
		$this->resDefaultPathRegion = $this->resDefaultPathLang.self::DEFAULT_REGION.¦;
		$this->resPathLang = $this->resPathBase.'i18n'.¦.$this->userLang.¦;
		$this->resPathRegion = $this->resPathLang.$this->userRegion.¦;
		if (!empty($aUserI18n))
			$this->setUserI18n($aUserI18n);
	}
	
	public function setUserI18n($aUserI18n) {
		$theUserI18nParts = explode('/',$aUserI18n);
		$this->resPathLang = $this->resPathBase.'i18n'.¦.array_shift($theUserI18nParts).¦;
		$this->resPathRegion = $this->resPathLang.array_shift($theUserI18nParts).¦;
	}
	
	public function isUsingDefault() {
		return ($this->userLang==self::DEFAULT_LANG && $this->userRegion==self::DEFAULT_REGION);
	}
	
	public function includeResClass($aResClass) {
		$theResClassFile = $aResClass.'.php';
		if (file_exists($this->resPathRegion.$theResClassFile) && include_once($this->resPathRegion.$theResClassFile))
			return BITS_NAMESPACE_RES.$this->userLang.'\\'.$this->userRegion.'\\'.$aResClass;
		elseif (file_exists($this->resPathLang.$theResClassFile) && include_once($this->resPathLang.$theResClassFile))
			return BITS_NAMESPACE_RES.$this->userLang.'\\'.$aResClass;
		elseif (file_exists($this->resPathBase.$theResClassFile) && include_once($this->resPathBase.$theResClassFile))
			return BITS_NAMESPACE_RES.$aResClass;
		elseif (include_once($this->resPathBase.'Resources'))
			return BITS_NAMESPACE_RES.'Resources';
	}
	
	public function includeDefaultResClass($aResClass) {
		$theResClassFile = $aResClass.'.php';
		if (file_exists($this->resDefaultPathRegion.$theResClassFile) && include_once($this->resDefaultPathRegion.$theResClassFile))
			return BITS_NAMESPACE_RES.self::DEFAULT_LANG.'\\'.self::DEFAULT_REGION.'\\'.$aResClass;
		elseif (file_exists($this->resDefaultPathLang.$theResClassFile) && include_once($this->resDefaultPathLang.$theResClassFile))
			return BITS_NAMESPACE_RES.self::DEFAULT_LANG.'\\'.$aResClass;
		elseif (file_exists($this->resPathBase.$theResClassFile) && include_once($this->resPathBase.$theResClassFile))
			return BITS_NAMESPACE_RES.$aResClass;
		elseif (include_once($this->resPathBase.'Resources'))
			return BITS_NAMESPACE_RES.'Resources';
	}
	
}//end class

}//end namespace
