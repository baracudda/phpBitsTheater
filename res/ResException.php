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
use com\blackmoonit\exceptions\DebuggableExceptionTrait;
use com\blackmoonit\exceptions\IDebuggableException;
use \Exception;
use com\istresearch\argos\app\config\I18N;
{//begin namespace

/**
 * Resource load exception.
 */
class ResException extends Exception implements IDebuggableException {
	private $mDebuggableExceptionTrait;
	public $resName;
	public $resClass;
	public $resArgs;
	public $resErr;
	
	public function __construct($aResName, $aResClass=NULL, $args=NULL, $e=NULL) {
		if (empty($aResClass) || empty($args) || empty($e))
			parent::__construct('Resource "'.$aResName.'" not found.',18404);
		else
			parent::__construct('Resource "'.$aResClass.'.'.$aResName.'('.implode('/',$args).')" caused: '.$e->getMessage(),18500);
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
				$msg .= BITS_RES_PATH."\n";
				$msg .= I18N::PATH_LANG."\n";
				$msg .= I18N::PATH_REGION."\n";
				if (I18N::LANG != I18N::DEFAULT_LANG)
					$msg .= I18N::DEFAULT_PATH_LANG."\n";
				if ((I18N::LANG != I18N::DEFAULT_LANG) || (I18N::REGION != I18N::DEFAULT_REGION))
					$msg .= I18N::DEFAULT_PATH_REGION."\n";
			}
		} else {
			$msg = $this->getCode().': '.$this->resClass.'.'.$this->resName.'('.implode('/',$this->resArgs).')"'."\n";
			$msg .= 'caused: '.$this->resErr->getMessage()."\n";
			$msg .= $this->resErr->getDebugMsg()."\n";
		}
		return $msg;
	}
	
	public function getDebugDisplay($aMsg=null) {
		return $this->mDebuggableExceptionTrait->getDebugDisplay($aMsg);
	}
	
	public function debugPrint($aMsg=null) {
		return $this->mDebuggableExceptionTrait->debugPrint($aMsg);
	}	
	
}//end class

}//end namespace
