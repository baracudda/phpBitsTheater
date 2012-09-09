<?php
namespace com\blackmoonit\bits_theater\app;
{//begin namespace
	
/**
 * Pseudo-trait for Exceptions in < PHP 5.4
 */
class DebuggableExceptionTrait extends \stdClass {
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

/**
 * Alias for InvalidArgumentException
 */
class IllegalArgumentException extends \InvalidArgumentException {
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
	
	public function debugPrint($aMsg=null) {
		return $this->mDebuggableExceptionTrait->debugPrint($aMsg);
	}
}//end class

/**
 * 404 error occured, pop us back out to the index.php code for what to load
 */
class FourOhFourExit extends \Exception { 
	private $mDebuggableExceptionTrait;
	public $url = '';
	
	function __construct($aUrl, $aCode=404, $aPreviousException=NULL) {
		parent::__construct("Page Not Found: ".$aUrl, $aCode, $aPreviousException);
		$this->url = $aUrl;
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
	
	public function debugPrint($aMsg=null) {
		return $this->mDebuggableExceptionTrait->debugPrint($aMsg);
	}
}//end class

/**
 * Usually just a clean exit, but with the ability to log a message if desired.
 */
class SystemExit extends \Exception {
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
	
	public function debugPrint($aMsg=null) {
		return $this->mDebuggableExceptionTrait->debugPrint($aMsg);
	}
}//end class

/**
 * A DB exception occured, alias for PDOException (if using a different DB abstraction layer, 
 * descend from that DB layer's exception class instead.
 */
class DbException extends \PDOException {
	private $mDebuggableExceptionTrait;
		
	public function __construct(\PDOException $e, $aMsg='') {
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
        $this->mDebuggableExceptionTrait = new DebuggableExceptionTrait($this);
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

/**
 * Resource load exception.
 */
class ResException extends \Exception {
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
				$msg .= config\I18N::PATH_LANG."\n";
				$msg .= config\I18N::PATH_REGION."\n";
				if (config\I18N::LANG != config\I18N::DEFAULT_LANG)
					$msg .= config\I18N::DEFAULT_PATH_LANG."\n";
				if ((config\I18N::LANG != config\I18N::DEFAULT_LANG) || (config\I18N::REGION != config\I18N::DEFAULT_REGION))
					$msg .= config\I18N::DEFAULT_PATH_REGION."\n";
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