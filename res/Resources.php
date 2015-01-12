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
use com\blackmoonit\AdamEve as BaseResources;
use BitsTheater\Director;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\IllegalArgumentException;
use BitsTheater\costumes\EnumResEntry;
use BitsTheater\costumes\ConfigResEntry;
{//begin namespace

class Resources extends BaseResources {
	const _SetupArgCount = 1; //number of args required to call the setup() method.
	/**
	 * @var Director
	 */
	protected $_director = null;
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 */
	public function setup(Director $aDirector) {
		$this->_director = $aDirector;
		$this->bHasBeenSetup = true;
	}

	/**
	 * Descendants want to merge translated labels with their static definitions in ancestor.
	 * @param array $res1 - an array resource variable to merge data into (array will be modified).
	 * @param array $res2 - an array or a class with an array inteface used to merge data into res1.
	 */
	public function res_array_merge(array &$res1, $res2) {
		if (!is_array($res1) || empty($res2)) {
			throw new IllegalArgumentException('res_array_merge requires first param to be an array and second != null. '.
					Strings::debugStr($res1));
		}
		if (!is_array($res2)) {
			$res1[] = $res2;
		} else {
			foreach ($res2 as $key => &$value) {
				if (is_string($key)) {
					if (is_array($value) && array_key_exists($key, $res1) && is_array($res1[$key])) {
						$this->res_array_merge($res1[$key],$value);
					} else {
						$res1[$key] = $value;
					}
				} else {
					$res1[] = $value;
				}
			}
		}
	}
	
	/**
	 * Shortcut to calling the Director's getRes method.
	 * @param string $aResName - the resource in the form 'resClass/resName'.
	 * @return mixed Returns either a string or an array, depending on the resource retrieved.
	 */
	public function getRes($aResName) {
		return $this->_director->getRes($aResName);
	}
	
	/**
	 * Images need to be lang/region/default checked as well as extension agnostic.
	 * Return a list of base paths to check for image files.
	 * @param $aI18NObj - the object used by the Director to load resources.
	 * @return array Returns an array of URL=>Paths to check in order of precidence.
	 */
	protected function getBasePathList($aI18NObj) {
		$theList = array();
		if (!empty($aI18NObj)) {
			//start looking in user lang/region: res/i18n/es/MX
			$theKey = str_replace(BITS_RES_PATH, BITS_RES.'/', $aI18NObj->resPathRegion);
			$theKey = str_replace(DIRECTORY_SEPARATOR,'/',$theKey).'images/';
			$theList[$theKey] = $aI18NObj->resPathRegion.'images'.DIRECTORY_SEPARATOR;
			//next look in user lang: res/i18n/es
			$theKey = str_replace(BITS_RES_PATH, BITS_RES.'/', $aI18NObj->resPathLang);
			$theKey = str_replace(DIRECTORY_SEPARATOR,'/',$theKey).'images/';
			$theList[$theKey] = $aI18NObj->resPathLang.'images'.DIRECTORY_SEPARATOR;
			//try looking in default lang/region: res/i18n/en/US
			$theKey = str_replace(BITS_RES_PATH, BITS_RES.'/', $aI18NObj->resDefaultPathRegion);
			$theKey = str_replace(DIRECTORY_SEPARATOR,'/',$theKey).'images/';
			$theList[$theKey] = $aI18NObj->resDefaultPathRegion.'images'.DIRECTORY_SEPARATOR;
			//next look in default lang: res/i18n/en
			$theKey = str_replace(BITS_RES_PATH, BITS_RES.'/', $aI18NObj->resDefaultPathLang);
			$theKey = str_replace(DIRECTORY_SEPARATOR,'/',$theKey).'images/';
			$theList[$theKey] = $aI18NObj->resDefaultPathLang.'images'.DIRECTORY_SEPARATOR;
		}
		//if none of the above work, try default image folder
		$theList[BITS_RES.'/images/'] = BITS_RES_PATH.'images'.DIRECTORY_SEPARATOR;
		return $theList;
	}

	/**
	 * Check to see if image exists for the given path and return url if exists.
	 * @param string $aBaseUrl - base url to use if exists.
	 * @param string $aBasePath - base path to check for image.
	 * @param array $aRelativePathList - required, may be empty, each entry is a deeper subfolder.
	 * @param string $aFilename - either a full filename or, if missing an extension, match first likely image file.
	 * @return string Returns a url to be used as an "img" tag's "src" attribute.
	 */
	protected function checkImgResSrcPath($aBaseUrl, $aBasePath, array $aRelativePathList, $aFilename) {
		//construct the base URL
		$theImgSrc = $aBaseUrl;
		foreach ($aRelativePathList as $thePathSegment) {
			$theImgSrc .= $thePathSegment.'/';
		}
		
		//determine the image file wanted
		$theFilename = null;
		$theImagePath = $aBasePath;
		foreach ($aRelativePathList as $thePathSegment) {
			$theImagePath .= $thePathSegment.DIRECTORY_SEPARATOR;
		}
		if (strpos($aFilename,'.')===false) {
			//no extension given, match it to one in the path given
			$theImagePattern = $theImagePath.$aFilename.'.*';
			foreach (glob($theImagePattern) as $theMatchFilename) {
				//just grab first match and break out
				$theFilename = basename($theMatchFilename);
				break;
			}
		} else if (file_exists($aBasePath.$aFilename)) {
			$theFilename = $aFilename;
		}
		
		//if found, return the URL else NULL
		if (!empty($theFilename))
			return $theImgSrc . $theFilename;
		else
			return null;
	}
	
	/**
	 * The parameters determine the relative path to an image file. Several base paths
	 * will be checked based on getBasePathList() results.
	 * @param array $aRelativePathList - required, may be empty, each entry is a deeper subfolder.
	 * @param string $aFilename - either a full filename or, if missing an extension, match first likely image file.
	 * @return string Returns a string suitable to be used as an "img" tag "src" attribute.
	 */
	protected function getImgResSrc(array $aRelativePathList, $aFilename) {
		foreach($this->getBasePathList($this->_director->getResManager()) as $theUrl => $thePath) {
			$theImgSrc = $this->checkImgResSrcPath($theUrl, $thePath, $aRelativePathList, $aFilename);
			if (!empty($theImgSrc))
				return $theImgSrc;
		}
		//if nothing was found, return NULL.
		return null;
	}
	
	/**
	 * The parameters determine the relative path to a BITS_RES.'/images/' image file.
	 * @return string Returns a string suitable to be used as an "img" tag "src" attribute.
	 */
	public function imgsrc() {
		$theImagePathList = func_get_args();
		if (!empty($theImagePathList)) {
			//since this operation may be expensive, check session cache first
			$theSessionVarName = 'imgsrc_cache/'.get_called_class().implode('/',$theImagePathList);
			if (!empty($this->_director[$theSessionVarName])) {
				return $this->_director[$theSessionVarName];
			} else {
				$theFilename = array_pop($theImagePathList);
				$theResult = $this->getImgResSrc($theImagePathList,$theFilename);
				//cache this result
				$this->_director[$theSessionVarName] = $theResult;
				return $theResult;
			}
		}
	}
	
	/**
	 * Build the enum info from separate parts. Allows us to define the enum
	 * values separate from UI presentation / language concerns.
	 * @param string $aEnumName - name of variable resulting from combined "enum_*",
	 * "label_*", and "desc_*" parts.
	 */
	public function mergeEnumEntryInfo($aEnumName) {
		$theProp = $aEnumName;
		$this->{$theProp} = array();
		$eprop = 'enum_'.$theProp;
		$lprop = 'label_'.$theProp;
		$dprop = 'desc_'.$theProp;
		if (isset($this->{$eprop})) {
			$this->{$theProp} = array();
			foreach ($this->{$eprop} as $theEnumValue) {
				$res = new EnumResEntry($theEnumValue);
				
				if (isset($this->{$lprop}[$theEnumValue]))
					$res->label = $this->{$lprop}[$theEnumValue];
	
				if (isset($this->{$dprop}[$theEnumValue]))
					$res->desc = $this->{$dprop}[$theEnumValue];
	
				$this->{$theProp}[$theEnumValue] = $res;
			}//foreach
			//no need to keep the un-merged data around, unset it to recover its memory
			unset($this->{$eprop});
			if (isset($this->{$lprop}))
				unset($this->{$lprop});
			if (isset($this->{$dprop}))
				unset($this->{$dprop});
		}//if
		//$this->debugLog(__METHOD__.'('.$theProp.'): '.$this->debugStr($this->{$theProp}));
	}
	
	/**
	 * Build the config enum info from separate parts. Allows us to define the config
	 * values separate from UI presentation / language concerns.
	 * @param string $aConfigEnumName - name of variable resulting from combined "enum_*",
	 * "label_*", "desc_*", and input parts.
	 */
	public function mergeConfigEntryInfo($aConfigEnumName) {
		$theProp = $aConfigEnumName;
		$this->{$theProp} = array();
		$eprop = 'enum_'.$theProp;
		$lprop = 'label_'.$theProp;
		$dprop = 'desc_'.$theProp;
		$iprop = 'input_'.$theProp;
		if (isset($this->{$eprop})) {
			$this->{$theProp} = array();
			foreach ($this->{$eprop} as $theEnumValue) {
				$res = new ConfigResEntry($theEnumValue);
				
				if (isset($this->{$lprop}[$theEnumValue]))
					$res->label = $this->{$lprop}[$theEnumValue];
	
				if (isset($this->{$dprop}[$theEnumValue]))
					$res->desc = $this->{$dprop}[$theEnumValue];
	
				if (isset($this->{$iprop}[$theEnumValue]))
					$res->setInput($this->{$iprop}[$theEnumValue]);
				
				$this->{$theProp}[$theEnumValue] = $res;
			}//foreach
			//no need to keep the un-merged data around, unset it to recover its memory
			unset($this->{$eprop});
			if (isset($this->{$lprop}))
				unset($this->{$lprop});
			if (isset($this->{$dprop}))
				unset($this->{$dprop});
			if (isset($this->{$iprop}))
				unset($this->{$iprop});
		}//if
		//$this->debugLog(__METHOD__.'('.$theProp.'): '.$this->debugStr($this->{$theProp}));
	}
	
}//end class

}//end namespace
