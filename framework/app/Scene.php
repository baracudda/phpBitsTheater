<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *	  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BitsTheater;
use com\blackmoonit\AdamEve as BaseScene;
use BitsTheater\costumes\IDirected;
use BitsTheater\models\Config;
use com\blackmoonit\exceptions\IllegalArgumentException;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use Traversable;
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
class Scene extends BaseScene
implements IDirected
{
	const _SetupArgCount = 2; //number of args required to call the setup() method.
	
	/**
	 * Normal website operation mode.
	 * @var string
	 */
	const SITE_MODE_NORMAL = Director::SITE_MODE_NORMAL;
	/**
	 * Refuse connections while the site is being worked on.
	 * @var string
	 */
	const SITE_MODE_MAINTENANCE = Director::SITE_MODE_MAINTENANCE;
	/**
	 * Use local resources as much as possible (little/no net connection)
	 * @var string
	 */
	const SITE_MODE_DEMO = Director::SITE_MODE_DEMO;
	
	/**
	 * @var ReflectionClass
	 */
	public $me = null;
	/**
	 * @var Actor
	 */
	public $_actor = null;
	/**
	 * @var Director
	 */
	public $_director = null;
	/**
	 * @var Config
	 */
	public $_config = null;
	public $_action = '';
	public $_dbError = false;
	protected $_methodList = array(); // list of all custom added methods
	protected $_properties = array(); // list of all added properties
	protected $_dbResNames = array(); // list of all dbResult var names
	const USER_MSG_NOTICE = 'notice';
	const USER_MSG_WARNING = 'warning';
	const USER_MSG_ERROR = 'error';
	
	/**
	 * Total row count used for data pager.
	 * @var number
	 */
	public $_pager_total_row_count = null;
	/**
	 * Paged data results when TRUE.
	 * @var boolean
	 */
	public $_pager_enabled = true;
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['me']);
		unset($vars['_actor']);
		unset($vars['_director']);
		unset($vars['_config']);
		unset($vars['_methodList']);
		unset($vars['_properties']);
		unset($vars['_dbResNames']);
		unset($vars['_pager_total_row_count']);
		unset($vars['_pager_enabled']);
		unset($vars['myIconStyle']);
		unset($vars['is_mobile']);
		unset($vars['view_path']);
		unset($vars['actor_view_path']);
		unset($vars['page_header']);
		unset($vars['page_footer']);
		unset($vars['page_user_msgs']);
		unset($vars['app_header']);
		unset($vars['app_footer']);
		unset($vars['app_user_msgs']);
		return $vars;
	}
	
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
		if ($this->_director->canConnectDb() && $this->_director->isInstalled()) {
			$this->_config = $this->_director->getProp('Config');
		}
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
	
	/**
	 * Convert JSON data and incorporate them like POST and GET vars.
	 * @param string $aJsonData - JSON data to incorporate.
	 */
	protected function setupJsonVars($aJsonData) {
		$theData = null;
		if (!empty($aJsonData)) {
			$theData = json_decode($aJsonData,true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$this->errorLog(__METHOD__.' json error: '.json_last_error().' data='.$this->debugStr($aJsonData));
			}
		}
		if (!empty($theData)) {
			foreach ($theData as $key => $val) {
				$this->$key = $val;
			}
		}
	}
	
	/**
	 * grab all $_GET vars and incorporate them
	 */
	protected function setupGetVars() {
		foreach ($_GET as $key => $val) {
			$this->$key = $val;
		}
	}
	
	/**
	 * grab all $_POST vars and incorporate them
	 */
	protected function setupPostVars() {
		//$this->debugLog(__METHOD__.' server='.$this->debugStr($_SERVER));
		//Retro-fit REST library client seen sending: CONTENT_TYPE = "application/json; charset=UTF-8"
		if (!empty($_SERVER['CONTENT_TYPE']) && Strings::beginsWith($_SERVER['CONTENT_TYPE'], 'application/json')) {
			$this->setupJsonVars(file_get_contents('php://input'));
		} else {
			//multipart/form-data or application/x-www-form-urlencoded will usually get auto-converted to $_POST[].
			foreach ($_POST as $key => $val) {
				$this->$key = $val;
			}
			if (!empty($_POST['post_as_json']))
				$this->setupJsonVars($_POST['post_as_json']);
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
		if (file_exists($aFilePath))
			return $aFilePath;
		else if ($this->is_mobile && file_exists($aFilePath.'.m.php'))
			return $aFilePath.'.m.php';
		else
			return $aFilePath.'.php';
	}

	public function includeMyHeader($aExtraHeaderHtml=null) {
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
	
	/**
	 * Return the director object.
	 * @return Director Returns the site director object.
	 */
	public function getDirector() {
		return $this->_director;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\IDirected::isAllowed()
	 */
	public function isAllowed($aNamespace, $aPermission, $aAcctInfo=null) {
		return $this->getDirector()->isAllowed($aNamespace, $aPermission, $aAcctInfo);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\IDirected::isGuest()
	 */
	public function isGuest() {
		return $this->getDirector()->isGuest();
	}
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\IDirected::checkAllowed()
	 */
	public function checkAllowed($aNamespace, $aPermission, $aAcctInfo=null) {
		return $this->getDirector()->checkAllowed($aNamespace, $aPermission, $aAcctInfo);
	}
	
	/**
	 * {@inheritDoc}
	 * @return $this
	 * @see \BitsTheater\costumes\IDirected::checkPermission()
	 */
	public function checkPermission($aNamespace, $aPermission, $aAcctInfo=null)
	{
		$this->getDirector()->checkPermission($aNamespace, $aPermission, $aAcctInfo);
		return $this;
	}
	
	/**
	 * Return a Model object, creating it if necessary.
	 * @param string $aName - name of the model object.
	 * @return Model Returns the model object.
	 */
	public function getProp($aName) {
		return $this->getDirector()->getProp($aName);
	}
	
	/**
	 * Let the system know you do not need a Model anymore so it
	 * can close the database connection as soon as possible.
	 * @param Model $aProp - the Model object to be returned to the prop closet.
	 */
	public function returnProp($aProp) {
		$this->getDirector()->returnProp($aProp);
	}

	/**
	 * Get a resource based on its combined 'namespace/resource_name'.
	 * @param string $aName - The 'namespace/resource[/extras]' name to retrieve.
	 */
	public function getRes($aName) {
		return $this->getDirector()->getRes($aName);
	}
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param mixed $aRelativeURL - array of path segments OR a bunch of string parameters
	 * equating to path segments.
	 * @return string - returns the site domain + relative path URL.
	 */
	public function getSiteUrl($aRelativeURL='', $_=null) {
		return call_user_func_array(array($this->getDirector(), 'getSiteUrl'), func_get_args());
	}
	
	/**
	 * Get the setting from the configuration model.
	 * @param string $aSetting - setting in form of "namespace/setting"
	 * @throws \Exception
	 */
	public function getConfigSetting($aSetting) {
		return $this->getDirector()->getConfigSetting($aSetting);
	}
	
	/**
	 * @see Director::getSiteMode()
	 * @return string Returns the site mode config setting.
	 */
	public function getSiteMode() {
		if ($this->_config) {
			return $this->_config['site/mode'];
		} else
			return self::SITE_MODE_NORMAL;
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
		} catch (\ReflectionException $e) {
			//no method to call, just ignore it
		}
	}

	/**
	 * Render a fragment and return the string rather than automatically pass it to the output.
	 * @param string $anActorName - the actor to instantiate and use.
	 * @param string $anAction - the actor's method to call
	 * @param array $_ - other params that will be passed on to Director::cue().
	 */
	
	public function cueActor($anActorName, $anAction, $_=null) {
		$args = func_get_args();
		array_unshift($args,$this);
		return call_user_func_array(array($this->getDirector(),'cue'), $args);
	}
	
	/**
	 * Return the site home page.
	 */
	public function getHomePage() {
		return $this->_actor->getHomePage();
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
			$theUrl = $this->_director->getSiteURL($this->_actor->mySimpleClassName,$aUrl);
		} else {
			$theUrl = $this->_director->getSiteURL($aUrl);
		}
		if (!empty($aQuery)) {
			$theUrl .= '?'.http_build_query($aQuery,'','&');
		}
		return $theUrl;
	}

	/**
	 * @return string - Returns the http/https scheme in use followed by '://'.
	 */
	public function getMyUrlScheme() {
		return Strings::getUrlSchemeInUse();
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
	 * If the parameter is just empty or whitespace, nothing will be returned.
	 * @param string $aJsCode - the JavaScript code.
	 * @return string|NULL Return the JS code wrapped appropriately for
	 * inclusion in HTML or NULL if $aJsCode is nothing but whitespace.
	 */
	public function createJsTagBlock($aJsCode, $aId=null)
	{
		$theTrimmedCode = trim($aJsCode) ;
		if( !empty($theTrimmedCode) )
		{
			$theIdAttr = (empty($aId)) ? '' : 'id="'.$aId.'"';
			return "<script type=\"text/javascript\" {$theIdAttr}>" . PHP_EOL
					. $theTrimmedCode . PHP_EOL
					. '</script>' . PHP_EOL
			;
		}
	}

	/**
	 * Wrap CSS code in the appropriate HTML tag block.
	 * If the parameter is just empty or whitespace, nothing will be returned.
	 * @param string $aCssCode - the CSS code.
	 * @return string|NULL Return the CSS code wrapped appropriately for
	 * inclusion in HTML or NULL if $aCssCode is nothing but whitespace.
	 */
	public function createCssTagBlock($aCssCode)
	{
		$theTrimmedCode = trim($aCssCode) ;
		if( !empty($theTrimmedCode) )
		{
			return "<style type=\"text/css\">" . PHP_EOL
					. $theTrimmedCode . PHP_EOL
					. '</style>' . PHP_EOL
			;
		}
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
	 * @param string $aLocation - URL to prepend to $aFilename.
	 * @param string $aFilename - filename, may include relative URL (be sure not to lead with "/").
	 * @return string Returns the appropriate tag string that will load the JavaScript file if included in HTML page.
	 */
	protected function returnScriptTag($aLocation, $aFilename) {
		return Strings::format('<script type="text/javascript" src="%s/%s"></script>', $aLocation, $aFilename);
	}
	
	/**
	 * Create standard HTML load JavaScript file tag.
	 * @param string|array|Traversable $aFilename - filename(s), may include relative URL (be sure not to lead with "/").
	 * @param string $aLocation - (optional) URL to prepend to $aFilename, if NULL or not supplied, defaults to BITS_LIB.
	 * @return string Returns the appropriate tag string that will load the JavaScript file if included in HTML page.
	 */
	public function getScriptTag($aFilename, $aLocation=null) {
		if (empty($aFilename))
			return;
		if (empty($aLocation))
			$aLocation = BITS_LIB;
		
		if (is_array($aFilename) || $aFilename instanceof Traversable) {
			$theResult = '';
			foreach ($aFilename as $theFilename) {
				$theResult .= $this->returnScriptTag($aLocation, $theFilename) . "\n";
			}
			return $theResult;
		} else {
			return $this->returnScriptTag($aLocation, $aFilename);
		}
	}
	
	/**
	 * Prints out the standard HTML load JavaScript file tag.
	 * @param string $aFilename - filename (may include relative URL), be sure not to lead with "/".
	 * @param string $aLocation - URL to prepend to $aFilename, if NULL or not supplied, defaults to BITS_LIB.
	 */
	public function loadScript($aFilename, $aLocation=null) {
		print($this->getScriptTag($aFilename, $aLocation)."\n");
	}
	
	/**
	 * If not logged in, check for "Basic HTTP Auth" header/POST var and attempt to login with that user/pw info.
	 * @deprecated (if called, performs a NO-OP)
	 */
	public function checkForBasicHttpAuth() {
		return;
		/* handled inside checkTicket() now
		$theDirector = $this->getDirector();
		if ($theDirector->isGuest()) {
			//authenticate
			if (empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW'])) {
				$theAuthKey = (!empty($_SERVER['HTTP_AUTHORIZATION'])) ? $_SERVER['HTTP_AUTHORIZATION'] : $this->HTTP_AUTHORIZATION;
				if (!empty($theAuthKey)) {
					list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($theAuthKey,6)));
					unset($_SERVER['HTTP_AUTHORIZATION']);
				}
			}
			if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
				$this->{AuthBase::KEY_userinfo} = $_SERVER['PHP_AUTH_USER'];
				$this->{AuthBase::KEY_pwinput} = $_SERVER['PHP_AUTH_PW'];
				$theDirector->getProp('Auth')->checkTicket($this);
			}
			unset($_SERVER['PHP_AUTH_PW']);
		}
		*/
	}

	/**
	 * Immediately kills script processing and page rendering.
	 * Sends back the standard Basic HTTP auth failure headers along
	 * with the 'auth/msg_basic_auth_fail' localized string resource
	 * as the die() message if nothing is passed in.
	 * @param string $aDieMsg - the die() string paramater to use.
	 */
	public function dieAsBasicHttpAuthFailure($aDieMsg=null) {
		if (!isset($aDieMsg))
			$aDieMsg = $this->getRes('auth/msg_basic_auth_fail');
		header('WWW-Authenticate: Basic realm="'.$this->getRes('website/header_meta_title').'"');
		if( function_exists('http_response_code') )
			http_response_code(401) ;
		else
			header('HTTP/1.0 401 Unauthorized') ;
		die($aDieMsg);
	}
	
	/**
	 * @return number Returns the total count for query
	 *   regardless of paging.
	 */
	public function getPagerTotalRowCount() {
		return $this->_pager_total_row_count;
	}
	
	/**
	 * Models can set the query total regardless of paging.
	 * @param number $aTotalRowCount - the total.
	 * @return \BitsTheater\Scene
	 */
	public function setPagerTotalRowCount($aTotalRowCount) {
		$this->_pager_total_row_count = max($aTotalRowCount,0);
		return $this;
	}
	
	/**
	 * Check to see if the pager is enabled.
	 * @return boolean - returns the state of the pager.
	 */
	public function isPagerEnabled() {
		return $this->_pager_enabled;
	}
	
	/**
	 * Enable/disable the pager.
	 * @param boolean $aEnabled - desired state of pager.
	 * @return \BitsTheater\Scene
	 */
	public function setPagerEnabled($aEnabled) {
		$this->_pager_enabled = !empty($aEnabled);
		return $this;
	}
	
	/**
	 * Get the defined maximum row count for a page in a pager.
	 * @param int $aDefaultPageSize - (optional) override the default page size of 25.
	 * @return int Returns the max number of rows shown for a single pager page;
	 * guaranteed to be 1 or greater.
	 */
	public function getPagerPageSize($aDefaultPageSize=25) {
		if (!$this->isPagerEnabled())
			return 0;
		//TODO $this->_config['display/query_limit'];
		$theLimit = max($aDefaultPageSize,1);
		if (!empty($this->query_limit) && is_numeric($this->query_limit))
			$theLimit = min(max($this->query_limit,1),1000);
		else if (!empty($this->pagesz) && is_numeric($this->pagesz))
			$theLimit = min(max($this->pagesz,1),1000);
		return $theLimit;
	}
	
	/**
	 * @return int Returns the current pager page.
	 */
	public function getPagerCurrPage() {
		$thePage = 1;
		if (!empty($this->page) && is_numeric($this->page))
			$thePage = max($this->page,1);
		return $thePage;
	}
	
	/**
	 * Get the SQL query offset based on pager page size and page desired.
	 * @param int $aPagerPageNum
	 */
	public function getQueryOffset($aPageSize, $aPageNum=null) {
		$theOffset = 0;
		if (!empty($this->query_offset) && is_numeric($this->query_offset))
			$theOffset = $this->query_offset;
		else if (($aPageSize>0) && !isset($aPageNum))
			$theOffset = ($this->getPagerCurrPage()-1)*$aPageSize;
		return $theOffset;
	}
	
	/**
	 * Return the SQL "LIMIT" expression for the given Database type based
	 * on the "standard Scene variables" defined.
	 * @param string $aDbType - one of the various PDO::DB_TYPE.
	 * @return string Returns the LIMIT string to use, including prefix " LIMIT".
	 */
	public function getQueryLimit($aDbType) {
		$theResult = null;
		$theLimit = $this->getPagerPageSize();
		if ($theLimit>0) {
			$theOffset = $this->getQueryOffset($theLimit);
			switch ($aDbType) {
				case Model::DB_TYPE_MYSQL:
				case Model::DB_TYPE_PGSQL:
				default:
					$theResult = ' LIMIT '.$theLimit . (($theOffset>0) ? ' OFFSET '.$theOffset : '');
			}//switch
		}//if
		return $theResult;
	}
	
	/**
	 * Create the pager links for a table of data.
	 * @param string $aAction - the relative action used as the first param in $this->getMyUrl().
	 * @param int $aTotalCount - the total number of items in dataset.
	 * @param int $aCurrPage - (optional) the curent page, if NULL (default), calls getPagerCurrPage()
	 * @return string Returns the HTML used for the pager.
	 * @see Scene::getPagerCurrPage()
	 */
	public function getPagerHtml($aAction, $aTotalCount=null, $aCurrPage=null) {
		$thePagerCount = 10; // The number of pages to display numerically
		$thePagerPageSize = $this->getPagerPageSize();
		$theCurrPage = (empty($aCurrPage)) ? $this->getPagerCurrPage() : max($aCurrPage,1);
		$theTotalCount = (empty($aTotalCount)) ? $this->getPagerTotalRowCount() : max($aTotalCount,0);
		$theTotalPages = ceil($theTotalCount/$thePagerPageSize);
		
		//print($aAction.' tc='.$theTotalCount.' cp='.$theCurrPage.' tp='.$theTotalPages.' pps='.$thePagerPageSize);
		
		if ($theTotalPages>1) {
			$theQueryParams = array();
			if ($thePagerPageSize!=25) {
				$theQueryParams['pagesz'] = $thePagerPageSize;
			}
			$theLabelSpacer = $this->getRes('pager/label_spacer');
			$thePager = '<div class="pager"> ';
			
			$theQueryParams['page'] = 1;
			$thePager .= '<a href="'.$this->getMyUrl($aAction,$theQueryParams).'"';
			if ($theCurrPage<=1) //hide, but still take up space
				$thePager .= ' class="invisible"';
			$thePager .= '>';
			if ($theSrc = $this->getRes('pager/imgsrc/pager_first')) {
				$thePager .= '<img src="'.$theSrc.'" class="pager" alt="'.$this->getRes('pager/label_first').'">';
			} else {
				$thePager .= $this->getRes('pager/label_first');
			}
			$thePager .= '</a>';

			$thePager .= $theLabelSpacer;
			
			$theQueryParams['page'] = $theCurrPage-1;
			$thePager .= '<a href="'.$this->getMyUrl($aAction,$theQueryParams).'"';
			if ($theCurrPage<=1) //hide, but still take up space
				$thePager .= ' class="invisible"';
			$thePager .= '>';
			if ($theSrc = $this->getRes('pager/imgsrc/pager_previous')) {
				$thePager .= '<img src="'.$theSrc.'" class="pager" alt="'.$this->getRes('pager/label_previous').'">';
			} else {
				$thePager .= $this->getRes('pager/label_previous');
			}
			$thePager .= '</a>';

			if ($theCurrPage <= ($thePagerCount/2)) {
				$i_start = 1;
			} else {
				$i_start = $theCurrPage-($thePagerCount/2)+1;
			}
			$i_final = min($i_start+$thePagerCount-1,$theTotalPages);
			$i_start = max(1,$i_final-$thePagerCount+1);
			for ($i=$i_start; ($i <= $i_final); $i++) {
				//$thePager .= ($i==$i_start && $i_start>1)?' … ':$theLabelSpacer;
				$thePager .= $theLabelSpacer;
				if ($i != $theCurrPage) {
					$theQueryParams['page'] = $i;
					$thePager .= '<a href="'.$this->getMyUrl($aAction,$theQueryParams).'">&nbsp;'.$i.'&nbsp;</a>';
				} else {
					$thePager .= '<span class="current-page">('.$i.')</span>';
				}
				$final_i = $i;
			}
			//if ($final_i < $theTotalPages) {
			//	$thePager .= $theLabelSpacer.'… ';
			//}
			
			$thePager .= $theLabelSpacer;

			$theQueryParams['page'] = min($theCurrPage+1,$theTotalPages);
			$thePager .= '<a href="'.$this->getMyUrl($aAction,$theQueryParams).'"';
			if ($theCurrPage >= $theTotalPages) //hide, but still take up space
				$thePager .= ' class="invisible"';
			$thePager .= '>';
			if ($theSrc = $this->getRes('pager/imgsrc/pager_next')) {
				$thePager .= '<img src="'.$theSrc.'" class="pager" alt="'.$this->getRes('pager/label_next').'">';
			} else {
				$thePager .= $this->getRes('pager/label_next');
			}
			$thePager .= '</a>';
					
			$thePager .= $theLabelSpacer;
				
			$theQueryParams['page'] = $theTotalPages;
			$thePager .= '<a href="'.$this->getMyUrl($aAction,$theQueryParams).'"';
			if ($theCurrPage >= $theTotalPages) //hide, but still take up space
				$thePager .= ' class="invisible"';
			$thePager .= '>';
			if ($theSrc = $this->getRes('pager/imgsrc/pager_last')) {
				$thePager .= '<img src="'.$theSrc.'" class="pager" alt="'.$this->getRes('pager/label_last').'">';
			} else {
				$thePager .= $this->getRes('pager/label_last');
			}
			$thePager .= '</a>';
				
			$thePager .= "</div>";
		} else {
			$thePager = '';
		}
	
		return $thePager;
	}
	
	/**
	 * Useful for debugging sometimes.
	 * @return string - returns GET and POST vars.
	 */
	public function getRequestVars() {
		$s = 'GET={ ';
		//grab all _GET vars and incorporate them
		foreach ($_GET as $key => $val) {
			$s .= $key.'='.$val.'; ';
		}
		$s .= '}, POST={ ';
		//grab all _POST vars and incorporate them
		foreach ($_POST as $key => $val) {
			$s .= $key.'='.$val.'; ';
		}
		$s .= '}';
		return $s;
	}
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	public function isFieldSortable($aFieldName)
	{
		switch ($aFieldName) {
			case 'created_by':
			case 'updated_by':
			case 'created_ts':
			case 'updated_ts':
				return true;
			default:
				return false;
		}//end switch
	}
	
	/**
	 * Returns the human label used for a field.
	 * @param string $aFieldName - one of the property names defined
	 *     for SelectorList costume.
	 */
	public function getColHeaderLabel($aFieldName)
	{
		switch ($aFieldName) {
			case 'record_id':  return $this->getRes('generic/colheader_record_id');
			case 'created_by': return $this->getRes('generic/colheader_created_by');
			case 'updated_by': return $this->getRes('generic/colheader_updated_by');
			case 'created_ts': return $this->getRes('generic/colheader_created_ts');
			case 'updated_ts': return $this->getRes('generic/colheader_updated_ts');
			default:           return $aFieldName;
		}//end switch
	}
	
	/**
	 * Construct the URL used for column headers that support sorting.
	 * @param string $aViewName - the name of the view with the table.
	 * @param string $aFieldName - the field name to sort on.
	 * @return string Returns the URL to be used to sort on the field provided.
	 */
	public function getColHeaderHrefForSortableField($aViewName, $aFieldName)
	{
		return $this->getMyUrl($aViewName, array(
				'orderby' => $aFieldName,
				'orderbyrvs' => ($this->orderby != $aFieldName || $this->orderbyrvs ? 0 : 1),
		));
	}
	
	/**
	 * Construct the HTML used for a column header for a particular sortable field.
	 * @param string $aViewName - the name of the view with the table.
	 * @param string $aFieldName - the field name to sort on.
	 * @param string $aStyle - (optional) the style for the header column.
	 * @return string Returns the HTML to use.
	 */
	public function getColHeaderTextForSortableField($aViewName, $aFieldName, $aStyle=null)
	{
		$theStyle = (!empty($aStyle)) ? ' style="' . $aStyle . '"' : null;
		return '<th'.$theStyle.'><a href="'
				. $this->getColHeaderHrefForSortableField($aViewName, $aFieldName)
				.'">' . $this->getColHeaderLabel($aFieldName) . '</a></th>'
				;
	}
	
	/**
	 * Construct the HTML used for a column header for a particular sortable field.
	 * @param string $aFieldName - the field name to determine label/sorting.
	 * @param string $aStyle - (optional) the style for the header column.
	 * @return string Returns the HTML to use.
	 */
	public function getColHeaderHTML($aFieldName, $aStyle=null)
	{
		if ( $this->isFieldSortable($aFieldName) )
			return $this->getColHeaderTextForSortableField(
					$this->_action, $aFieldName, $aStyle
			);
		else
		{
			$theStyle = (!empty($aStyle)) ? ' style="' . $aStyle . '"' : null;
			return '<th'.$theStyle.'>'.$this->getColHeaderLabel($aFieldName).'</th>';
		}
	}
	
	/**
	 * Construct the HTML necessary to convert a UTC timestamp into the
	 * local-to-browser timezone value via JS code.
	 * @param number/string $aTime - either a UTC timestamp or a MySQL datetime string.
	 * @return string Returns the HTML needed to display local time.
	 */
	public function getLocalTimestampValue($aTime)
	{
		$theTsId = Strings::createUUID();
		$this->jsCode .= Widgets::cnvUtcTs2LocalStr($theTsId, $aTime)."\n";
		return '<span id="'.$theTsId.'" data-orig="'.$aTime.'">'.$aTime.'</span>';
	}
	
	/**
	 * Helper function to obtain specific field cell HTML for the display table.
	 * @param string $aFieldName - the fieldname of the data to display.
	 * @param object $aDataRow - the object containing the field data.
	 * @return string Returns the HTML for the table cell inside &lt;td&gt; tags.
	 */
	public function getColCellValue($aFieldName, $aDataRow)
	{
		switch ($aFieldName) {
			case 'created_by':
				return htmlentities($aDataRow->created_by);
			case 'created_ts':
				return $this->getLocalTimestampValue($aDataRow->created_ts);
			case 'updated_by':
				return htmlentities($aDataRow->updated_by);
			case 'updated_ts':
				$w = '';
				if ($aDataRow->created_ts !== $aDataRow->updated_ts) {
					$w .= $this->getLocalTimestampValue($aDataRow->updated_ts);
				}
				return $w;
			default:
				return '';
		}//end switch
	}
	
}//end class

}//end namespace
