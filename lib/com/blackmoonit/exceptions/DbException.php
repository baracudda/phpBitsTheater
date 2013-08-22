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
use \PDOException;
{//begin namespace


/**
 * A DB exception occured, alias for PDOException (if using a different DB abstraction layer, 
 * descend from that DB layer's exception class instead.
 */
class DbException extends PDOException implements IDebuggableException {
	private $mDebuggableExceptionTrait;
		
	public function __construct(PDOException $e, $aMsg='') {
		$this->mDebuggableExceptionTrait = new DebuggableExceptionTrait($this);
		if (isset($e)) {
			$theArgs = self::parseSqlStateMsg($e->getMessage());
			if ($e->getPrevious()) {
				if (count($theArgs)<2) {
					$theArgs[] = 0;
				}
				$theArgs[] = $e->getPrevious();
			}
			call_user_func_array(array('parent','__construct'),$theArgs);
			$this->setContextMsg($aMsg);
		} else {
			call_user_func_array(array('parent','__construct'),array($aMsg));
		}
	}

	static public function parseSqlStateMsg($aMsg) {
		$theResult = array();
		if (strstr($aMsg,'SQLSTATE[') && preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/',$aMsg,$matches)) {
			$theResult[] = $matches[3];
			$theResult[] = ( ($matches[1]=='HT000' || $matches[1]=='HY000') ? $matches[2] : $matches[1] );
		} else {
			$theResult[] = $aMsg;
		}
		return $theResult;
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
	
	public function getDebugMsg() {
		return 'SQL ErrCode ('.$this->getCode().')';
	}
	
    public function getDebugDisplay($aMsg=null) {
    	return $this->mDebuggableExceptionTrait->getDebugDisplay($aMsg);
	}
	
	public function debugPrint($aMsg=null) {
		return $this->mDebuggableExceptionTrait->debugPrint($aMsg);
	}	

}//end class

}//end namespace
