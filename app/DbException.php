<?php
namespace app;
use \PDOException;
{//namespace begin

class DbException extends PDOException {
	public $contextMsg;
	
	public function __construct(PDOException $e, $aMsg='') {
		if (isset($e)) {
			$theArgs = $this::parseSqlStateMsg($e->getMessage());
			if ($e->getPrevious()) {
				$theArgs[] = $e->getPrevious();
			}
			call_user_func_array(array('parent','__construct'),$theArgs);
			$this->setContextMsg($aMsg);
		} else {
			call_user_func_array(array('parent','__construct'),array($aMsg));
		}
	}
    
    public function setContextMsg($aMsg) {
    	$this->contextMsg = $aMsg;
    	return $this; //for chaining purposes
    }
    
    static public function parseSqlStateMsg($aMsg) {
		$theResult = array();
    	if (strstr($aMsg,'SQLSTATE[') && preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/',$aMsg,$matches)) {
			$theResult[] = $matches[3];
    		$theResult[] = ($matches[1] == 'HT000' ? $matches[2] : $matches[1]);
        } else {
        	$theResult[] = $aMsg;
        }
        return $theResult;
    }
	
	public function getContextMsg() {
		return $this->contextMsg;
	}
	
    public function getErrorMsg() {
		return $this->getMessage();
	}
	
	public function getDebugMsg() {
		return 'SQLerr('.$this->getCode().')';
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
