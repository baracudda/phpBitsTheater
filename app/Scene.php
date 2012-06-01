<?php
namespace app;
use com\blackmoonit\AdamEve as BaseScene;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use app\config\I18N;
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
	public $me = null;
	public $_actor = null;
	public $_director = null;
	public $_config = null;
	public $_action = '';
	public $_dbError = false;
	protected $_methodList = array(); // list of all custom added methods
	protected $_properties = array(); // list of all added properties
	protected $_dbResNames = array(); // list of all dbResult var names
	
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
			//throw new \InvalidArgumentException("Property {$aName} does not exist.");
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
			throw new \InvalidArgumentException('Property '.$aName.' defined with invalid get/set methods.');
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

	/**
	 * Constructor that will call __construct%numargs%(...) if any are passed in
	 */
	public function __construct() {
		$this->_setupArgCount = 2;
        call_user_func_array('parent::__construct',func_get_args());
	}
   
	public function setup($anActor, $anAction) {
		parent::setup();
		$this->me = new \ReflectionClass($this);
		$this->_actor = $anActor;
		$this->_director =& $anActor->director;
		$this->_config = $anActor->config;
		$this->_action = $anAction;
		$this->_dbError = false;
		$this->setupDefaults();
		$this->setupPostVars();
	}

	public function cleanup() {
		$this->popDbResults();
		parent::cleanup();
	}
	
	protected function setupDefaults() {
		$this->on_set_session_var = function ($thisScene, $aName, $aValue) { $thisScene->_director[$aName] = $aValue; return $aValue; };
		unset($this->name); //unwanted ancestor var
		$this->defineProperty('_dbResult_next_id',function ($thisScene, $aName, $aValue) { return $aValue++; },null,1);
		$this->checkMobileDevice();

		$this->form_name = 'form_'.$this->_action;
		$this->view_path = BITS_PATH.'app'.DIRECTORY_SEPARATOR.'view'.DIRECTORY_SEPARATOR;
		$this->actor_view_path = $this->view_path.$this->_actor->name.DIRECTORY_SEPARATOR;
		$this->page_header = $this->getViewPath($this->actor_view_path.'header');
		$this->page_footer = $this->getViewPath($this->actor_view_path.'footer');
		$this->app_header = $this->getViewPath($this->view_path.'header');
		$this->app_footer = $this->getViewPath($this->view_path.'footer');
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
			$recite =& $this; $v =& $recite; //$this, $recite, $v are all the same
			$myView = $myHeader;
			include_once($myHeader);
		}
	}

	public function includeMyFooter() {
		$myFooter = $this->page_footer;
		if (!file_exists($myFooter))
			$myFooter = $this->app_footer;
		if (file_exists($myFooter)) {
			$recite =& $this; $v =& $recite; //$this, $recite, $v are all the same
			$myView = $myFooter;
			include_once($myFooter);
		}
	}
	
	public function getRes($aName) {
		return $this->_director->getRes($aName);
	}
	
	public function cueActor($anActorName, $anAction, $args=array()) {
		return $this->_director->cue($this,$anActorName,$anAction,$args);
	}
	
}//end class

}//end namespace
