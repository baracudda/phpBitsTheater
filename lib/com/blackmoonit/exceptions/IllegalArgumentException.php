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

namespace com\blackmoonit\exceptions;
use com\blackmoonit\exceptions\DebuggableExceptionTrait;
use com\blackmoonit\exceptions\IDebuggableException;
use \InvalidArgumentException;
{//begin namespace

/**
 * Alias for InvalidArgumentException
 */
class IllegalArgumentException extends InvalidArgumentException implements IDebuggableException {
	private $mDebuggableExceptionTrait;
	
	public function __construct() {
		call_user_func_array('parent::__construct',func_get_args());
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
	
	public function getDebugDisplay($aMsg=null) {
		return $this->mDebuggableExceptionTrait->getDebugDisplay($aMsg);
	}
	
    public function setDebugCheck($aDebugCheck) {
    	return $this->mDebuggableExceptionTrait->setDebugCheck($aDebugCheck);
	}
	
    public function setCssFileUrl($aCssFileUrl) {
    	return $this->mDebuggableExceptionTrait->setCssFileUrl($aCssFileUrl);
	}
	
    public function setFileRoot($aFileRoot) {
    	return $this->mDebuggableExceptionTrait->setFileRoot($aFileRoot);
	}
	
	public function debugPrint($aMsg=null) {
		return $this->mDebuggableExceptionTrait->debugPrint($aMsg);
	}
	
}//end class

}//end namespace
