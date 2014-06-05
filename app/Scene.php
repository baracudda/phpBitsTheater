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

namespace BitsTheater;
use com\blackmoonit\AdamEve as BaseScene;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use \ReflectionClass;
use \ReflectionMethod;
use com\blackmoonit\exceptions\IllegalArgumentException;
{//begin namespace

/**
 * Manages dynamic vars, methods, and properties
 * 
 * // Example adding/using new var
 * $test = new Scene();
 * $test->newVar = 'hi!';
 * echo $test->newVar; //prints "hi!"
 *
 * // Example adding a new custom method
 * $test = new Scene();
 * $test->do_something = function ($thisScene, $aVar1, $aVar2) {echo "custom called with $var1 and $var2\n";};
 * $test->do_something('asdf', 'test'); //prints "custom called with asdf and test"
 *
 * // Properties are special vars with get/set methods (at least one)
 * $test = new Scene();
 * $test->defineProperty('myProp',null,function ($thisScene, $name, $value) { return $value+1; }, 5);
 * echo $test->myProp; //prints "5"
 * $test->myProp += 1;
 * echo $test->myProp; //prints "7" (5+1 is 6 which becomes $value in call to "set" function which actually sets myProp to 6+1) 
 */
class Scene extends BaseScene {
	const _SetupArgCount = 2; //number of args required to call the setup() method.
	public $me = null;
	public $_actor = null;
	public $_director = null;
	public $_config = null;
	public $_action = '';
	public $_dbError = false;
	protected $_methodList = array(); // list of all custom added methods
	protected $_properties = array(); // list of all added properties
	protected $_dbResNames = array(); // list of all dbResult var names
	const USER_MSG_NOTICE = 'notice';
	const USER_MSG_WARNING = 'warning';
	const USER_MSG_ERROR = 'error';
	
	public function __call($aName, $args) {
		//Strings::debugLog('call:'.$aName);
		if (isset($this->_methodList[$aName])) {
			array_unshift($args,$this);
			return call_user_func_array($this->_methodList[$aName],$args);
		}
	}
	
	public function __set($aName, $aValue) {
		//Strings::debugLog('set:'.$aName);
		if (is_callable($aValue) || array_key_exists($aName,$this->_methodList)) {
			$this->_methodList[$aName] = $aValue;
		} elseif (array_key_exists($aName,$this->_properties)) {
			$prop =& $this->_properties[$aName];
			if (isset($prop['on_set'])) { 
				$prop['value'] = $prop['on_set']($this,$aName,$aValue);
			} else {
				$prop['value'] = $aValue;
			}
		} else {
			$this->$aName = $aValue;
		}
	}

	public function __get($aName) {
		if (array_key_exists($aName,$this->_properties)) {
			$prop =& $this->_properties[$aName];
			$theValue = (array_key_exists('value',$prop))?$prop['value']:null;
			if (isset($prop['on_get'])) {
				return $prop['on_get']($this,$aName,$theValue);
			} else {
				return $theValue;
			}
		} elseif (array_key_exists($aName,$this->_methodList)) {
			return $this->_methodList[$aName];
		//} elseif (property_exists($this,$aName)) {  __get not called if property_exists in first place
		//	return $this->$aName;
		} else {
			return null; //value not set yet, no need to toss an exception unless debugging something special
			//throw new IllegalArgumentException("Property {$aName} does not exist.");
		}
	}
	
	public function __isset($aName) {
		return isset($this->$aName) || isset($this->_properties[$aName]) || isset($this->_methodList[$aName]);
	}
	
	public function __unset($aName) {
		//Strings::debugLog('_unset:'.$aName);
		if (isset($this->$aName)) {
			unset($this->$aName);
		} elseif (isset($this->_methodList[$aName])) {
			unset($this->_methodList[$aName]);
		} elseif (isset($this->_properties[$aName])) {
			unset($this->_properties[$aName]['value']);
		}
	}
	
	//get/set functions: ($this, $name, $value), returning what should be get/set.
	public function defineProperty($aName, $aGetMethod, $aSetMethod, $aDefaultValue=null) {
		if ((isset($aGetMethod) && !is_callable($aGetMethod)) || (isset($aSetMethod) && !is_callable($aSetMethod)))
			throw new IllegalArgumentException('Property '.$aName.' defined with invalid get/set methods.');
		if (property_exists($this,$aName)) {
			if (empty($aDefaultValue))
				$aDefaultValue = $this->$aName;
			unset($this->$aName);
		}
		$this->_properties[$aName] = array('on_get'=>$aGetMethod,'on_set'=>$aSetMethod,'value'=>$aDefaultValue);
	}
	
	public function undefineProperty($aName) {
		unset($this->_properties[$aName]);
	}

	public function setup($anActor, $anAction) {
		$this->me = new ReflectionClass($this);
		$this->_actor = $anActor;
		$this->_director = $anActor->director;
		$this->_config = $anActor->config;
		$this->_action = $anAction;
		$this->_dbError = false;
		$this->setupDefaults();
		$this->setupGetVars();
		$this->setupPostVars();

		$this->bHasBeenSetup = true;
	}

	public function cleanup() {
		$this->popDbResults();
		parent::cleanup();
	}
	
	protected function setupDefaults() {
		$this->on_set_session_var = function ($thisScene, $aName, $aValue) { 
				$thisScene->_director[$aName] = $aValue; 
				return $aValue; 
		};
		$this->defineProperty('_row_class',function ($thisScene, $aName, $aValue) { 
				$thisScene->_row_class = $aValue+1; 
				return ($aValue%2)?'"row1"':'"row2"'; 
		},null,1);
		$this->defineProperty('_rowClass',function ($thisScene, $aName, $aValue) { 
				$thisScene->_rowClass = $aValue+1; 
				return ($aValue%2)?'row1':'row2'; 
		},null,1);
		$this->checkMobileDevice();

		$this->form_name = 'form_'.$this->_action;
		$this->view_path = BITS_APP_PATH.'views'.DIRECTORY_SEPARATOR;
		$this->actor_view_path = $this->view_path.$this->_actor->mySimpleClassName.DIRECTORY_SEPARATOR;
		$this->page_header = $this->getViewPath($this->actor_view_path.'header');
		$this->page_footer = $this->getViewPath($this->actor_view_path.'footer');
		$this->page_user_msgs = $this->getViewPath($this->actor_view_path.'user_msgs');
		$this->app_header = $this->getViewPath($this->view_path.'header');
		$this->app_footer = $this->getViewPath($this->view_path.'footer');
		$this->app_user_msgs = $this->getViewPath($this->view_path.'user_msgs');
		
		$this->myIconStyle = "none";
	}
	
	protected function setupGetVars() {
		//grab all _GET vars and incorporate them
		foreach ($_GET as $key => $val) {
			$this->$key = $val;
		}
	}
	
	protected function setupPostVars() {
		//grab all _POST vars and incorporate them
		foreach ($_POST as $key => $val) {
			$this->$key = $val;
		}
	}
	
	static public function getMapValue($anArray, $aKey) {
		if (array_key_exists($aKey,$anArray)) {
			return $anArray[$aKey];
		} else {
			return null;
		}
	}

	public function strip_spaces($aName) {
		$this->$aName = Strings::strip_spaces($this->$aName);
	}
	
	public function nullIfEmptyStr($aName) {
		if (isset($this->$aName)) {
			$s = trim($this->$aName);
			return ($s!=='') ? $s : null;
		} else {
			return null;
		}
	}
	
	public function pushDbResult($aDbResult, $aResultName=null) {
		if (empty($aResultName))
			$aResultName = '_dbResult_var'.$this->_dbResult_next_id;
		$this->$aResultName = $aDbResult;
		$this->_dbResNames[] = $aResultName;
		return $this->$aResultName;
	}
	
	public function popDbResults() {
		foreach ($this->_dbResNames as $theResultName) {
			$this->$theResultName->closeCursor();
			unset($this->$theResultName);
			unset($theResultName);
		}
		$this->_dbResNames = array();
	}
	
	//pre-html5 js validation possible
	public function formValidation($aFormName) {
		/**/
		//this is for using the Microsoft ajax jquery validator
		$validationMethod = 'validate_'.$aFormName;
		if (!$this->me->hasMethod($validationMethod))
			return '';
		$validate_input_info = $this->$validationMethod();
		$validate_input_info['debug'] = false;
		$validate_input_info['submitHandler'] = 'function(form) {$.post(\''.REQUEST_URL.'\', '.
					'$("#'.$aFormName.'").serialize(), function(data) {$(\'#validation_results\').html(data);});}';
		
		$s = '<script type="text/javascript">$(document).ready(function(){ $("#'.$aFormName.'").validate('."\n";
		$s .= Strings::phpArray2jsArray($validate_input_info);
		$s .= '); }); </script>'."\n";
		$s .= '<style>label.error {width: 250px; display: inline; color: red;}</style>'."\n";
		/*
		//jquery-validation engine
        $s = '<script type="text/javascript">jQuery(document).ready(function(){jQuery("#'.$aFormName.'").validationEngine();});'."\n";
		$validationMethod = 'validate_functions_'.$aFormName;
		if ($this->me->hasMethod($validationMethod))
        	$s .= $this->$validationMethod();
        $s .= "</script>\n";
        */
		return $s;
	}
	
	public function createHiddenPosts(array $aNames) {
		$w = '';
		foreach ($aNames as $varName) {
			$w .= Widgets::createHiddenPost($varName,$this->$varName)."\n";
		}
		return $w;
	}
	
	public function checkMobileDevice() {
		//TODO: Detect Mobile device and change view file accordingly
		$this->is_mobile = (self::getMapValue($_GET,'mobile') || !self::getMapValue($_GET,'full'));
	}
	
	public function getViewPath($aFilePath) {
		if ($this->is_mobile && file_exists($aFilePath.'.m.php'))
			return $aFilePath.'.m.php';
		else
			return $aFilePath.'.php';
	}

	public function includeMyHeader() {
		$myHeader = $this->page_header;
		if (!file_exists($myHeader))
			$myHeader = $this->app_header;
		if (file_exists($myHeader)) {
			$recite =& $this; $v =& $this; //$this, $recite, $v are all the same
			$myView = $myHeader;
			include_once($myHeader);
		}
	}

	public function includeMyFooter() {
		$myFooter = $this->page_footer;
		if (!file_exists($myFooter))
			$myFooter = $this->app_footer;
		if (file_exists($myFooter)) {
			$recite =& $this; $v =& $this; //$this, $recite, $v are all the same
			$myView = $myFooter;
			include_once($myFooter);
		}
	}
	
	public function includeMyUserMsgs() {
		$myUserMsgs = $this->page_user_msgs;
		if (!file_exists($myUserMsgs))
			$myUserMsgs = $this->app_user_msgs;
		if (file_exists($myUserMsgs)) {
			$recite =& $this; $v =& $this; //$this, $recite, $v are all the same
			$myView = $myUserMsgs;
			include_once($myUserMsgs);
		} else {
			$this->debugPrint($myUserMsgs.' not found.');
		}
	}
	
	public function renderMyUserMsgsAsString() {
		ob_start();
		$this->includeMyUserMsgs();
		return ob_get_clean();
	}
	
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		return $this->_director->isAllowed($aNamespace,$aPermission,$acctInfo);
	}

	public function isGuest() {
		return $this->_director->isGuest();
	}
	
	public function getProp($aName) {
		return $this->_director->getProp($aName);
	}
	
	public function returnProp($aProp) {
		$this->_director->returnProp($aProp);
	}
	
	public function getRes($aResName) {
		return $this->_director->getRes($aResName);
	}
	
	/**
	 * Same as cueActor() but for myself.
	 * @param string $anAction
	 * @param array $args
	 */
	public function renderFragment($anAction, $args=array()) {
		try {
			$theActor = $this->_actor;
			$theMethod = new ReflectionMethod($theActor,$anAction);
			//if no exception, call the method
			$theMethod->setAccessible(true); //protected from direct "raiseCurtain" calls, but ok for cue() or render*().
			$args['aScene'] = $aScene; //append the scene of our caller as last param in case called method wants it
			$theResult = $theMethod->invokeArgs($theActor,$args);
			if (empty($theResult)) {
				$s = $theActor->renderFragment($anAction);
				unset($theActor);
				return $s;
			} else {
				header('Location: '.$theResult);
			}
		} catch (ReflectionException $e) {
			//no method to call, just ignore it
		}
	}

	/**
	 * Render a fragment and return the string rather than automatically pass it to the output.
	 * @param string $anActorName
	 * @param string $anAction
	 * @param array $args
	 */
	
	public function cueActor($anActorName, $anAction, $args=array()) {
		return $this->_director->cue($this,$anActorName,$anAction,$args);
	}
	
	/**
	 * Return the site home page.
	 */
	public function getHomePage() {
		return $this->_actor->getHomePage();
	}
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param mixed $aRelativeURL - array of path segments OR a bunch of string parameters
	 * equating to path segments.
	 * @return string - returns the site domain + relative path URL.
	 */
	public function getSiteURL($aRelativeURL='', $_=null) {
		return call_user_func_array(array($this->_director, 'getSiteURL'), func_get_args());
	}
		
	/**
	 * 
	 * @param string $aUrl - string/array of relative site path segment(s), if
	 * leading '/' is omitted, current Actor class name is pre-pended to $aUrl.
	 * @param array $aQuery - (optional) array of query key/values.
	 * @return string - Returns the domain + page url.
	 * @see Scene::getSiteURL()
	 */
	public function getMyUrl($aUrl='', array $aQuery=array()) {
		$theUrl = null;
		if (!empty($aUrl) && !is_array($aUrl) && !Strings::beginsWith($aUrl,'/') && !empty($this->_actor)) {
			$theUrl = $this->_director->getSiteURL(strtolower($this->_actor->mySimpleClassName),$aUrl);
		} else {
			$theUrl = $this->_director->getSiteURL($aUrl);
		}
		if (!empty($aQuery)) {
			$theUrl .= '?'.http_build_query($aQuery,'','&');
		}
		return $theUrl;
	}

	/**
	 * Push a message onto the UI message queue.
	 * @param string $aMsgText - text of the message to display.
	 * @param string $aMsgClass - class of the message, one of self::USER_MSG_* constants (default NOTICE).
	 */
	public function addUserMsg($aMsgText, $aMsgClass=self::USER_MSG_NOTICE) {
		if (!isset($_SESSION['user_msgs'])) {
			$this->clearUserMsgs();
		}
		$_SESSION['user_msgs'][] = array('msg_class'=>$aMsgClass, 'msg_text'=>$aMsgText);
	}
	
	/**
	 * Clear out the message queue. Usually done after it is displayed in user_msgs.php view.
	 */
	public function clearUserMsgs() {
		$_SESSION['user_msgs'] = array();
	}
	
	/**
	 * Retrieve the messages to display.
	 * @return array Returns an array of messages, each msg is an array of 'msg_text' and 'msg_class'.
	 */
	public function getUserMsgs() {
		if (!isset($_SESSION['user_msgs'])) {
			$this->clearUserMsgs();
		}
		return $_SESSION['user_msgs'];
	}

	/**
	 * Wrap JS code in the appropriate HTML tag block.
	 * @param string $aJsCode
	 * @return string Return the JS code wrapped appropriately for inclusion in HTML.
	 */
	public function createJsTagBlock($aJsCode, $aId=null) {
		$theIdAttr = (empty($aId)) ? '' : 'id="'.$aId.'"';
		return "<script type=\"text/javascript\" {$theIdAttr}>\n{$aJsCode}\n</script>\n";
	}

	/**
	 * Create standard HTML load CSS file tag.
	 * @param string $aFilename - filename (may include relative URL), be sure not to lead with "/".
	 * @param string $aLocation - URL to prepend to $aFilename, if NULL or not supplied, defaults to BITS_LIB.
	 * @return string Returns the appropriate tag string that will load the CSS file if included in HTML page.
	 */
	public function getCSStag($aFilename, $aLocation=null) {
		if (empty($aFilename))
			return;
		if (empty($aLocation))
			$aLocation = BITS_LIB;
		return Strings::format('<link rel="stylesheet" type="text/css" href="%s/%s">'."\n", $aLocation, $aFilename);
	}
	
	/**
	 * Prints out the standard HTML load CSS file tag.
	 * @param string $aFilename - filename (may include relative URL), be sure not to lead with "/".
	 * @param string $aLocation - URL to prepend to $aFilename, if NULL or not supplied, defaults to BITS_LIB.
	 */
	public function loadCSS($aFilename, $aLocation=null) {
		print($this->getCSStag($aFilename, $aLocation));
	}
	
	/**
	 * Create standard HTML load JavaScript file tag.
	 * @param string $aFilename - filename (may include relative URL), be sure not to lead with "/".
	 * @param string $aLocation - URL to prepend to $aFilename, if NULL or not supplied, defaults to BITS_LIB.
	 * @return string Returns the appropriate tag string that will load the JavaScript file if included in HTML page.
	 */
	public function getScriptTag($aFilename, $aLocation=null) {
		if (empty($aFilename))
			return;
		if (empty($aLocation))
			$aLocation = BITS_LIB;
		return Strings::format('<script type="text/javascript" src="%s/%s"></script>'."\n", $aLocation, $aFilename);
	}
	
	/**
	 * Prints out the standard HTML load JavaScript file tag.
	 * @param string $aFilename - filename (may include relative URL), be sure not to lead with "/".
	 * @param string $aLocation - URL to prepend to $aFilename, if NULL or not supplied, defaults to BITS_LIB.
	 */
	public function loadScript($aFilename, $aLocation=null) {
		print($this->getScriptTag($aFilename, $aLocation));
	}
	

}//end class

}//end namespace
