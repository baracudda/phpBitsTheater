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
use com\blackmoonit\exceptions\DebuggableExceptionTrait;
use com\blackmoonit\exceptions\IDebuggableException;
use \Exception;
{//begin namespace

/**
 * Resource load exception.
 */
class ResException extends Exception implements IDebuggableException {
	private $mDebuggableExceptionTrait;
	public $resMgr;
	public $resName;
	public $resClass;
	public $resArgs;
	/**
	 * @var ResException
	 */
	public $resErr;
	
	public function __construct($aResMgr, $aResName, $aResClass=NULL, $args=NULL, $e=NULL) {
		if (empty($aResClass) || empty($args) || empty($e))
			parent::__construct('Resource "'.$aResName.'" not found.',18404);
		else
			parent::__construct('Resource "'.$aResClass.'.'.$aResName.'('.implode('/',$args).')" caused: '.$e->getMessage(),18500);
		$this->resMgr = $aResMgr;
		$this->resName = $aResName;
		$this->resClass = $aResClass;
		$this->resArgs = $args;
		$this->resErr = $e;
		$this->mDebuggableExceptionTrait = new DebuggableExceptionTrait($this);
	}
	
	public function setContextMsg($aMsg) {
		$this->mDebuggableExceptionTrait->setContextMsg($aMsg);
		return $this; //support chaining
	}
	
	public function getContextMsg() {
		return $this->mDebuggableExceptionTrait->getContextMsg();
	}
	
	public function getErrorMsg() {
		return $this->mDebuggableExceptionTrait->getErrorMsg();
	}
	
	public function getDebugMsg($bIncludePathInfo = TRUE) {
		if (empty($this->resArgs) && empty($this->resErr)) {
			$msg = $this->getCode().': '.$this->resName." not found in any of the paths";
			if ($bIncludePathInfo) {
				$msg .= ":\n";
				$msg .= $this->resMgr->resPathBase."\n";
				$msg .= $this->resMgr->resPathLang."\n";
				$msg .= $this->resMgr->resPathRegion."\n";
				if (!$this->resMgr->isUsingDefault()) {
					$msg .= $this->resMgr->resDefaultPathLang."\n";
					$msg .= $this->resMgr->resDefaultPathRegion."\n";
				}
				$theFileRoot = $this->mDebuggableExceptionTrait->getFileRoot();
				if ($theFileRoot)
					$msg = str_replace($theFileRoot,'[%site]',$msg);
			}
		} else {
			$msg = $this->getCode().': '.$this->resClass.'.'.$this->resName.'('.implode('/',$this->resArgs).')"'."\n";
			if (!empty($this->resErr)) {
				$msg .= 'caused: '.$this->resErr->getMessage()."\n";
				$msg .= $this->resErr->getDebugDisplay()."\n";
			}
		}
		return $msg;
	}
	
	public function getDebugDisplay($aMsg=null) {
		return $this->mDebuggableExceptionTrait->getDebugDisplay($aMsg);
	}
	
	public function getDebugCheck() {
		return $this->mDebuggableExceptionTrait->getDebugCheck();
	}
	
	public function setDebugCheck($aDebugCheck) {
		return $this->mDebuggableExceptionTrait->setDebugCheck($aDebugCheck);
	}
	
	public function getCssFileUrl() {
		return $this->mDebuggableExceptionTrait->getCssFileUrl();
	}
	
	public function setCssFileUrl($aCssFileUrl) {
		return $this->mDebuggableExceptionTrait->setCssFileUrl($aCssFileUrl);
	}
	
	public function getFileRoot() {
		return $this->mDebuggableExceptionTrait->getFileRoot();
	}
	
	public function setFileRoot($aFileRoot) {
		return $this->mDebuggableExceptionTrait->setFileRoot($aFileRoot);
	}
	
	public function debugPrint($aMsg=null) {
		return $this->mDebuggableExceptionTrait->debugPrint($aMsg);
	}
	
	public function getException()
	{ return $this->mDebuggableExceptionTrait->getException(); }
	
}//end class

}//end namespace
