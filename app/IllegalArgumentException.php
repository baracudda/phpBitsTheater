<?php
namespace app;
{//begin namespace
	
/**
 * Alias for InvalidArgumentException
 */
class IllegalArgumentException extends \InvalidArgumentException {
	public $contextMsg = '';
	
    public function setContextMsg($aMsg) {
    	$this->contextMsg = $aMsg;
    	return $this; //for chaining purposes
    }
	
	public function getContextMsg() {
		return $this->contextMsg;
	}
	
    public function getErrorMsg() {
		return $this->getMessage();
	}
	
	public function getDebugMsg() {
		return 'Error Code ('.$this->getCode().')';
	}
	
    public function getDebugDisplay($aMsg=null) {
		$s = "<br/>\n".'<div id="container-error">';
		$this->contextMsg .= $aMsg;
		$s .= '<span class="msg-context">'.str_replace("\n","<br/>\n",$this->getContextMsg())."</span><br/>\n";
		$s .= '<span class="msg-error">'.str_replace("\n","<br/>\n",$this->getErrorMsg())."</span><br/>\n";
		$s .= '<span class="msg-debug">'.str_replace("\n","<br/>\n",$this->getDebugMsg())."</span><br/>\n";
		$s .= '<span class="msg-trace">Stack trace:<br/>'."\n".str_replace("\n","<br/>\n",$this->getTraceAsString())."</span><br/>\n";
		$s .= '</div>'."\n";
		return $s;
	}
	
	public function debugPrint($aMsg=null) {
		if ((defined('_DEBUG_APP') && constant('_DEBUG_APP')) || !class_exists('app\\config\\Settings')) {
			print($this->getDebugDisplay($aMsg));
		} else {
			Strings::debugLog($this->getDebugMsg().': '.$this->getErrorMsg());
		}
	}
	
	
}//end class
	
}//end namespace