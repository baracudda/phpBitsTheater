<?php
/*
 * Copyright (C) 2013 Blackmoon Info Tech Services
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
use com\blackmoonit\Strings;
{//begin namespace

/**
 * Pseudo-trait for Exceptions in < PHP 5.4
 */
class DebuggableExceptionTrait extends \stdClass implements IDebuggableException {
	private $mException;
	protected $mContextMsg = '';
	
	public function __construct($aException) {
		$this->mException = $aException;
	}
	
	public function setContextMsg($aMsg) {
    	$this->mContextMsg = $aMsg;
    }
	
	public function getContextMsg() {
		return $this->mContextMsg;
	}
	
    public function getErrorMsg() {
		return $this->mException->getMessage();
	}
	
	public function getDebugMsg() {
		if (is_callable(array($this->mException,'getDebugMsg'))) {
			return $this->mException->getDebugMsg();
		} else {
			return 'Error Code ('.$this->mException->getCode().')';
		}
	}
	
	public function getDebugDisplay($aMsg=null) {
		$s = "<br/>\n".'<div id="container-error">';
		$this->mContextMsg .= $aMsg;
		$s .= '<span class="msg-context">'.str_replace("\n","<br/>\n",$this->getContextMsg())."</span><br/>\n";
		$s .= '<span class="msg-error">'.str_replace("\n","<br/>\n",$this->getErrorMsg())."</span><br/>\n";
		$s .= '<span class="msg-debug">'.str_replace("\n","<br/>\n",$this->getDebugMsg())."</span><br/>\n";
		$s .= '<span class="msg-trace">Stack trace:<br/>'."\n".str_replace("\n","<br/>\n",
				$this->mException->getTraceAsString())."</span><br/>\n";
		$s .= '</div>'."\n";
		return $s;
	}
	
	public function debugPrint($aMsg=null) {
		if ((defined('_DEBUG_APP') && constant('_DEBUG_APP')) || !class_exists('config\\Settings')) {
			print($this->getDebugDisplay($aMsg));
		} else {
			Strings::debugLog($this->getDebugMsg().': '.$this->getErrorMsg());
		}
	}

}//end class

}//namespace
