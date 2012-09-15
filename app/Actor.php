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

namespace com\blackmoonit\bits_theater\app;
use com\blackmoonit\AdamEve as BaseActor;
use com\blackmoonit\Strings;
use \ReflectionClass;
use \BadMethodCallException;
{//begin namespace

/*
 * Base class for all Actors in the app.
 */
class Actor extends BaseActor {
	const _SetupArgCount = 2; //number of args required to call the setup() method.
	const DEFAULT_ACTION = '';
	const ALLOW_URL_ACTIONS = true;
	public $director = null;	//session vars can be accessed like property (ie. director->some_session_var; )
	//public $config = null;	//config model used essentially like property (ie. config->some_key; ) Dynamically created when accessed for 1st time.
	public $scene = null;		//scene ui interface used like properties (ie. scene->some_var; (which can be functions))
	protected $action = null;

	//static public function _rest_handler() {}; //define this static function if Actor is actually a REST handler.
	
	public function setup(Director $aDirector, $anAction) {
		parent::setup();
		$this->director = $aDirector;
		$this->action = $anAction;
		$theSceneClass = BITS_BASE_NAMESPACE.'\\app\\scene\\'.$this->mySimpleClassName.'\\'.$anAction;
		if (!class_exists($theSceneClass))
			$theSceneClass = BITS_BASE_NAMESPACE.'\\app\\scene\\'.$this->mySimpleClassName;
		if (!class_exists($theSceneClass))
			$theSceneClass = BITS_BASE_NAMESPACE.'\\app\\Scene';
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
		if (!$this->bHasBeenSetup) throw new BadMethodCallException('setup() must be called first.');
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
		if (!$this->bHasBeenSetup) throw new BadMethodCallException('setup() must be called first.');
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
		//Strings::debugLog('actor->'.$aName.', is_empty='.empty($this->$aName).', canConnDb='.$this->director->canConnectDb());
		switch ($aName) {
			case 'config': 
				if (empty($this->$aName) && $this->director->canConnectDb()) {
					try { 
						$theResult = $this->director->getProp('Config');
						$this->config = $theResult;
						return $theResult;
					} catch (\Exception $e) {
						syslog(LOG_ERR,'load config model failed: '.$e->getMessage());
						return null;
					}
				}
			default:
				if ($this->director->isDebugging())
					throw new \Exception('Cannot find actor->'.$aName.', check spelling.');
				return null;
		}
	}
	
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		return $this->director->isAllowed($aNamespace,$aPermission,$acctInfo);
	}

	public function isGuest() {
		return $this->director->isGuest();
	}
	
	public function getProp($aName) {
		return $this->director->getProp($aName);
	}
	
	public function returnProp($aProp) {
		$this->director->returnProp($aProp);
	}

	public function getRes($aName) {
		return $this->director->getRes($aName);
	}
	
	public function getMyUrl($aPage='', array $aQuery=array()) {
		$theUrl = BITS_URL.$aPage;
		if (!empty($aQuery)) {
			$theUrl .= '?'.http_build_query($aQuery,'','&');
		}
		return $theUrl;
	}

	public function getAppId() {
		return $this->director['played'];
	}
	
	public function getHomePage() {
		return BITS_URL.config\Settings::getLandingPage();
	}
	
	public function throwPermissionDenied($aMsg='') {
		if ($aMsg==='') {
			$aMsg = getRes('generic/msg_permission_denied');
		}
		throw new SystemExit($aMsg,500);
	}
	
	public function getMyAccountID() {
		return $this->director->account_info['account_id'];
	}
	
}//end class

}//end namespace
