<?php
namespace app;
use com\blackmoonit\AdamEve as BaseActor;
use com\blackmoonit\Strings;
use app\Director;
use app\Scene;
{//begin namespace

/*
 * Base class for all Actors in the app.
 */
class Actor extends BaseActor {
	const DEFAULT_ACTION = '';
	const ALLOW_URL_ACTIONS = true;
	public $director = null;	//session vars can be accessed like property (ie. director->some_session_var; )
	//public $config = null;	//config model used essentially like property (ie. config->some_key; )
	public $scene = null;		//scene ui interface used like properties (ie. scene->some_var; (which can be functions))
	protected $action = null;

	/*
	 * Constructor that will call __construct%numargs%(...) if any are passed in
	 */
	public function __construct() {
		$this->_setupArgCount = 2;
        call_user_func_array('parent::__construct',func_get_args());
	}
   
	public function setup(Director $aDirector, $anAction) {
		parent::setup();
		$this->director = $aDirector;
		$this->action = $anAction;
		$me = new \ReflectionClass($this);
		$myShortClassName = $me->getShortName();
		unset($me);  
		$theSceneClass = '\\app\\scene\\'.$myShortClassName.'\\'.$anAction;
		if (!class_exists($theSceneClass))
			$theSceneClass = '\\app\\scene\\'.$myShortClassName;
		if (!class_exists($theSceneClass))
			$theSceneClass = '\\app\\Scene';
		$this->scene = new $theSceneClass($this,$anAction);
	}

	public function cleanup() {
		$this->director->returnProp($this->config);
		unset($this->director);
		unset($this->action);
		unset($this->scene);
		parent::cleanup();
	}
	
	static public function perform(Director $aDirector, $anAction, array $aQuery=array()) {
		if (!is_array($aQuery)) {
			Strings::debugLog('query not array:'.$anAction.'/'.$aQuery);
		}
		$myClass = get_called_class();
		$theActor = new $myClass($aDirector,$anAction);
		$theResult = $aDirector->admitAudience();
		if ($theResult)
			header('Location: '.$theResult);
		else
			$theResult = call_user_func_array(array($theActor,$anAction),$aQuery);
		if ($theResult)
			header('Location: '.$theResult);
		else
			$theActor->renderView();
		$theActor = null;
	}
	
	public function renderView($anAction=null) {
		if (!$this->bHasBeenSetup) throw new \BadMethodCallException('setup() must be called first.');
		if (empty($anAction))
			$anAction = $this->action;
		$recite =& $this->scene; $v =& $recite; //$this->scene, $recite, $v are all the same
		$myView = $recite->getViewPath($recite->actor_view_path.$anAction);
		if (file_exists($myView))
			include($myView);
		else
			throw new FourOhFourExit(str_replace(BITS_ROOT,'',$myView));
	}
	
	/**
	 * Used for partial page renders so sections can be compartmentalized and/or reused by View designers.
	 * @param aViewName - renders app/view/%name%.php, defaults to currently running action if name is empty.
	 */
	public function renderFragment($aViewName=null) {
		if (!$this->bHasBeenSetup) throw new \BadMethodCallException('setup() must be called first.');
		if (empty($aViewName))
			$aViewName = $this->action;
		$recite =& $this->scene; $v =& $recite; //$this->scene, $recite, $v are all the same
		$myView = $recite->getViewPath($recite->actor_view_path.$aViewName);
		if (file_exists($myView)) {
			ob_start();
			include($myView);
			return ob_get_clean();
		}	
	}
	
	public function __get($aName) {
		if ($aName=='config' && empty($this->config) && $this->director->canConnectDb()) {
			try { 
				$this->config = $this->director->getProp('Config');
				return $this->config;
			} catch (\Exception $e) {
				return null;
			}
		}
		
	}
	


}//end class

}//end namespace
