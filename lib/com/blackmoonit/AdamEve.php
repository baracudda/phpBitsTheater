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
use \stdClass as BaseClass;
use \ReflectionClass;
{//begin namespace

/**
 * Root class most others will derive from to provide core object funcitonality.
 */
class AdamEve extends BaseClass {
	const _SetupArgCount = 0; //number of args required to call the setup() method.
	protected $bHasBeenSetup = false;
	protected $bHasBeenCleanup = false;
	public $myClassName;
	public $mySimpleClassName;

	/**
	 * Constructor that will call __construct%numargs%(...) if any are passed in
	 */
	public function __construct() {
		$this->myClassName = get_class($this);
		$this->mySimpleClassName = basename(str_replace('\\',DIRECTORY_SEPARATOR,$this->myClassName));
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
	
	public function isDebugging() {
		return defined('_DEBUG_APP') && constant('_DEBUG_APP');
	}	

	public function get_class_constants($aFilter) {
    	$reflect = new ReflectionClass(get_calling_class());
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
