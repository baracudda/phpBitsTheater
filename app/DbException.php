<?php
namespace app;
use \PDOException;
{//namespace begin

class DbException extends PDOException {
	public $contextMsg;
	
	public function __construct(PDOException $e, $aMsg = NULL) {
		$theMsg = '';
		$theCode = 0;
		$thePrior = null;
		if (isset($e)) {
			$theMsg = $e->getMessage();
			$theCode = $e->getCode();
			$thePrior = $e->getPrevious();
		}
		if (strstr($theMsg,'SQLSTATE[')) {
			preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/',$theMsg,$matches);
			$theCode = ($matches[1] == 'HT000' ? $matches[2] : $matches[1]);
			$theMsg = $matches[3];
        }
		parent::__construct($theMsg,$theCode,$thePrior);
		$this->setContextMsg($aMsg);
	}
    
    public function setContextMsg($aMsg) {
    	$this->contextMsg = $aMsg;
    	return $this; //for chaining purposes
    }
	
	public function getErrorMsg() {
		return $this->getMessage();
	}
	
	public function getDebugMsg() {
		return 'SQLerr('.$this->getCode().')';
	}
	
	public function getDebugDisplay($aMsg=null) {
		$s = "<br/>\n".'<div style="background-color:black">';
		$this->contextMsg .= $aMsg;
		$s .= '<font color="red">'.str_replace("\n","<br/>\n",$this->contextMsg)."</font><br/>\n";
		$s .= '<font color="yellow">'.str_replace("\n","<br/>\n",$this->getErrorMsg())."</font><br/>\n";
		$s .= '<font color="aqua">'.str_replace("\n","<br/>\n",$this->getDebugMsg())."</font><br/>\n";
		$s .= '<font color="lime">Stack trace:<br/>'."\n".str_replace("\n","<br/>\n",$this->getTraceAsString())."</font><br/>\n";
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
