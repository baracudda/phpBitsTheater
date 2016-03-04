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
use BitsTheater\costumes\IDirected;
use BitsTheater\Scene as MyScene;
use BitsTheater\Director;
use BitsTheater\models\Config;
use com\blackmoonit\Strings;
use ReflectionClass;
use BadMethodCallException;
use Exception;
use com\blackmoonit\exceptions\FourOhFourExit;
use com\blackmoonit\exceptions\SystemExit;
use BitsTheater\BrokenLeg;
{//begin namespace

/**
 * Base class for all Actors in the app.
 */
class Actor extends BaseActor
implements IDirected
{
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
	/**
	 * @var Config
	 */
	public $config = NULL; //config model used essentially like property (ie. config[some_key]; )
	/**
	 * @var MyScene
	 */
	public $scene = NULL; //scene ui interface used like properties (ie. scene->some_var; (which can be functions))
	/**
	 * @var string
	 */
	protected $action = NULL;
	/**
	 * '_blank' will not render anything, NULL renders the view with the same name as the action
	 * being performed, any other value tried to render that view file.
	 * @var string
	 */
	protected $renderThisView = NULL;
	
	public function setup(Director $aDirector, $anAction) {
		$this->director = $aDirector;
		$this->action = $anAction;
		$theSceneClass = Director::getSceneClass($this->mySimpleClassName);
		if (!class_exists($theSceneClass)) {
			Strings::debugLog(__METHOD__.': cannot find Scene class: '.$theSceneClass);
		}
		$this->scene = new $theSceneClass($this,$anAction);
		if ($this->director->canConnectDb() && $this->director->isInstalled()) {
			$this->config = $this->director->getProp('Config');
		}
		$this->bHasBeenSetup = true;
	}

	public function cleanup() {
		$this->director->returnProp($this->config);
		unset($this->director);
		unset($this->action);
		unset($this->scene);
		parent::cleanup();
	}
	
	static public function getDefaultAction($anAction=null) {
		return (!empty($anAction)) ? $anAction : static::DEFAULT_ACTION;
	}
	
	static public function perform(Director $aDirector, $anAction, array $aQuery=array()) {
		$myClass = get_called_class();
		$theActor = new $myClass($aDirector,$anAction);
		try {
			$theActor->usherGreetAudience($anAction);
			$aDirector->admitAudience($theActor->scene); //even guests may get to see some pages, ignore function result
			if ($theActor->usherAudienceToSeat($anAction))
				$theResult = call_user_func_array(array($theActor,$anAction),$aQuery);
		} catch (BrokenLeg $e) {
			if ($theActor->renderThisView=='results_as_json') {
				//API calls need to eat the exception and give a sane HTTP Response
				$e->setErrorResponse($theActor->scene);
			} else {
				//non-API calls need to bubble up the exception
				throw $e;
			}
		}
		if (empty($theResult))
			$theActor->renderView($theActor->renderThisView);
		else
			header('Location: '.$theResult);
		$theActor = null;
	}
	
	public function renderView($anAction=null) {
		if ($anAction=='_blank')
			return;
		if (!$this->bHasBeenSetup)
			throw new BadMethodCallException(__CLASS__.'::setup() must be called first.');
		if (empty($anAction))
			$anAction = $this->action;
		$recite =& $this->scene; $v =& $recite; //$this->scene, $recite, $v are all the same
		$myView = $recite->getViewPath($recite->actor_view_path.$anAction);
		if (file_exists($myView))
			include($myView);
		else {
			$theAppView = $recite->getViewPath($recite->view_path.$anAction);
			if (file_exists($theAppView))
				include($theAppView);
			else
				throw new FourOhFourExit(str_replace(BITS_ROOT,'',$myView));
		}
	}
	
	/**
	 * If param is passed in, sets the value; otherwise
	 * returns the current value.
	 * @param string $aViewName - if set, will store it and return $this.
	 * @return Actor Returns the value of renderThisView if nothing is passed in,
	 * otherwise $this is returned if a param isset().
	 */
	public function viewToRender($aViewName=null) {
		if (isset($aViewName)) {
			$this->renderThisView = $aViewName;
			return $this; //for chaining
		} else {
			return $this->renderThisView;
		}
	}
	
	/**
	 * Used for partial page renders so sections can be compartmentalized and/or reused by View designers.
	 * @param aViewName - renders app/view/%name%.php, defaults to currently running action if name is empty.
	 */
	public function renderFragment($aViewName=null) {
		if (!$this->bHasBeenSetup) throw new BadMethodCallException(__CLASS__.'::setup() must be called first.');
		if (empty($aViewName))
			$aViewName = $this->action;
		$recite =& $this->scene; $v =& $recite; //$this->scene, $recite, $v are all the same
		$myView = $recite->getViewPath($recite->actor_view_path.$aViewName);
		//$this->debugLog(__METHOD__.' myView="'.$myView.'", args='.$this->debugStr(func_get_args()));
		if (file_exists($myView)) {
			ob_start();
			include($myView);
			return ob_get_clean();
		}
	}
	
	/**
	 * Return TRUE if the specified action can be activated via a browsers URL. Useful to
	 * restrict actions based on AJAX calls vs. regular URL browsing.
	 * @param string $aAction - method name to be called.
	 * @return boolean Returns TRUE if method call is allowed.
	 */
	static public function isActionUrlAllowed($aAction) {
		if (static::ALLOW_URL_ACTIONS) {
			if (Strings::beginsWith($aAction, 'ajax')) {
				//if method has "ajax" prefixed to it, allow if header indicates it is an AJAX call
				//  NOTE: AngularJS does not set this header by default like jQuery and Symphony do for POSTs.
				return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
			}
			// CSRF token checks must be handled after admitAudience() has been called;
			//   see usherAudienceToSeat() method instead.
			else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Some methods may restrict us on how we can authorize.
	 * @param string $aAction - method name to be called.
	 */
	public function usherGreetAudience($aAction) {
		if (Strings::beginsWith($aAction, 'ajaj')) {
			//auto-set view to json response since AJAJ expects it
			//  also, our auto-response-exception to standard error
			//  object needs to have this view before checking for the
			//  CSRF protection token in the headers.
			$this->viewToRender('results_as_json');

			//if we try to call an ajaj* method without CSRF token header,
			//  then treat as if api* method was called and check for
			//  headers with auth credentials instead.
			if ($this->getDirector()->isInstalled()) {
				$dbAuth = $this->getProp('Auth');
				if (!empty($dbAuth)) {
					$this->scene->bCheckOnlyHeadersForAuth = (!$dbAuth->isCsrfTokenHeaderPresent());
				}
			}
		}
		else if (Strings::beginsWith($aAction, 'api')) {
			//auto-set view to json response
			$this->viewToRender('results_as_json');
			//we need to have a prefix that does the opposite of AJAJ
			//  and allow CORS, at the expense of always providing
			//  any auth if needed.
			$this->scene->bCheckOnlyHeadersForAuth = true;
		}
		else if (Strings::beginsWith($aAction, 'ajax')) {
			//auto-set view to json response, yes, the X in AJAX means XML, but
			//  framework does not have a "default XML" view, yet.
			$this->viewToRender('results_as_json');
		}
		else {
			//we are probably a page that needs rendering, remember it in case
			//  login needs to be forced and then lets us get back to our
			//  intended page.
			$this->getDirector()['lastpagevisited'] = $this->getDirector()['currpagevisited'];
			$this->getDirector()['currpagevisited'] = REQUEST_URL;
		}
	}
	
	/**
	 * Return TRUE if the specified action is allowed to be called.
	 * Also, set any default settings here, if desired.
	 * @param string $aAction - method name to be called.
	 * @return boolean Returns TRUE if method call is allowed.
	 */
	public function usherAudienceToSeat($aAction) {
		$theResult = true;
		if (Strings::beginsWith($aAction, 'ajaj')) {
			//ajaj methods either require CSRF token header OR valid
			//  auth credentials; default our result to checking to
			//  see if auth credentials were supplied.
			$theResult = (!$this->isGuest());
			//now check to see if CSRF token header was defined/present
			$dbAuth = $this->getProp('Auth');
			if (!empty($dbAuth) && $dbAuth->isCsrfTokenHeaderPresent()) {
				$theResult = $dbAuth->checkCsrfTokenHeader();
			}
			$this->returnProp($dbAuth);
			if (!$theResult)
				throw BrokenLeg::toss($this, 'FORBIDDEN');
		}
		return $theResult;
	}
	
	/**
	 * Return the director object.
	 * @return Director Returns the site director object.
	 */
	public function getDirector() {
		return $this->director;
	}
	
	/**
	 * Determine if the current logged in user has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|NULL $acctInfo - (optional) check specified account instead of
	 * currently logged in user.
	 */
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		return $this->getDirector()->isAllowed($aNamespace,$aPermission,$acctInfo);
	}

	/**
	 * Determine if there is even a user logged into the system or not.
	 * @return boolean Returns TRUE if no user is logged in.
	 */
	public function isGuest() {
		return $this->getDirector()->isGuest();
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
	 * @param mixed $aRelativeUrl - array of path segments OR a bunch of string parameters
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
		return $this->director->app_id;
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
		return $this->director->account_info->account_id;
	}
	
	/**
	 * If the menu being used supports highlighting, highlight the menu passed in.
	 * @param string $aMenuKey - menu key name as used in res/MenuInfo.
	 */
	public function setCurrentMenuKey($aMenuKey) {
		$this->director['current_menu_key'] = $aMenuKey;
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
