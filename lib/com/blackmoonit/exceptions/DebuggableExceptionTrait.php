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
	protected $mDebugCheck = '_DEBUG_APP';
	protected $mCssFileUrl = null;
	protected $mFileRoot = null;
	
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
		$theTrace = str_replace("\n","<br/>\n",$this->mException->getTraceAsString());
		if ($this->mFileRoot)
			$theTrace = str_replace($this->mFileRoot,'[%site]',$theTrace);
		$s .= '<span class="msg-trace">Stack trace:<br/>'."\n".$theTrace."</span><br/>\n";
		$s .= '</div>'."\n";
		return $s;
	}
	
	/**
	 * Pass in a CONST name to check or a function/method name to check for "is debugging".
	 * Default is to check the _DEBUG_APP const.
	 * @param string $aDebugCheck - CONST or function/method to check that returns TRUE or FALSE.
	 * @return Returns $this to support chaining.
	 */
	public function setDebugCheck($aDebugCheck) {
		if (!empty($aDebugCheck))
			$this->mDebugCheck = $aDebugCheck;
		return $this;
	}
	
	/**
	 * If headers have not yet been sent and debug output must occur, this CSS file will be loaded.
	 * @param string $aCssFileUrl - Url for CSS to print pretty errors while debugging.
	 * @return Returns $this to support chaining.
	 */
	public function setCssFileUrl($aCssFileUrl) {
		if (!empty($aCssFileUrl))
			$this->mCssFileUrl = $aCssFileUrl;
		return $this;
	}
	
	/**
	 * If defined, trace output will str_replace the param with "[%site]".
	 * @param string $aFileRoot - This path will be replaced with "[%site]" in debug output.
	 * @return Returns $this to support chaining.
	 */
	public function setFileRoot($aFileRoot) {
		if (!empty($aFileRoot))
			$this->mFileRoot = $aFileRoot;
		return $this;
	}
	
	protected function isDebugging() {
		return (is_string($this->mDebugCheck) && defined($this->mDebugCheck) && constant($this->mDebugCheck)) || 
				(is_callable($this->mDebugCheck) && call_user_func($this->mDebugCheck));
	}

	public function debugPrint($aMsg=null) {
		if ($this->isDebugging()) {
			if (!headers_sent()) {
				print('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n");
				print('<html xmlns="http://www.w3.org/1999/xhtml">'."\n");
				print('<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n");
				if (!empty($this->mCssFileUrl))
					print('<link rel="stylesheet" type="text/css" href="'.$this->mCssFileUrl.'">'."\n");
			}
			print($this->getDebugDisplay($aMsg));
		} else {
			Strings::debugLog($this->getDebugMsg().': '.$this->getErrorMsg());
		}
	}

}//end class

}//namespace
