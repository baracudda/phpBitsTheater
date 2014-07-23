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
use com\blackmoonit\AdamEve as BaseActor;
use BitsTheater\Scene as MyScene;
use com\blackmoonit\Strings;
use \ReflectionClass;
use \BadMethodCallException;
use \Exception;
use com\blackmoonit\exceptions\FourOhFourExit;
use com\blackmoonit\exceptions\SystemExit;
{//begin namespace

/*
 * Base class for all Actors in the app.
 */
class Actor extends BaseActor {
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
	
	const _SetupArgCount = 2; //number of args required to call the setup() method.
	const DEFAULT_ACTION = '';
	const ALLOW_URL_ACTIONS = true;
	/**
	 * @var Director
	 */
	public $director = NULL; //session vars can be accessed like property (ie. director->some_session_var; )
	//public $config = NULL; //config model used essentially like property (ie. config[some_key]; ) Dynamically created when accessed for 1st time.
	/**
	 * @var MyScene
	 */
	public $scene = NULL; //scene ui interface used like properties (ie. scene->some_var; (which can be functions))
	protected $action = NULL;
	protected $renderThisView = NULL; // REST service actions may wish to render a single view e.g. JSONoutput.php or XMLout.php

	//static public function _rest_handler() {}; //define this static function if Actor is actually a REST handler.
	
	public function setup(Director $aDirector, $anAction) {
		$this->director = $aDirector;
		$this->action = $anAction;
		$theSceneClass = Director::getSceneClass($this->mySimpleClassName);
		if (!class_exists($theSceneClass)) {
			Strings::debugLog(__NAMESPACE__.': cannot find Scene class: '.$theSceneClass);
		}
		$this->scene = new $theSceneClass($this,$anAction);
		$this->bHasBeenSetup = true;
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
		$aDirector->admitAudience(); //even guests may get to see some pages, ignore function result
		$theResult = call_user_func_array(array($theActor,$anAction),$aQuery);
		if (!empty($theResult))
			header('Location: '.$theResult);
		else
			$theActor->renderView($theActor->renderThisView);
		$theActor = null;
	}
	
	public function renderView($anAction=null) {
		if ($anAction=='_blank') return;
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
				if (empty($this->$aName) && $this->director->canConnectDb() && $this->director->isInstalled()) {
					try {
						$theResult = $this->director->getProp('Config');
						$this->config = $theResult;
						return $theResult;
					} catch (Exception $e) {
						syslog(LOG_ERR,'load config model failed: '.$e->getMessage());
					}
				}
				return null;
			default:
				if ($this->director->isDebugging())
					throw new Exception('Cannot find actor->'.$aName.', check spelling.');
				return null;
		}
	}
	
	static public function isActionUrlAllowed($aAction) {
		if (static::ALLOW_URL_ACTIONS) {
			$bIsAjaxCall = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
			//allow if url is an ajax call, else reject if method has "ajax" prefixed to it.
			return ( $bIsAjaxCall  || !Strings::beginsWith($aAction,'ajax') );
		} else {
			return false;
		}
	}
	
	/**
	 * @return Director Returns the director object.
	 */
	public function getDirector() {
		return $this->director;
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
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param mixed $aRelativeURL - array of path segments OR a bunch of string parameters
	 * equating to path segments.
	 * @return string - returns the site domain + relative path URL.
	 */
	public function getSiteURL($aRelativeURL='', $_=null) {
		return call_user_func_array(array($this->director, 'getSiteURL'), func_get_args());
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
		if (!empty($aUrl) && !is_array($aUrl) && !Strings::beginsWith($aUrl,'/')) {
			$theUrl = $this->director->getSiteURL(strtolower($this->mySimpleClassName),$aUrl);
		} else
			$theUrl = $this->director->getSiteURL($aUrl);
		if (!empty($aQuery)) {
			$theUrl .= '?'.http_build_query($aQuery,'','&');
		}
		return $theUrl;
	}

	public function getAppId() {
		return $this->director['played'];
	}
	
	public function getHomePage() {
		return BITS_URL.'/'.configs\Settings::getLandingPage();
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
	
	/**
	 * If the menu being used supports highlighting, highlight the menu passed in.
	 * @param string $aMenuKey - menu key name as used in res/MenuInfo.
	 */
	public function setCurrentMenuKey($aMenuKey) {
		$this->director['current_menu_key'] = $aMenuKey;
	}
	
	public function getConfigSetting($aConfigName) {
		if ($this->config)
			return $this->config[$aConfigName];
	}
	
	/**
	 * @see Director::getSiteMode()
	 * @return string Returns the site mode config setting.
	 */
	public function getSiteMode() {
		if ($this->config)
			return $this->config['site/mode'];
		else
			return self::SITE_MODE_NORMAL;
	}
	
}//end class

}//end namespace
