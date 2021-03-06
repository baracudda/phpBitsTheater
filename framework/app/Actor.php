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
use BitsTheater\costumes\APIResponse;
use BitsTheater\costumes\IDirected;
use BitsTheater\Scene as MyScene;
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
	 * PHP Magic method to get properties that do not exist.
	 * @param string $aName - the property to get.
	 * @return mixed Returns what is needed.
	 */
	public function __get($aName)
	{
		if ($this->director->canConnectDb() && $this->director->isInstalled())
		{
			//support legacy property removed.
			if ($aName=='config')
			{ return $this->getProp('Config'); }
		}
	}
	
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
		$this->bHasBeenSetup = true;
	}

	/**
	 * Object is being destroyed, free any resources.
	 * @see \com\blackmoonit\AdamEve::cleanup()
	 */
	public function cleanup() {
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
	static public function getDefaultAction( $anAction=null )
	{ return ( !empty($anAction) ) ? $anAction : static::DEFAULT_ACTION; }
	
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

	/** @return MyScene Returns my scene object. */
	public function getMyScene()
	{ return $this->scene; }
	
	/**
	 * Should the endpoint being rendered be considered an API endpoint for
	 * returning the standard APIResponse/BrokenLeg results? Used by perform().
	 * @param string $aAction - the endpoint method name.
	 * @param string[] $aQuery - the endpoint parameters.
	 * @return boolean Returns TRUE if the endpoint should be considered one
	 *   for the purposes of returning an APIResponse.
	 */
	protected function isApiResult( $aAction, $aQuery )
	{
		return ( $this->renderThisView==='results_as_json' );
	}
	
	/**
	 * Director is trying to determine what Actor::method() to execute, it
	 * will call this method to determine if an action/endpoint by the URL
	 * is present and callable. Return the name of the method to call if so,
	 * otherwise toss a 404.
	 * @param string $aAction - action as encoded in the URL.
	 * @return string Returns the method name to call for the given action.
	 * @throws FourOhFourExit - if the action cannot be performed.
	 */
	static public function getMethodForAction( $aAction )
	{
		$myClass = get_called_class();
		//normalize URL action to method name format and ensure non-empty
		$theMethodName = static::getDefaultAction(
				Strings::getMethodName($aAction)
		);
		//see if method exists and is scope public
		try {
			$theMethod = new \ReflectionMethod($myClass, $theMethodName);
			if ( !$theMethod->isPublic() ) {
				throw (new FourOhFourExit($myClass . '::' . $aAction))
					->setContextMsg('action not accessible');
			}
		}
		catch ( \ReflectionException $rx ) {
			throw (new FourOhFourExit($myClass . '::' . $aAction))
				->setContextMsg('action not found');
		}
		//ensure a public scope method is not restricted for some reason
		if ( !static::isActionUrlAllowed($theMethodName) ) {
			throw (new FourOhFourExit($myClass . '::' . $aAction))
				->setContextMsg('action not a valid URL action');
		}
		//all good, return the actual method name to call.
		return $theMethodName;
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
		{ return $theThingToCall(...$aQuery); }
		else {
			$theUrlNotFound = BITS_URL . '/' . $this->mySimpleClassName
					. '/' . $aAction . '/' . implode('/', $aQuery);
			if ($this->getDirector()->isRunningUnderCLI() )
			{ print('Endpoint not found: ' . $theUrlNotFound); }
			throw new FourOhFourExit($theUrlNotFound);
		}
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
			if ( $this->isApiResult($anAction, $aQuery) ) {
				//API calls need to eat the exception and give a sane HTTP Response
				$e->setErrorResponse($this->scene);
			} else {
				//non-API calls need to bubble up the exception
				throw $e;
			}
		}
		catch (\Exception $x) {
			if ( $this->isApiResult($anAction, $aQuery) ) {
				//API calls need to eat the exception and give a sane HTTP Response
				BrokenLeg::tossException($this, $x)->setErrorResponse($this->scene);
			} else {
				//non-API calls need to bubble up the exception
				throw $x;
			}
		}
		if ( empty($theResult) ) {
			$theView = $this->viewToRender();
			if ( empty($theView) ) {
				$theView = $anAction;
			}
			//$this->debugLog(__METHOD__ . " theView=[{$theView}]"); //DEBUG
			$this->renderView($theView);
		}
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
		if ( is_file($myView) )
		{ include($myView); }
		else {
			$theAppView = $recite->getViewPath($recite->view_path.$anAction);
			if ( is_file($theAppView) )
			{ include($theAppView); }
			else {
				/* DEBUG
				$this->debugLog(__METHOD__ . ' ' . $this->mySimpleClassName
						. '->' . $anAction . ' NOT FOUND.'
				);
				$this->debugLog(__METHOD__ . ' ' . Strings::getStackTrace());
				*/
				throw new FourOhFourExit(str_replace(BITS_ROOT,'',$myView));
			}
		}
		$recite; $v; //no-op to avoid warning of not using var
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
	 * Set the view to render. If NULL or file not found, '_blank' is used.
	 * @param string $aViewName - the view file name.
	 */
	public function setView( $aViewFileName )
	{
		if ( isset($aViewFileName) && $aViewFileName != '_blank' ) {
			$v = $this->getMyScene();
			$theView = $v->getViewPath($v->actor_view_path . $aViewFileName);
			if ( !is_file($theView) ) {
				$theView = $v->getViewPath($v->view_path . $aViewFileName);
			}
			if ( is_file($theView) ) {
				return $this->viewToRender($aViewFileName);
			}
		}
		return $this->viewToRender('_blank');
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
		$recite; $v; //no-op to avoid warning of not using var
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
		$this->scene->bExplicitAuthRequired = true;
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
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see \BitsTheater\costumes\IDirected::isAllowed()
	 */
	public function isAllowed($aNamespace, $aPermission, $aAcctInfo=null) {
		return $this->getDirector()->isAllowed($aNamespace, $aPermission, $aAcctInfo);
	}
	
	/**
	 * {@inheritDoc}
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see \BitsTheater\costumes\IDirected::isGuest()
	 */
	public function isGuest() {
		return $this->getDirector()->isGuest();
	}
	
	/**
	 * {@inheritDoc}
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see \BitsTheater\costumes\IDirected::checkAllowed()
	 */
	public function checkAllowed($aNamespace, $aPermission, $aAcctInfo=null) {
		return $this->getDirector()->checkAllowed($aNamespace, $aPermission, $aAcctInfo);
	}
	
	/**
	 * {@inheritDoc}
	 * @return $this Returns $this for chaining.
	 * @see \BitsTheater\costumes\IDirected::checkPermission()
	 */
	public function checkPermission($aNamespace, $aPermission, $aAcctInfo=null)
	{
		$this->getDirector()->checkPermission($aNamespace, $aPermission, $aAcctInfo);
		return $this;
	}
	
	/**
	 * Return a Model object for a given org, creating it if necessary.
	 * @param string $aName - name of the model object.
	 * @param string $aOrgID - (optional) the org ID whose data we want.
	 * @return Model Returns the model object.
	 */
	public function getProp( $aName, $aOrgID=null )
	{ return $this->getDirector()->getProp($aName, $aOrgID); }
	
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
	 * Returns the relative URL for this site appended with additional path info.
	 * @param string[]|string $aRelativeURL - array of path segments
	 *   OR a bunch of string parameters equating to path segments.
	 * @return string Returns the relative path URL.
	 * @see Director::getSiteUrl()
	 */
	public function getSiteUrl($aRelativeURL='') {
		return call_user_func_array(array($this->getDirector(), 'getSiteUrl'), func_get_args());
	}
	
	/**
	 * Get the setting from the configuration model.
	 * @param string $aSetting - setting in form of "namespace/setting"
	 * @param string $aOrgID - (optional) the org ID whose data we want.
	 * @throws \Exception
	 */
	public function getConfigSetting( $aSetting, $aOrgID=null )
	{ return $this->getDirector()->getConfigSetting($aSetting, $aOrgID); }
	
	/**
	 * Get the current actor's relative URL and append more segments to it.
	 * @param string|string[] $aUrl - relative site path segment(s), if a string and the
	 * leading '/' is omitted, current Actor class name is pre-pended to $aUrl.
	 * @param array $aQuery - (optional) array of query key/values.
	 * @return string Returns the relative page url.
	 * @see Director::getSiteUrl()
	 */
	public function getMyUrl($aUrl='', array $aQuery=array()) {
		if (!empty($aUrl) && !is_array($aUrl) && !Strings::beginsWith($aUrl,'/')) {
			$theUrl = $this->director->getSiteUrl(strtolower($this->mySimpleClassName), $aUrl);
		} else
			$theUrl = $this->director->getSiteUrl($aUrl);
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
	public function getHomePage()
	{
		return $this->getDirector()->getSiteLandingPage();
	}
	
	/**
	 * Generic exception for permission denied (403).
	 * @param string $aMsg - (OPTIONAL) message to return.
	 * @throws SystemExit
	 */
	public function throwPermissionDenied( $aMsg=null )
	{
		if ( is_null($aMsg) )
		{ $aMsg = $this->getRes('generic/msg_permission_denied'); }
		throw new SystemExit($aMsg, 403);
	}
	
	/**
	 * Return the logged in user's account_id.
	 * @var integer
	 */
	public function getMyAccountID()
	{
		$theAcctInfo = $this->getDirector()->getMyAccountInfo();
		return ( !empty($theAcctInfo) ) ? $theAcctInfo->account_id : 0;
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
	public function getSiteMode()
	{ return $this->director->getSiteMode(); }
	
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
	 * @deprecated Use getRequestData() instead.
	 * @since BitsTheater 3.7.0
	 */
	protected function getEntityID( $aValue=null, $aField=null, $isRequired=true )
	{ return $this->getRequestData( $aValue, $aField, $isRequired ) ; }
	
	/**
	 * Helper method to easily set a 204 no content response.
	 * Alias for setNoContentResponse().
	 */
	public function setApiResultsAsNoContent()
	{ $this->scene->results = APIResponse::noContentResponse(); }
	
	/**
	 * Helper method to easily set a 204 no content response.
	 * Alias for setApiResultsAsNoContent().
	 */
	public function setNoContentResponse()
	{ $this->scene->results = APIResponse::noContentResponse(); }
	
	/**
	 * Helper method to easily set the successful results of an API endpoint.
	 * @param mixed $aResults - the result data you wish to return.
	 * @param integer $aRespCode (optional) an HTTP response code to be set; if
	 *  omitted, then the current response code will be retained
	 * @return APIResponse Returns the response object so you can work with it
	 *   if you have more to do.
	 */
	public function setApiResults( $aResults, $aRespCode=null )
	{
		$this->scene->results = APIResponse::resultsWithData($aResults, $aRespCode);
		return $this->scene->results;
	}
	
	/**
	 * Helper method for a descendant actor to easily get the current results
	 * of an API endpoint.
	 * @return APIResponse Returns the response object.
	 */
	public function getApiResults()
	{
		if ( $this->scene->results instanceof APIResponse ) {
			return $this->scene->results;
		}
		else {
			return null;
		}
	}
	
}//end class

}//end namespace
