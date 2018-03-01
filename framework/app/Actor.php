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
use BitsTheater\models\Config;
use com\blackmoonit\exceptions\FourOhFourExit;
use com\blackmoonit\exceptions\SystemExit;
use com\blackmoonit\Strings;
use ReflectionClass;
use ReflectionMethod;
use BadMethodCallException;
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
	 * Session vars can be accessed like an array (ie. director[some_session_var]; )
	 * @var Director
	 */
	public $director = null;
	/**
	 * Config model used essentially like an array (ie. config[some_key]; )
	 * @var Config
	 */
	public $config = null;
	/**
	 * Scene interface used like properties (ie. scene->some_var; (which can be functions))
	 * @var MyScene
	 */
	public $scene = null;
	/**
	 * The action being performed.
	 * @var string
	 */
	protected $action = null;
	/**
	 * '_blank' will not render anything, NULL renders the view with the same name as the action
	 * being performed, any other value tried to render that view file.
	 * @var string
	 */
	protected $renderThisView = null;
	/**
	 * We need to disallow the public worker methods defined here; start with
	 * the Actor methods.
	 * @var @var array[string]boolean
	 */
	protected $myPublicMethodsAccessControl = array();
	
	/**
	 * Once created, set up the Actor object for use.
	 * @param Director $aDirector - the Director object.
	 * @param string $anAction - the method attempting to be called via URL.
	 */
	public function setup(Director $aDirector, $anAction) {
		$this->director = $aDirector;
		$this->action = $anAction;
		$this->setupMethodAccessControl();
		$this->scene = $this->createMyScene($anAction);
		if ($this->director->canConnectDb() && $this->director->isInstalled()) {
			$this->config = $this->director->getProp('Config');
		}
		$this->bHasBeenSetup = true;
	}

	/**
	 * Object is being destroyed, free any resources.
	 * @see \com\blackmoonit\AdamEve::cleanup()
	 */
	public function cleanup() {
		$this->director->returnProp($this->config);
		unset($this->director);
		unset($this->action);
		unset($this->scene);
		parent::cleanup();
	}
	
	/**
	 * If a URL specifies an actor, but not an action, peform this action.
	 * @param string $anAction - action specified by URL.
	 * @return string Return what action should be performed.
	 */
	static public function getDefaultAction($anAction=null) {
		return (!empty($anAction)) ? $anAction : static::DEFAULT_ACTION;
	}
	
	/**
	 * Default behavior is to prevent all public methods inherent in Actor class
	 * from being accessed via the URL. Static public methods of the descendant
	 * class will also be prevented access. Descendants overriding this method
	 * should call parent before adding to $this->myPublicMethodsAccessControl.
	 * @see Actor::$myPublicMethodsAccessControl
	 */
	protected function setupMethodAccessControl() {
		$rc = new ReflectionClass(__CLASS__);
		$myMethods = $rc->getMethods(ReflectionMethod::IS_PUBLIC);
		/* @var $theMethod ReflectionMethod */
		foreach ($myMethods as $theMethod) {
			$this->myPublicMethodsAccessControl[$theMethod->name] = false;
		}
		//now check for all public/static methods
		$rc = new ReflectionClass($this);
		$myMethods = $rc->getMethods(ReflectionMethod::IS_STATIC);
		foreach ($myMethods as $theMethod) {
			if ($theMethod->isPublic())
				$this->myPublicMethodsAccessControl[$theMethod->name] = false;
		}
	}
	
	/**
	 * When the object is first setup(), a scene is created along with it. Use
	 * this method to determine what is created. By default, a scene with the
	 * same simple name as the Actor will be used, if found, else just the
	 * base Scene class is used. Descendants may override this to create
	 * something specific.
	 * @param string $anAction - the method attempting to be called via URL.
	 * @return Scene Returns a newly created scene descendant.
	 */
	protected function createMyScene($anAction)
	{
		$theSceneClass = Director::getSceneClass($this->mySimpleClassName);
		if ( !class_exists($theSceneClass) )
			Strings::errorLog(__METHOD__.': cannot find Scene class: '.$theSceneClass);
		return new $theSceneClass($this, $anAction);
	}

	/**
	 * Once it has been determined that we wish to and are allowed to perform
	 * an action, this method actually does the work calling the method.
	 * @param string $aAction - method to be called.
	 * @param array $aQuery - params for the method to be called.
	 * @return string|null (OPTIONAL) Return a URL to redirect to.
	 */
	protected function performAct($aAction, $aQuery) {
		$theThingToCall = array($this, $aAction);
		if (is_callable($theThingToCall))
			return call_user_func_array($theThingToCall, $aQuery);
		else
			throw new FourOhFourExit(BITS_URL . '/' . $this->mySimpleClassName
					. '/' . $aAction . '/' . implode('/', $aQuery)
			);
	}

	/**
	 * The Director will call this method on the Actor class it determined
	 * from the URL to figure out what method should be executed and
	 * execute it.
	 * @param string $anAction - the method to execute.
	 * @param array $aQuery - params for the method to be called.
	 * @return boolean Return FALSE if a 404 should be thrown.
	 * @throws BrokenLeg
	 */
	public function perform($anAction, array $aQuery=array()) {
		try {
			$this->usherGreetAudience($anAction);
			if ($this->usherAudienceToSeat($anAction)) {
				$theResult = $this->performAct($anAction, $aQuery);
			}
		}
		catch (FourOhFourExit $e404) {
			return false;
		}
		catch (BrokenLeg $e) {
			if ($this->renderThisView==='results_as_json') {
				//API calls need to eat the exception and give a sane HTTP Response
				$e->setErrorResponse($this->scene);
			} else {
				//non-API calls need to bubble up the exception
				throw $e;
			}
		}
		if (empty($theResult))
			$this->renderView($this->renderThisView);
		else
			header('Location: '.$theResult);
		return true;
	}
	
	/**
	 * Once an action has been performed, we may need to render a view which
	 * might be a webpage or an API response object.
	 * @param string $anAction - action performed.
	 * @throws BadMethodCallException if the class was not instantiated correctly.
	 * @throws FourOhFourExit if the view file is not found.
	 */
	public function renderView($anAction=null) {
		if ($anAction=='_blank' || http_response_code()==204)
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
	 * Used for partial page renders so sections can be compartmentalized and/or
	 * reused by View designers.
	 * @param aViewName - renders app/view/%name%.php, defaults to currently running
	 *     action if name is empty.
	 * @return string Returns the contents of the output buffer.
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
	 * Restrict authorization to anti-CSRF mechanism.
	 * @param string $aAction - method name to be called.
	 */
	protected function usherGreetWithAntiCsrf($aAction) {
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

	/**
	 * Restrict authorization to require an auth header.
	 * @param string $aAction - method name to be called.
	 */
	protected function usherGreetWithNeedsAuth($aAction) {
		//auto-set view to json response
		$this->viewToRender('results_as_json');
		//we need to have a prefix that does the opposite of AJAJ
		//  and allow CORS, at the expense of always providing
		//  any auth if needed.
		$this->scene->bCheckOnlyHeadersForAuth = true;
	}

	/**
	 * Set us up to render a web page.
	 * @param string $aAction - method name to be called.
	 */
	protected function usherGreetWithPageRender($aAction) {
		//we are probably a page that needs rendering, remember it in case
		//  login needs to be forced and then lets us get back to our
		//  intended page.
		$theDirector = $this->getDirector();
		$theDirector['lastpagevisited'] = $theDirector['currpagevisited'];
		$theDirector['currpagevisited'] = REQUEST_URL;
	}

	/**
	 * Some methods may restrict us on how we can authorize.
	 * @param string $aAction - method name to be called.
	 */
	public function usherGreetAudience($aAction) {
		if (Strings::beginsWith($aAction, 'ajaj'))
			$this->usherGreetWithAntiCsrf($aAction);
		else if (Strings::beginsWith($aAction, 'api'))
			$this->usherGreetWithNeedsAuth($aAction);
		else if (Strings::beginsWith($aAction, 'ajax')) {
			//auto-set view to json response, yes, the X in AJAX means XML, but
			//  framework does not have a "default XML" view, yet.
			$this->viewToRender('results_as_json');
		}
		else {
			$this->usherGreetWithPageRender($aAction);
		}
	}
	
	/**
	 * Return TRUE if the specified action is allowed to be called.
	 * @param string $aAction - method name to be called.
	 * @return boolean Returns TRUE if method call is allowed.
	 */
	protected function usherCheckCsrfProtection($aAction) {
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
		if ($theResult)
			return true;
		else {
			$this->scene->addUserMsg($this->getRes('generic/msg_permission_denied'), MyScene::USER_MSG_ERROR);
			throw BrokenLeg::toss( $this, BrokenLeg::ACT_PERMISSION_DENIED );
		}
	}
	
	/**
	 * Return TRUE if the specified action is allowed to be called.
	 * @param string $aAction - method name to be called.
	 * @return boolean Returns TRUE if method call is allowed.
	 */
	public function usherAudienceToSeat($aAction) {
		$theResult = true;
		if (isset($this->myPublicMethodsAccessControl[$aAction]))
		{
			$theResult = $this->myPublicMethodsAccessControl[$aAction];
			if (!$theResult) $this->debugLog($this->myClassName.' denied Action "'.$aAction.'"');
		}
		if ($theResult)
		{
			//even guests may get to see some pages, ignore function result
			$this->getDirector()->admitAudience($this->scene);
		}
		if ($theResult && Strings::beginsWith($aAction, 'ajaj'))
		{
			$this->usherCheckCsrfProtection($aAction);
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
	 * Alternatively, you can pass each segment in as its own parameter.
	 * @param string $aName - The 'namespace/resource[/extras]' name to retrieve.
	 */
	public function getRes($aName) {
		return call_user_func_array(array($this->getDirector(), 'getRes'), func_get_args());
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

	/**
	 * Returns the APP_ID generated at website install time.
	 * This should be unique for a given web server so that cookie
	 * separation can occur between sites.
	 */
	public function getAppId() {
		return $this->director->app_id;
	}
	
	/**
	 * Returns the defined Home/Landing page of the website.
	 * @return string
	 */
	public function getHomePage() {
		return BITS_URL.'/'.configs\Settings::getLandingPage();
	}
	
	/**
	 * Generic exception for permission denied (403).
	 * @param string $aMsg - (OPTIONAL) message to return.
	 * @throws SystemExit
	 */
	public function throwPermissionDenied($aMsg='') {
		if ($aMsg==='') {
			$aMsg = getRes('generic/msg_permission_denied');
		}
		throw new SystemExit($aMsg, 403);
	}
	
	/**
	 * Return the logged in user's account_id.
	 * @var integer
	 */
	public function getMyAccountID() {
		if (!empty($this->director->account_info))
			return $this->director->account_info->account_id;
		else
			return 0;
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
	
	/**
	 * Gets a value from an incoming request, based on a value fetched from a
	 * URI segment, or a named field from the POST data. The caller of this
	 * function decides which is the "preferred" data source or "alternate" data
	 * source by the values sent in the parameters.
	 * @param string $aValue a possible value for the field, already fetched
	 *  from a specific source (like a URI segment); this will be checked first
	 * @param string $aField the name of a field in the POST data where the
	 *  value might be found, if not found in <code>$aValue</code>
	 * @param boolean $isRequired indicates whether to throw an exception if no
	 *  value can be found for the field in either data source; this defaults to
	 *  <code>true</code> (required by default)
	 * @return NULL|string the value that was found, preferring
	 *  <code>$aValue</code> but falling back to the POST data if needed; if no
	 *  field name is given, and no value is found, then NULL is returned
	 * @throws BrokenLeg (MISSING_ARGUMENT) if no value was found in either data
	 *  source, and the caller indicated that a result is required
	 * @since BitsTheater 3.5.2, 3.7.0
	 */
	protected function getRequestData( $aValue=null, $aField=null, $isRequired=true )
	{
		$theValue = $aValue ;
		if( empty($theValue) )
		{
			if( empty($aField) ) // Don't look for a substitute value.
				return null ;
			else if( ! empty( $this->scene->$aField ) )
				$theValue = $this->scene->$aField ;
			else if( $isRequired )
				throw BrokenLeg::toss( $this, BrokenLeg::ACT_MISSING_ARGUMENT, $aField ) ;
			else
				return null ;
		}
		return $theValue ;
	}
	
	/**
	 * Gets an entity ID from a URI segment or a POST data field.
	 * This is an alias of <code>getRequestData()</code> which preserves
	 * backward compatibility for anyone who has been using this older function
	 * name to fetch entity IDs from requests.
	 *
	 * @param string $aValue a possible value for the ID, already fetched from a
	 *  specific source (like a URI segment); this will be checked first
	 * @param string $aField the name of a field in the POST data where the ID
	 *  value might be found, if not found in <code>$aValue</code>
	 * @param boolean $isRequired indicates whether to throw an exception if no
	 *  value can be found for the ID in either data source; this defaults to
	 *  <code>true</code> (required by default)
	 * @return NULL|string the value that was found, preferring
	 *  <code>$aValue</code> but falling back to the POST data if needed; if no
	 *  field name is given, and no value is found, then NULL is returned
	 * @throws BrokenLeg (MISSING_ARGUMENT) if no value was found in either data
	 *  source, and the caller indicated that a result is required
	 * @see Actor::getRequestData()
	 * @since BitsTheater 3.7.0
	 */
	protected function getEntityID( $aValue=null, $aField=null, $isRequired=true )
	{ return $this->getRequestData( $aValue, $aField, $isRequired ) ; }

}//end class

}//end namespace
