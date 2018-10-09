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

namespace com\blackmoonit;
use stdClass as BaseClass;
use ReflectionClass;
{//begin namespace

/**
 * Root class most others will derive from to provide core object funcitonality.
 * Constructor that will call __construct%numargs%(...) if any are passed in.
 * If the number of args matches _SetupArgCount, then $this->setup(%args%) is called instead.
 */
class AdamEve extends BaseClass {
	const _SetupArgCount = 0; //number of args required to call the setup() method.
	protected $bHasBeenSetup = false;
	protected $bHasBeenCleanup = false;
	public $myClassName;
	public $mySimpleClassName;
	public $myNamespaceName;
	/**
	 * Debugging out of memory errors are very difficult to trace.
	 * Use of this static var during onShutdown handler to help track down last class loaded.
	 */
	static public $lastClassLoaded1 = null;
	static public $lastClassLoaded2 = null;
	static public $lastClassLoaded3 = null;
	
	/**
	 * Constructor that will call __construct%numargs%(...) if any are passed in.
	 * If the number of args matches _SetupArgCount, then $this->setup(%args%) is called instead.
	 */
	public function __construct() {
		//$this->myClassName = get_class($this);
		//$this->mySimpleClassName = basename(str_replace('\\',DIRECTORY_SEPARATOR,$this->myClassName));
		$rc = new ReflectionClass($this);
		$this->myClassName = $rc->getName();
		$this->mySimpleClassName = $rc->getShortName();
		$this->myNamespaceName = $rc->getNamespaceName();
		$rc = null;
		self::$lastClassLoaded3 = self::$lastClassLoaded2;
		self::$lastClassLoaded2 = self::$lastClassLoaded1;
		self::$lastClassLoaded1 = $this;
		$theArgs = func_get_args();
		$numArgs = func_num_args();
		if ($numArgs==static::_SetupArgCount) {
			call_user_func_array(array($this,'setup'),$theArgs);
		} else if (method_exists($this,$theMethod='setup'.$numArgs)) {
			call_user_func_array(array($this,$theMethod),$theArgs);
		} else {
			$this->bHasBeenSetup = true;
		}
	}
	
	/* example setup() of _SetupArgCount arguments
	 * function setup($agr1, $arg2, ... $argN) {}
	 */

	/* example setup() of 1 argument
	 * function setup1($arg1) {}
	 */
    
	/* example setup() of 3 arguments
	 * function setup3($arg1, $arg2, $arg3) {}
   */

	public function cleanup() {
		$this->bHasBeenCleanup = true;
	}

	public function __destruct() {
		if (!$this->bHasBeenCleanup)
			$this->cleanup();
	}
	
	/**
	 * Controls what info is displayed during a var_dump().
	 */
	public function __debugInfo() {
		$vars = get_object_vars($this);
		unset($vars['bHasBeenSetup']);
		unset($vars['bHasBeenCleanup']);
		unset($vars['myClassName']);
		unset($vars['mySimpleClassName']);
		unset($vars['myNamespaceName']);
		return $vars;
	}

	/**
	 * Checks _DEBUG_APP constant.
	 * @return boolean - Returns TRUE if _DEBUG_APP is defined and evals to TRUE.
	 */
	public function isDebugging() {
		return defined('_DEBUG_APP') && constant('_DEBUG_APP');
	}

	/**
	 * Returns an array of constant values keyed by their const name.
	 * @param string $aFilter - used to preg_match const names, if not empty().
	 * @return array - Returns an associative array of const values keyed on their const name.
	 */
	public function get_class_constants($aFilter=null) {
    	$reflect = new ReflectionClass(get_called_class());
	    $arr = $reflect->getConstants();
	    $theResult = array();
	    foreach ($arr as $key => $value) {
	    	if (empty($aFilter) || preg_match($aFilter,$key)>0) {
	    		$theResult[$key] = $value;
	    	}
	    }
	    return $theResult;
	}
	
	/**
	 * Static version of logStuff().
	 * @see AdamEve::logStuff()
	 */
	static protected function debugOutput( $_ )
	{
		$theLogLine = '';
		foreach (func_get_args() as $arg)
		{
			$theLogLine .= ( is_string($arg) ) ? $arg : Strings::debugStr($arg);
		}
		Strings::debugLog( $theLogLine );
	}
	
	/**
	 * If isDebugging, this function will print out $s, else it will
	 * log as [dbg] instead.
	 * @param string $s - string parameter to print out
	 * @see Strings::debugLog($s)
	 */
	public function debugPrint($s) {
		if ($this->isDebugging())
			print $s;
		else
			Strings::debugLog($s);
	}
	
	/**
	 * Send string out to the debug log (or std log as [dbg]).
	 * @param $s - parameter to print out (non-strings converted with debugStr()).
	 * @see Strings::debugLog()
	 * @see AdamEve::debugStr()
	 */
	public function debugLog($s) {
		Strings::debugLog($s);
	}
	
	/**
	 * Returns string of "deep debug output" for arrays, objects, etc.
	 * @param mixed $aVar - variable to output
	 * @param string $aNewLineReplacement - (optional) default is space ' '.
	 * @return string - Returns debug output string (may contain \n).
	 * @see Strings::debugStr($aVar)
	 */
	public function debugStr($aVar, $aNewLineReplacement=' ') {
		return Strings::debugStr($aVar, $aNewLineReplacement);
	}
	
	/**
	 * Send string out to the error log prepended with the defined error prefix.
	 * @param $s - parameter to print out (non-strings converted with debugStr()).
	 * @see Strings::errorLog($s)
	 * @see AdamEve::debugStr()
	 */
	public function errorLog($s) {
		Strings::errorLog($s);
	}
	
	/**
	 * Check to see if self has a callable method.
	 * @param string $aMethodName - method name to check
	 * @return boolean - Returns TRUE if method name is callable.
	 */
	public function isCallable($aMethodName) {
		return is_callable(array($this,$aMethodName));
	}

	/**
	 * Send string out to the debug log (or std log as [dbg]).
	 * Accepts any number of parameters and will convert all non-strings with json_encode().
	 */
	public function logAsJSON( $_ ) {
		$theLogLine = '';
		foreach (func_get_args() as $arg)
		{
			$theLogLine .= ( is_string($arg) ) ? $arg : json_encode($arg);
		}
		$this->debugLog( $theLogLine );
	}
	
	/**
	 * Send string out to the debug log (or std log as [dbg]).
	 * Accepts any number of parameters and will convert all non-strings with debugStr().
	 */
	public function logStuff( $_ ) {
		$theLogLine = '';
		foreach (func_get_args() as $arg)
		{
			$theLogLine .= ( is_string($arg) ) ? $arg : $this->debugStr($arg);
		}
		$this->debugLog( $theLogLine );
	}
	
	/**
	 * Send string out to the debug log (or std log as [dbg]).
	 * Accepts any number of parameters and will convert all non-strings with debugStr().
	 */
	public function logErrors( $_ ) {
		$theLogLine = '';
		foreach (func_get_args() as $arg)
		{
			$theLogLine .= ( is_string($arg) ) ? $arg : $this->debugStr($arg);
		}
		$this->errorLog( $theLogLine );
	}
	
	/**
	 * IDE helper function, Code-Complete will display defined functions even for decendants.
	 * TEMPLATE FOR YOUR OWN CLASSES/FILES, KIND OF USELESS AS IS HERE.
	 * /
	static public function asMe(AdamEve &$aObj) {
		return $aObj;
	}
	*/
		
}//end class

}//end namespace
