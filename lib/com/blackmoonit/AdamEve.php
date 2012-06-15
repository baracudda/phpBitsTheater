<?php
namespace com\blackmoonit;
{//begin namespace

/*
 * Root class most others will derive from to provide core object funcitonality.
 */
class AdamEve extends \stdClass {
	protected $bHasBeenSetup = false;
	protected $bHasBeenCleanup = false;
	protected $_setupArgCount = -1; //number of args required to call the setup() method.
	public $myClassName;
	public $name;

	/*
	 * Constructor that will call __construct%numargs%(...) if any are passed in
	 */
	public function __construct() {
		$this->myClassName = get_class($this);
		$this->name = basename(str_replace('\\',DIRECTORY_SEPARATOR,$this->myClassName));
		$theArgs = func_get_args();
		$numArgs = func_num_args();
		if ($numArgs==$this->_setupArgCount) {
			call_user_func_array(array($this,'setup'),$theArgs);
		} else if (method_exists($this,$theMethod='__construct'.$numArgs)) {
			call_user_func_array(array($this,$theMethod),$theArgs);
		}
	}
	
	/* example constructor of 1 argument
    function __construct1($arg1) {}
    */
    
	/* example constructor of 3 arguments
    function __construct3($arg1, $arg2, $arg3) {}
    */
    
    public function setup() {
		$this->bHasBeenSetup = true;
		unset($this->_setupArgCount);
    }

	public function cleanup() {
		$this->bHasBeenCleanup = true;
	}

	public function __destruct() {
		if (!$this->bHasBeenCleanup)
			$this->cleanup();
	}
	
	public function isDebugging() {
		return defined('_DEBUG_APP') && constant('_DEBUG_APP');
	}	

	public function get_class_constants($aFilter) {
    	$reflect = new \ReflectionClass(get_calling_class());
	    $arr = $reflect->getConstants();
	    $theResult = array();
	    foreach ($arr as $key => $value) {
	    	if (preg_match($aFilter,$key)>0) {
	    		$theResult[$key] = $value;
	    	}
	    }
	    return $theResult;
	}
	
	public function debugPrint($s) {
		if ($this->isDebugging()) print $s;
	}
	
	public function isCallable($aMethodName) {
		return is_callable(array($this,$aMethodName));
	}
	
}//end class

}//end namespace
