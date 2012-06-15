<?php
namespace app;
use app\config\I18N;
{//namespace begin

class ResException extends \Exception {
	public $contextMsg = '';
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
	}
	
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
	
	public function getDebugMsg($bIncludePathInfo = TRUE) {
		if (empty($this->resArgs) && empty($this->resErr)) {
			$msg = $this->getCode().': '.$this->resName." not found in any of the paths";
			if ($bIncludePathInfo) {
				$msg .= ":\n";
				$msg .= BITS_RES_PATH."\n";
				$msg .= I18N::PATH_LANG."\n";
				$msg .= I18N::PATH_REGION."\n";
				if (I18N::LANG!=I18N::DEFAULT_LANG)
					$msg .= I18N::DEFAULT_PATH_LANG."\n";
				if (I18N::LANG!=I18N::DEFAULT_LANG || I18N::REGION!=I18N::DEFAULT_REGION)
					$msg .= I18N::DEFAULT_PATH_REGION."\n";
			}
		} else {
			$msg = $this->getCode().': '.$this->resClass.'.'.$this->resName.'('.implode('/',$this->resArgs).')"'."\n";
			$msg .= 'caused: '.$this->resErr->getMessage()."\n";
			$msg .= $this->resErr->getDebugInfo()."\n";
		}
		return $msg;
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
			Strings::debugLog($this->getErrorMsg());
		}
	}
	
}//end class

}//end namespace
