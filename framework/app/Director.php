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
use com\blackmoonit\AdamEve as BaseDirector;
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\SiteSettings;
use BitsTheater\costumes\venue\Usher;
use BitsTheater\res\ResException;
use com\blackmoonit\Strings;
use com\blackmoonit\Arrays;
use com\blackmoonit\FileUtils;
use com\blackmoonit\exceptions\FourOhFourExit;
use ArrayAccess;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use Exception;
{//begin namespace

class Director extends BaseDirector
implements ArrayAccess, IDirected
{
	/**
	 * Normal website operation mode.
	 * @var string
	 */
	const SITE_MODE_NORMAL = 'normal';
	/**
	 * Refuse connections while the site is being worked on.
	 * @var string
	 */
	const SITE_MODE_MAINTENANCE = 'maintenance';
	/**
	 * Use local resources as much as possible (little/no net connection)
	 * @var string
	 */
	const SITE_MODE_DEMO = 'demo';
	
	/**
	 * The app ID found in the [cfg]Settings class.
	 * @var string
	 */
	public $app_id = __DIR__;
	
	/**
	 * Non-sensitive account info of the logged in user.
	 * @var AccountInfoCache
	 */
	public $account_info = null;
	/**
	 * Database connections to share with the models.
	 * @var DbConnInfo[]
	 */
	public $dbConnInfo = array();
	/**
	 * Cache the models created so we do not create 12 instances of any single model.
	 */
	protected $_propMaster = array();
	/**
	 * Resource manager class (@var may not apply during installation).
	 * @var res\ResI18N
	 */
	protected $_resManager = null;
	/**
	 * Cache of resource classes.
	 */
	protected $_resMaster = array();
	/**
	 * Cache of log filenames.
	 * @var string[]
	 */
	protected $_logFilenameCache = array();
	/** @var Usher The authorization costume used for permission checks. */
	protected $mChiefUsher = null;
	/**
	 * Cache of the config model in use.
	 * NOTE: If set to FALSE, then model is unavailable, use defined defaults.
	 * @var \BitsTheater\models\Config
	 */
	protected $dbConfig = null;
	
	/**
	 * Determine which Director to create for the job.
	 * @return Director
	 */
	static public function requisition()
	{
		$theAppDirectorName = WEBAPP_NAMESPACE . 'AppDirector' ;
		if( class_exists( $theAppDirectorName ) )
			return new $theAppDirectorName() ;
		else
			return new Director() ;
	}
	
	/**
	 * Check for Magic Quotes and remove them.
	 */
	static public function removeMagicQuotes() {
		if ( get_magic_quotes_gpc() )
		{
			Strings::stripSlashesDeep($_GET);
			Strings::stripSlashesDeep($_POST);
			Strings::stripSlashesDeep($_COOKIE);
		}
	}

	/**
	 * Check register globals and remove them since they are copies of the
	 * PHP global vars and are security risks to PHP Injection attacks.
	 */
	static public function unregisterGlobals() {
		if (ini_get('register_globals'))
		{
			$theVars = array( '_SESSION', '_POST', '_GET', '_COOKIE',
					'_REQUEST', '_SERVER', '_ENV', '_FILES' );
			foreach ($theVars as $theVarName) {
				foreach ($GLOBALS[$theVarName] as $key => $var) {
					if ($var === $GLOBALS[$key]) {
						unset($GLOBALS[$key]);
					}
				}
			}
		}
	}
	
	/**
	 * debugLog() and errorLog() can have a prefix so each website will "stand out"
	 * in the server logs; typically set to some variation of VIRTUAL_HOST_NAME.
	 * @see Strings::debugPrefix()
	 * @see Strings::errorPrefix()
	 */
	static public function setupLogPrefix()
	{
		if (defined('VIRTUAL_HOST_NAME') && VIRTUAL_HOST_NAME)
		{
			Strings::debugPrefix( '['.VIRTUAL_HOST_NAME.'-dbg] ' );
			Strings::errorPrefix( '['.VIRTUAL_HOST_NAME.'-err] ' );
		}
	}

	/**
	 * Initialization method called during class construction.
	 */
	public function setup() {
		static::setupLogPrefix();
		static::removeMagicQuotes();
		static::unregisterGlobals();
		register_shutdown_function(array($this, 'onShutdown'));
		try {
			if ( !$this->check_session_start() ) {
				$this->regenerateSession();
			}
		} catch ( \Exception $x ) {
			$this->logErrors('Exception starting session, will regenerate: ', $x->getMessage());
			$this->regenerateSession();
		}
		
		//if we are installing the website, check virtual-host-name-based session global
		$theSessionGlobalVarName = 'VH_'.VIRTUAL_HOST_NAME.'_played';
		//APP_ID is important since it is used for creating unique session variables
		if ($this->isInstalled()) {
			//website is installed, always get app_id from the Settings class.
			$theSettingsClass = $this->getSiteSettingsClass();
			$this->app_id = $theSettingsClass::getAppId();
		} else if (!empty($_SESSION[$theSessionGlobalVarName])) {
			//website is being installed, grab app_id from session global.
			$this->app_id = $_SESSION[$theSessionGlobalVarName];
		} else {
			//website is initiating install, create a new app_id and place it in session global.
			$this->app_id = Strings::createUUID();
			$_SESSION[$theSessionGlobalVarName] = $this->app_id;
		}
		$this['played'] = $this->app_id; //app_id -> play_id -> "played"
		
		$this->bHasBeenSetup = true;
	}

	/**
	 * When this class gets destroyed, this method lets us clean up held resources.
	 * @see \com\blackmoonit\AdamEve::cleanup()
	 */
	public function cleanup() {
		unset($this->account_info);
		//destroy all cached models
		array_walk($this->_propMaster, function(&$n) {$n['model'] = null;} );
		unset($this->_propMaster);
		//disconnect dbs
		array_walk($this->dbConnInfo, function(&$dbci) {$dbci->disconnect();} );
		unset($this->dbConnInfo);
		//free all resources
		$this->freeRes();
		//call parent
		parent::cleanup();
	}
	
	/**
	 * After PHP script is finished running, check to see if PHP crashed and
	 * then try to report why.
	 */
	public function onShutdown() {
		$err = error_get_last();
		if (!empty($err)) {
			Strings::errorLog( __METHOD__ . ' ' . Strings::debugStr($err) ) ;
// Uncomment the following 6 lines if you need more information.
//			Strings::debugLog('OOM?: last 3 known new AdamEve-based classes:'
//					.(static::$lastClassLoaded1 ? ' '. static::$lastClassLoaded1->myClassName : '')
//					.(static::$lastClassLoaded2 ? ', '.static::$lastClassLoaded2->myClassName : '')
//					.(static::$lastClassLoaded3 ? ', '.static::$lastClassLoaded3->myClassName : '')
//			);
//			Strings::debugLog(Strings::debugStr(static::$lastClassLoaded1));
		}
	}
	
	//----- methods required for various IMPLEMENTS interfaces
	//NOTE: $this['key'] works for simple types, but not arrays.  Avoid arrays!
	public function offsetSet($aOffset, $aValue) {
		$_SESSION[$this->app_id][$aOffset] = $aValue;
	}

	public function offsetExists($aOffset) {
		return isset($_SESSION[$this->app_id][$aOffset]);
	}

	public function offsetUnset($aOffset) {
		unset($_SESSION[$this->app_id][$aOffset]);
	}

	public function offsetGet($aOffset) {
		return isset($_SESSION[$this->app_id][$aOffset]) ? $_SESSION[$this->app_id][$aOffset] : null;
	}
	
	//----- IMPLEMENTS handled, get on with being a Director below -----

	/**
	 * PHP's session_start() may be vulnerable to a modified cookie attack.
	 * @see http://stackoverflow.com/questions/3185779/
	 * @return boolean return TRUE if a session was successfully started
	 */
	protected function check_session_start() {
		ini_set('session.cookie_httponly', 1); //helps prevents cookie modification (browser dependent)
		$theName = session_name();
		if (!empty($_COOKIE[$theName])) {
			$theId = $_COOKIE[$theName];
		} else if (!empty($_GET[$theName])) {
			$theId = $_GET[$theName];
		} else {
			$theId = session_id();
		}
		if (!empty($theId) && preg_match('/^[a-zA-Z0-9,\-]{1,128}$/',$theId)) {
			return $this->sessionStart();
		}
		return false;
	}
	
	/**
	 * Detect a destroyed session and wonky network conditions to either
	 * replace or repair the current session, if need be.
	 * Per PHP documentation:<br>
	 * Warning: Current session_regenerate_id does not handle unstable network
	 * well. e.g. Mobile and WiFi network. Therefore, you may experience lost
	 * session by calling session_regenerate_id. You should not destroy the
	 * old session data immediately, but should use destroy time-stamp and
	 * control access to old session ID. Otherwise, concurrent access to page
	 * may result in inconsistent state, or you may have lost session, or it
	 * may cause client (browser) side race condition and may create many
	 * session ID needlessly. Immediate session data deletion disables session
	 * hijack attack detection and prevention also.
	 * @return boolean Returns the status of session_start.
	 */
	protected function sessionStart()
	{
		$theResult = session_start();
		if ( isset($_SESSION['destroyed_ts']) ) {
			if ( $_SESSION['destroyed_ts'] < time() ) {
				//This could be an attack or due to an unstable network.
				//Remove all authentication status of this users session.
				$this->logout();
				throw BrokenLeg::toss($this, BrokenLeg::ACT_SERVICE_UNAVAILABLE);
			}
			if ( isset($_SESSION['new_session_id']) ) {
				//Not fully expired yet. Could be lost cookie by an unstable
				//  network. Try again to set proper session ID cookie.
				session_commit();
				session_id($_SESSION['new_session_id']);
				// New session ID should exist
				$theResult = session_start();
			}
		}
		return $theResult;
	}
	
	/**
	 * Follow PHP documentation and handle regenerating a session ourselves.
	 * Per PHP documentation:<br>
	 * Warning: Current session_regenerate_id does not handle unstable network
	 * well. e.g. Mobile and WiFi network. Therefore, you may experience lost
	 * session by calling session_regenerate_id. You should not destroy the
	 * old session data immediately, but should use destroy time-stamp and
	 * control access to old session ID. Otherwise, concurrent access to page
	 * may result in inconsistent state, or you may have lost session, or it
	 * may cause client (browser) side race condition and may create many
	 * session ID needlessly. Immediate session data deletion disables session
	 * hijack attack detection and prevention also.
	 */
	public function regenerateSession()
	{
		//A new_session_id is required to set proper session ID
		//  when session ID is not set due to unstable network.
		$theNewID = function_exists('session_create_id') ? session_create_id() : uniqid();
		$_SESSION['new_session_id'] = $theNewID;
		//Set destroyed error state timestamp to 1 min from now.
		$_SESSION['destroyed_ts'] = time()+60;
		//This can be used by forms to prevent cross-site forgery attempts
		$_SESSION['nonce'] = bin2hex(openssl_random_pseudo_bytes(32));
		//Create new session without destroying the old one.
		session_regenerate_id(false);
		//Grab current session ID and flush the memory cache.
		$theCurrID = session_id();
		session_write_close();
		//Set the ID to the new one, and start it back up again
		if ( !empty($theCurrID) )
		{ session_id($theCurrID); }
		else //occationally get an empty ID, cannot start a session with it.
		{ session_id($theNewID); }
		session_start();
		//Do not want this session to expire, yet.
		unset($_SESSION['destroyed_ts']);
		unset($_SESSION['new_session_id']);
	}

	public function isInstalled() {
		return class_exists(BITS_NAMESPACE_CFGS.'Settings');
	}

	public function canCheckTickets() {
		return $this->canConnectDb() && class_exists(BITS_NAMESPACE_MODELS.'Auth');
	}
	
	public function canConnectDb($aDbConnName='webapp') {
		return $this->getDbConnInfo($aDbConnName)->canAttemptConnectDb();
	}
	
	public function canGetRes() {
		return class_exists(BITS_NAMESPACE_CFGS.'I18N');
	}
	
	/**
	 * Return the director object.
	 * @return Director Returns the site director object.
	 */
	public function getDirector() {
		return $this;
	}
	
	/**
	 * Since the purpose of API virtual actor is to "redirect" the URL to the
	 * actual actor/method being referenced, construct and return the list
	 * of possible methods being called.
	 * @param string $aPossibleAction - the action requested.
	 * @return string[] Returns the list of possible matches to query.
	 */
	protected function getPossibleApiMethodsList($aPossibleAction)
	{
		return array(
				Strings::getMethodName($aPossibleAction),
				Strings::getMethodName('ajaj_' . $aPossibleAction),
				Strings::getMethodName('api_' . $aPossibleAction),
				Strings::getMethodName('ajax_' . $aPossibleAction),
		);
	}
	
	/**
	 * Route the URL requested to the approprate actor. However, this is a
	 * special case to avoid requiring methods to specify ajaj|api|ajax prefix
	 * and will determine which method to call by working through a list of
	 * possible prefixes rather than exact matching.
	 * @param string $aUrlPath - the URL path segments to act upon.
	 * @throws FourOhFourExit - 404 if $aUrlPath is not found.
	 */
	protected function routeApiRequest( $aUrlPath )
	{
		//$this->logStuff(__METHOD__, ' rerouting to ', $aUrlPath);//DEBUG
		if ( empty($aUrlPath) ) {
			throw (new FourOhFourExit($this->getSiteUrl($aUrlPath)))
				->setContextMsg('actor not found');
		}
		$thePathSegments = explode('/', $aUrlPath);
		$theActorName = array_shift($thePathSegments);
		$theActorClass = $this::getActorClass($theActorName);
		if ( !class_exists($theActorClass) ) {
			throw (new FourOhFourExit($this->getSiteUrl($aUrlPath)))
				->setContextMsg('actor not found');
		}
		if ( empty($thePathSegments) ) {
			throw (new FourOhFourExit($this->getSiteUrl($aUrlPath)))
				->setContextMsg('action not found');
		}
		$thePossibleMethodsList = $this->getPossibleApiMethodsList(
				array_shift($thePathSegments)
		);
		$theParamSegments = trim(implode('/', $thePathSegments), '/');
		$theFailCount = 0;
		//$this->logStuff(__METHOD__, ' possible routes: ', $theActorClass, '::', $thePossibleMethodsList);//DEBUG
		foreach($thePossibleMethodsList as $thePossibleMethod) {
			try {
				$thePossibleUrl = $theActorName.'/'.$thePossibleMethod.'/'.$theParamSegments;
				//$this->logStuff(__METHOD__, ' trying url=', $thePossibleUrl);//DEBUG
				$this->raiseCurtain($thePossibleUrl);
				break; //if we did not throw an exception, our job is done.
			}
			catch ( FourOhFourExit $fofx ) {
				$theFailCount += 1;
			}
		}
		//$this->logStuff(__METHOD__, ' fails=', $theFailCount, ' list=', count($thePossibleMethodsList) );//DEBUG
		//if we fail to execute any of our possibilies, toss 404 error.
		if ( $theFailCount>=count($thePossibleMethodsList) )
		{ throw new FourOhFourExit($this->getSiteUrl($aUrlPath)); }
	}
	
	
	/**
	 * Route the URL requested to the approprate actor.
	 * @param string $aUrlPath - the URL path segments to act upon.
	 * @throws FourOhFourExit - 404 if $aUrlPath is not found.
	 */
	public function routeRequest( $aUrlPath )
	{
		//$this->logStuff('[TRACE] URL=[', $aUrlPath, ']');
		//if ( $aUrlPath=='phpinfo' ) { print(phpinfo()); return; } //DEBUG
		if ( empty($aUrlPath) ) {
			if ( $this->isInstalled() ) {
				//if website has been installed, redirect to landing page
				$theSettingsClass = $this->getSiteSettingsClass();
				header('Location: ' . $theSettingsClass::getLandingPage());
			}
			//otherwise if we have an Install actor, open with install wizard
			else if ( class_exists($this->getActorClass('Install')) ) {
				$aUrlPath = 'install';
			}
		}
		if ( !empty($aUrlPath) ) try {
			$this->raiseCurtain($aUrlPath);
		}
		catch (FourOhFourExit $fofx)
		{
			//if 404, see if we should try a different parse of the URL
			$thePathSegments = explode('/', $aUrlPath);
			switch ( $thePathSegments[0] ) {
				case 'api': try {
					$this->routeApiRequest(Strings::strstr_after($aUrlPath, 'api/'));
					break; //found an alternative that worked, do not toss 404 error.
				}
				catch ( FourOhFourExit $fofx ) {
					//let fall through to default behavior
				}
				default:
					//$this->logStuff(__METHOD__, ' ', $fofx); //DEBUG
					if ( $this->getDirector()->isRunningUnderCLI() )
					{ print('Endpoint not found: ' . $theUrlNotFound); }
					else
					{ throw new FourOhFourExit($this->getSiteUrl($aUrlPath)); }
			}//switch
		}
	}
	
	
	//===========================================================
	//=                     Actor methods                       =
	//===========================================================
	/**
	 * Given the simple actor name, determine the full namespace path.
	 * @param string $anActorName - Actor name typically from the URL.
	 * @return string Returns the fully qualified namespace\actor.
	 */
	static public function getActorClass($anActorName) {
		$theActorName = Strings::getClassName($anActorName);
		$theActorClass = BITS_NAMESPACE_ACTORS.$theActorName;
		if (!class_exists($theActorClass)) {
			$theActorClass = WEBAPP_NAMESPACE.'actors\\'.$theActorName;
		}
		return $theActorClass;
	}
	
	/**
	 * Determine what actor::method(args) to call based on the URL.
	 * Render the defined view for the given Actor::method().
	 * @param string $aUrlPath - the non-empty URL path segments to parse.
	 * @throws FourOhFourExit if URL does not refer to a valid endpoint.
	 */
	public function raiseCurtain( $aUrlPath )
	{
		if ( empty($aUrlPath) ) {
			throw (new FourOhFourExit($this->getSiteUrl($aUrlPath)))
				->setContextMsg('actor not found');
		}
		$thePathSegments = explode('/', $aUrlPath);
		$theActorClass = $this::getActorClass(array_shift($thePathSegments));
		if ( !class_exists($theActorClass) ) {
			throw (new FourOhFourExit($this->getSiteUrl($aUrlPath)))
				->setContextMsg('actor not found');
		}
		$theAction = array_shift($thePathSegments);
		$theMethodName = $theActorClass::getMethodForAction($theAction);
		//actor and action exist as public method, call it!
		$theActor = new $theActorClass($this, $theAction);
		if ( !$theActor->perform($theMethodName, $thePathSegments) ) {
			throw (new FourOhFourExit($this->getSiteUrl($aUrlPath)))
				->setContextMsg('method returned false');
		}
	}
	
	/**
	 * Actors might need to call methods from other actors.
	 * @param Scene $aScene - the Scene being used by the calling Actor.
	 * @param string $anActorName - the simple actor name being called upon.
	 * @param string $anAction - the method being called.
	 * @param mixed $_ - (optional additional params, denotes 1..n params)
	 * @return string Returns the contents of the output buffer.
	 */
	public function cue($aScene, $anActorName, $anAction, $_=null) {
		$theActorClass = static::getActorClass($anActorName);
		//Strings::debugLog('rC: class='.$theActorClass.', exist?='.class_exists($theActorClass));
		if (class_exists($theActorClass)) {
			$theAction = $theActorClass::getDefaultAction($anAction);
			try {
				$theMethod = new ReflectionMethod($theActorClass,$theAction);
				//if no exception, instantiate the class and call the method
				$theActor = new $theActorClass($this,$theAction);
				$theMethod->setAccessible(true); //protected from direct "raiseCurtain" calls, but ok for cue().
				
				$args = func_get_args();
				//remove first 3 params as they are used to call the method, rest are passed in as args
				array_shift($args);
				array_shift($args);
				array_shift($args);
				//append the scene of our caller as last param in case called method wants it
				array_push($args,$aScene);
				$theResult = $theMethod->invokeArgs($theActor,$args);
				
				//$this->debugLog(__METHOD__.' actorClass="'.$theActorClass.'", renderThisView="'.$theActor->viewToRender().'"');
				if (empty($theResult)) {
					$theView = $theActor->viewToRender();
					if (empty($theView))
						$theView = $anAction;
					//$this->debugLog(__METHOD__.' theView="'.$theView.'"');
					$s = $theActor->renderFragment($theView);
					//$this->debugLog(__METHOD__.' s="'.$s.'"');
					unset($theActor);
					return $s;
				} else {
					header('Location: '.$theResult);
				}
			} catch (ReflectionException $e) {
				$this->errorLog($e->getMessage());
				//no method to call, just ignore it
			}
		}
	}
	
	
	//===========================================================
	//=                   Model methods                         =
	//===========================================================
	/**
	 * Returns the correct namespace associated with the model name/ReflectionClass.
	 * @param string/ReflectionClass $aModelName - model name as string or
	 * ReflectionClass of model in question.
	 * @return string Returns the model class name with correct namespace.
	 */
	static public function getModelClass($aModelName) {
		if (is_string($aModelName)) {
			$theModelSegPos = strpos($aModelName, 'models\\');
			if (class_exists($aModelName) && !empty($theModelSegPos))
				return $aModelName;
			$theModelName = Strings::getClassName($aModelName);
			$theModelClass = BITS_NAMESPACE_MODELS.$theModelName;
			if (!class_exists($theModelClass)) {
				$theModelClass = WEBAPP_NAMESPACE.'models\\'.$theModelName;
			}
		} elseif ($aModelName instanceof ReflectionClass) {
			$theModelClass = $aModelName->getName();
		}
		return $theModelClass;
	}
	
	/**
	 * Cache the DB connection information for a specific connection name.
	 * Any Models already connected to it will be re-connected to the new info.
	 * @param DbConnInfo $aDbConnInfo - the DB connection info to cache.
	 */
	public function setDbConnInfo(DbConnInfo $aDbConnInfo)
	{
		if ( !empty($this->dbConnInfo[$aDbConnInfo->dbConnName]) ) {
			$theOldDbConn = $this->dbConnInfo[$aDbConnInfo->dbConnName];
		}
		$this->dbConnInfo[$aDbConnInfo->dbConnName] = $aDbConnInfo;
		if ( !empty($this->_propMaster) ) {
			foreach($this->_propMaster as $theModelRefCountCell) {
				/* @var $theModel Model */
				$theModel = $theModelRefCountCell['model'];
				if ( empty($theModel) ) continue; //trivial short-circuit
				if ($theModel->dbConnName == $aDbConnInfo->dbConnName)
				{ $theModel->connect($aDbConnInfo->dbConnName); }
			}
		}
		if ( !empty($theOldDbConn) )
		{ $theOldDbConn->disconnect(); }
	}
	
	/**
	 * Retrieve the connection information for a specific connection name.
	 * @param string $aDbConnName - (optional) dbconn name, default="webapp".
	 * @return DbConnInfo
	 */
	public function getDbConnInfo($aDbConnName='webapp')
	{
		if ( empty($this->dbConnInfo[$aDbConnName]) )
		{ $this->setDbConnInfo(new DbConnInfo($aDbConnName)); }
		return $this->dbConnInfo[$aDbConnName];
	}
	
	/**
	 * Retrieve the singleton Model object for a given model class.
	 * @param string $aModelClass - the model class to retrieve.
	 * @throws Exception when the model fails to connect or is not found.
	 * @return Model Returns the model class requested.
	 */
	public function getModel($aModelClass) {
		$theModelClass = static::getModelClass($aModelClass);
		if (class_exists($theModelClass)) {
			if (empty($this->_propMaster[$theModelClass])) {
				//ensure we have a non-empty reference in case of a dbconn exception
				//  so that nested infinite loops can be avoided
				$this->_propMaster[$theModelClass] = array(
						'model' => null,
						'ref_count' => 0,
				);
				try
				{ $this->_propMaster[$theModelClass]['model'] = new $theModelClass($this); }
				catch (Exception $e) {
					$this->errorLog(__METHOD__.' '.$e->getMessage());
					throw $e ;
				}
			}
			$this->_propMaster[$theModelClass]['ref_count'] += 1;
			return $this->_propMaster[$theModelClass]['model'];
		} else {
			$theError = __METHOD__.' cannot find Model class: '.$theModelClass ;
			$this->errorLog( $theError ) ;
			throw new Exception( $theError ) ;
		}
	}
	
	public function unsetModel($aModel) {
		if (isset($aModel)) {
			$theModelClass = get_class($aModel);
			if (isset($this->_propMaster[$theModelClass])) {
				$this->_propMaster[$theModelClass]['ref_count'] -= 1;
				if ($this->_propMaster[$theModelClass]['ref_count']<1) {
					$this->_propMaster[$theModelClass]['model'] = null;
					unset($this->_propMaster[$theModelClass]);
				}
			}
			$aModel = null;
		}
	}
	
	/**
	 * Return a Model object, creating it if necessary.
	 * @param string $aName - name of the model object.
	 * @return Model Returns the model object.
	 */
	public function getProp($aModelClass) {
		return $this->getModel($aModelClass);
	}
	
	/**
	 * Let the system know you do not need a Model anymore so it
	 * can close the database connection as soon as possible.
	 * @param Model $aProp - the Model object to be returned to the prop closet.
	 */
	public function returnProp($aModel) {
		$this->unsetModel($aModel);
	}

	/**
	 * Calls methodName for every model class that matches the class patern and returns an array of results.
	 * @param string $aModelClassPattern - NULL for all non-abstract models, else a result from getModelClassPattern.
	 * @param string $aMethodName - method to call.
	 * @param mixed $args - arguments to pass to the method to call.
	 * @return array Returns an array of key(model class name) => value(function result);
	 * @see Model::foreachModel()
	 */
	public function foreachModel($aModelClassPattern, $aMethodName, $args=null) {
		return Model::foreachModel($this, $aModelClassPattern, $aMethodName, $args);
	}
	
	
	//===========================================================
	//=                  Scene methods                          =
	//===========================================================
	
	static public function getSceneClass($anActorName) {
		$theSceneClass = BITS_NAMESPACE_SCENES.$anActorName;
		if (!class_exists($theSceneClass)) {
			$theSceneClass = WEBAPP_NAMESPACE.'scenes\\'.$anActorName;
		}
		if (!class_exists($theSceneClass))
			$theSceneClass = BITS_NAMESPACE.'Scene';
		return $theSceneClass;
	}
	
	
	//===========================================================
	//=               RESOURCE management                       =
	//===========================================================
	
	public function getResManager() {
		return $this->_resManager;
	}
	
	/**
	 * Get a resource based on its combined 'namespace/resource_name'.
	 * Alternatively, you can pass each segment in as its own parameter.
	 * @param string $aName - The 'namespace/resource[/extras]' name to retrieve.
	 */
	public function getRes($aResName) {
		if (empty($this->_resManager)) {
			if ($this->canGetRes()) {
				//TODO create a user config for "en/US" and pass that into the constructor. (lang/region)
				$theSiteLangClass = $this->getSiteLanguageClass();
				$this->_resManager = new $theSiteLangClass();
			} else {
				$theInstallResMgr = BITS_NAMESPACE_RES.'ResI18N';
				$this->_resManager = new $theInstallResMgr('en/US');
			}
		}
		if ( func_num_args()>1 )
			$theResUri = func_get_args();
		else //explode on "\" or "/"
			$theResUri = explode('/',str_replace('\\','/',$aResName));
		//$this->debugPrint($this->debugStr($theResUri));
		if (count($theResUri)>=2) {
			$theResClassName = Strings::getClassName(array_shift($theResUri));
			$theRes = array_shift($theResUri);
		} else {
			$theResClassName = 'Resources';
			$theRes = array_shift($theResUri);
		}
		try {
			$theResClass = $this->_resManager->includeResClass($theResClassName);
			//$this->debugLog('[res] name='.$theResClassName.', class='.$this->debugStr($theResClass).', res='.$theRes.', args='.$this->debugStr($theResUri));
			if (!empty($theResUri))
				return $this->loadRes($theResClass,$theRes,$theResUri);
			else
				return $this->loadRes($theResClass,$theRes);
		} catch (ResException $re) {
			if ($this->_resManager->isUsingDefault()) {
				throw $re;
			} else {
				$theResClass = $this->_resManager->includeDefaultResClass($theResClassName);
				if (!empty($theResUri))
					return $this->loadRes($theResClass,$theRes,$theResUri);
				else
					return $this->loadRes($theResClass,$theRes);
			}
		}
	}
	
	protected function loadRes($aResClass, $aRes, $args=null) {
		if (empty($this->_resMaster[$aResClass])) {
			$this->_resMaster[$aResClass] = new $aResClass($this);
		}
		$resObj = $this->_resMaster[$aResClass];
		//$this->debugLog(__METHOD__.' resObj='.$this->debugStr($resObj).' method='.$aRes.' is_callable='.((is_callable(array($resObj,$aRes)))?'true':'false'));
		if (is_callable(array($resObj,$aRes))) {
			//$this->debugLog(__METHOD__.' resObj='.$this->debugStr($resObj).' cls='.$aResClass.' method='.$aRes.' args='.$this->debugStr($args));
			try {
				return call_user_func_array(array($resObj,$aRes),(!is_null($args)?$args:array()));
			} catch (Exception $e) {
				throw new ResException($this->_resManager,$aRes,$aResClass,$args,$e);
			}
		} else {
			if (isset($resObj->{$aRes})) {
				$theValue = $resObj->{$aRes};
				if (isset($args)) {
					//handle nested arrays until we get to a string
					while (is_array($theValue)) {
						//$this->debugLog(__METHOD__.' v='.$this->debugStr($theValue).' a='.$this->debugStr($args));
						$theValue = $theValue[array_shift($args)];
					}
					//if we still have args left once we get to a string, format it
					if (!empty($args)) {
						//$this->debugPrint('b: '.$theValue.Strings::debugStr($args));
						try {
							$theValue = call_user_func_array(array('com\blackmoonit\Strings','format'),
									Arrays::array_prepend($args,$theValue)
							);
						} catch (Exception $e) {
							throw new ResException($this->_resManager,$aRes,$aResClass,$args,$e);
						}
					}
				}
				return $theValue;
			} else {
				throw new ResException($this->_resManager,(isset($resObj) ? $aResClass.'/' : '').$aRes);
			}
		}
	}
	
	public function freeRes() {
		array_walk($this->_resMaster, function(&$n) {$n = null;} );
	}

	//===========================================================
	//=                   LOGIN INFO                            =
	//===========================================================
	
	/**
	 * See if we can validate the api/page request with an account.
	 * @param object $aScene - the Scene object associated with an Actor.
	 * @return boolean Returns TRUE if admitted.
	 */
	public function admitAudience( $aScene )
	{
		if ( $this->canCheckTickets() ) {
			$this->mChiefUsher = Usher::withContext($this);
			if ( !empty($this->mChiefUsher) ) {
				return $this->mChiefUsher->checkTicket($aScene);
			} else {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Determine if the current logged in user has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|NULL $aAcctInfo - (optional) check specified account instead of
	 *   currently logged in user.
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 */
	public function isAllowed($aNamespace, $aPermission, $aAcctInfo=null)
	{
		if ( !empty($this->mChiefUsher) ) {
			$theAcctInfo = ( empty($aAcctInfo) ) ? $this->getMyAccountInfo()
				: $this->mChiefUsher->createAccountInfoObj($aAcctInfo);
			return $this->mChiefUsher->isPermissionAllowed($aNamespace,
					$aPermission, $theAcctInfo
			);
		}
		return false;
	}
	
	/**
	 * Determine if there is even a user logged into the system or not.
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 */
	public function isGuest()
	{
		if ( !empty($this->mChiefUsher) ) {
			return $this->mChiefUsher->isGuestAccount(
					$this->getMyAccountInfo()
			);
		}
		else
		{ return true; }
	}
	
	/**
	 * {@inheritDoc}
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see \BitsTheater\costumes\IDirected::checkAllowed()
	 */
	public function checkAllowed($aNamespace, $aPermission, $aAcctInfo=null)
	{
		if ( $this->isAllowed($aNamespace, $aPermission, $aAcctInfo) )
			return true;
		else
			throw BrokenLeg::toss( $this, BrokenLeg::ACT_PERMISSION_DENIED );
	}
	
	/**
	 * {@inheritDoc}
	 * @return $this Returns $this for chaining.
	 * @see \BitsTheater\costumes\IDirected::checkPermission()
	 */
	public function checkPermission($aNamespace, $aPermission, $aAcctInfo=null)
	{
		if ( ! $this->isAllowed($aNamespace, $aPermission, $aAcctInfo) )
			throw BrokenLeg::toss( $this, BrokenLeg::ACT_PERMISSION_DENIED );
		return $this;
	}
	
	/**
	 * Convenience method for checking if any of a set of permissions is allowed.
	 * @param string[] $aPermList - array of "namespace/permission" strings to check.
	 * @param array|NULL $acctInfo - (optional) check specified account instead of
	 *     currently logged in user.
	 * @return $this Returns $this for chaining purposes.
	 * @throws BrokenLeg 403 if not allowed and logged in or 401 if not allowed and guest.
	 */
	public function checkIfAnyAllowed($aPermList, $aAcctInfo=null)
	{
		foreach ($aPermList as $thePerm) {
			list($theNamespace, $thePermission) = explode('/', $thePerm, 2);
			if ( $this->isAllowed($theNamespace, $thePermission, $aAcctInfo) )
				return true;
		}
		throw BrokenLeg::toss( $this, BrokenLeg::ACT_PERMISSION_DENIED );
	}
	
	/**
	 * Rips up a user's ticket and unsets the account info cache.
	 * @return string Returns the site URL.
	 */
	public function logout() {
		if ( !$this->isGuest() && isset($this->mChiefUsher) ) {
			$this->mChiefUsher->ripTicket();
			unset($this->account_info);
		}
		return BITS_URL;
	}
	
	/**
	 * Given an array of strings, put them together to create a URL path.
	 * @param string[] $aArgs - the args to be strung together.
	 * @return string Return the imploded array as a string; non-empty
	 *   results will always begin with a slash '/'.
	 */
	protected function convertArgsToPath( $aArgs )
	{
		if ( empty($aArgs) ) return; //trivial
		$theResult = '';
		//do not want to use implode() as params might be ('/foo/bar', '#blah')
		foreach ($aArgs as $pathPart) {
			if ( !empty($pathPart) && ($pathPart[0] !== '/') ) {
				$theResult .= '/';
			}
			$theResult .= $pathPart;
		}
		return $theResult;
	}
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param string[]|string $aRelativeURL - array of path segments
	 *   OR a bunch of string parameters equating to path segments.
	 * @return string Returns the site relative path URL.
	 * @see Director::getFullUrl()
	 */
	public function getSiteUrl($aRelativeURL='') {
		$theResult = BITS_URL;
		if ( !empty($aRelativeURL) ) {
			$theArgs = ( is_array($aRelativeURL) ) ? $aRelativeURL : func_get_args();
			$theResult .= $this->convertArgsToPath($theArgs);
		}
		return $theResult;
	}
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param string[]|string $aRelativeURL - array of path segments
	 *   OR a bunch of string parameters equating to path segments.
	 * @return string - returns the http scheme + site domain + relative path URL.
	 * @see Director::getSiteUrl()
	 */
	public function getFullUrl($aRelativeURL='') {
		$theResult = SERVER_URL;
		if ( strlen(VIRTUAL_HOST_NAME)>0 ) {
			$theResult .= '/' . VIRTUAL_HOST_NAME;
		}
		if ( !empty($aRelativeURL) ) {
			$theArgs = ( is_array($aRelativeURL) ) ? $aRelativeURL : func_get_args();
			$theResult .= $this->convertArgsToPath($theArgs);
		}
		else {
			$theResult .= '/';
		}
		return $theResult;
	}
	
	/**
	 * Returns the chat forum this site is mated with, or "" if not.
	 * @return string URL of the forum, if any.
	 */
	public function getForumUrl() {
		if ( !empty($this->mChiefUsher) ) {
			return $this->mChiefUsher->getForumUrl();
		}
	}
	
	/**
	 * Get the setting from the configuration model.
	 * @param string $aSetting - setting in form of "namespace/setting"
	 * @throws \Exception
	 */
	public function getConfigSetting($aSetting) {
		if ( is_null($this->dbConfig) )
		{ $this->dbConfig = $this->getProp('Config'); }
		if ( !empty($this->dbConfig) )
		{ return $this->dbConfig[$aSetting]; }
		else {
			//if dbConfig is empty, means dbconn error
			//  return the pre-defined default value, if any
			list($theAreaName, $theSettingName) = explode('/', $aSetting, 2);
			$res = $this->getRes('config/' . $theAreaName);
			if ( array_key_exists($theSettingName, $res) )
			{
				/* @var $theConfigRes \BitsTheater\costumes\ConfigResEntry */
				$theConfigRes = $res[$theSettingName];
				return $theConfigRes->default_value;
			}
		}
	}

	/**
	 * Get the current mode of the site (normal/maintenance/demo).
	 * @return string Returns one of the MODE_* constants.
	 */
	public function getSiteMode() {
		try {
			return $this->getConfigSetting('site/mode');
		} catch (Exception $e) {
			return static::SITE_MODE_NORMAL;
		}
	}
	
	/**
	 * @return string Returns the logged in user's account name, if any.
	 */
	public function getMyUsername() {
		if ( !empty($this->account_info) )
			return $this->account_info->account_name;
		else
			return null;
	}
	
	/**
	 * @return AccountInfoCache|null Return non-sensitive account info
	 *    cache object used for the currently logged in user.
	 *    Returns NULL not logged in.
	 */
	public function getMyAccountInfo()
	{ return $this->account_info; }
	
	/**
	 * Cache the non-sensitive account info for the currently
	 *    logged in user.
	 * @return AccountInfoCache|null Return non-sensitive account info
	 *    cache object used for the currently logged in user.
	 *    Returns NULL if Auth model is not available.
	 */
	public function setMyAccountInfo( $aAcctInfo=null )
	{
		if ( !empty($this->mChiefUsher) ) {
			$this->account_info = $this->mChiefUsher->createAccountInfoObj($aAcctInfo);
		}
		return $this->account_info;
	}
	
	/**
	 * Given the category of the logfile, return the filepath.
	 * @param string $aMimeType
	 */
	public function getLogFileOf($aCategory=null) {
		$thePath = FileUtils::appendPath($this->getConfigSetting('site/mmr'), 'logs');
		// Make sure there are no shenanigans with special characters (like "../")
		// which could be abused to write outside of the specified directory
		$theSanitizedCategory = preg_replace('/[^a-zA-Z0-9]/', '_', $aCategory);
		return FileUtils::appendPath($thePath, $theSanitizedCategory).'.log';
	}
	
	/**
	 * Log messages to a particular file (dictated by $aCategory).
	 * @param string $aCategory - the file to write to in "config[mmr]/log".
	 * @param string $aMessage - the text line to log (EOL will be appended if necessary).
	 */
	public function log($aCategory, $aMessage) {
		$theCacheKey = VIRTUAL_HOST_NAME . '|' . $aCategory;
		if (empty($this->_logFilenameCache[$theCacheKey])) {
			$this->_logFilenameCache[$theCacheKey] = $this->getLogFileOf($aCategory);
		}
		$theFilename = $this->_logFilenameCache[$theCacheKey];
		
		if (!Strings::endsWith($aMessage, PHP_EOL))
			$aMessage .= PHP_EOL;
		
		if (@file_put_contents($theFilename, $aMessage, FILE_APPEND | LOCK_EX)!==strlen($aMessage)) {
			@mkdir(dirname($theFilename), 0777, true);
			if (@file_put_contents($theFilename, $aMessage, FILE_APPEND | LOCK_EX)!==strlen($aMessage)) {
				$this->errorLog("Failed to open file [{$theFilename}] for appending.");
			}
		}
	}
	
	/**
	 * Deletes a log file. Will errorLog() if specified file exists
	 * but was unsuccessful in deletion attempt.
	 * @param string $aCategory Filename of log file desired.
	 */
	public function deleteLogFile($aCategory) {
		$theCacheKey = VIRTUAL_HOST_NAME . '|' . $aCategory;
		if (empty($this->_logFilenameCache[$theCacheKey])) {
			$this->_logFilenameCache[$theCacheKey] = $this->getLogFileOf($aCategory);
		}
		$theFilename = $this->_logFilenameCache[$theCacheKey];
		
		if ($theFilename != null) {
			if (@file_exists($theFilename)) {
				$result = @unlink($theFilename);
				if ($result == false) {
					$this->errorLog("Failed to delete file [{$theFilename}], unlink unsuccessful.");
				}
			}
		}
	}

	/**
	 * Log messages to a particular file (dictated by $aCategory) prepended with a timestamp.
	 * @param string $aCategory - the file to write to in "config[mmr]/log".
	 * @param string $aMessage - the text line to log (EOL will be appended if necessary).
	 * @param number $aTimestamp - (optional) Unix Timestamp to use instead of Now().
	 */
	public function logWithTimestamp($aCategory, $aMessage, $aTimestamp=null) {
		$theTimeStr = null;
		// Create the full message and append it to the file
		if (empty($aTimestamp))
			$theTimeStr = gmdate('Y-m-d\TH:i:s');
		else
			$theTimeStr = gmdate('Y-m-d\TH:i:s', $aTimestamp);
		$theFullMsg = '['.$theTimeStr.'] '.$aMessage;
		$this->log($aCategory, $theFullMsg);
	}
	
	/**
	 * Determine if we are executing in CLI mode or not.
	 * @return boolean
	 */
	public function isRunningUnderCLI()
	{
		global $theStageManager;
		return $theStageManager->isRunningUnderCLI();
	}
	
	/**
	 * Method to use instead of 'use \BitsTheater\configs\I18N;' since the
	 * class itself does not exist until runtime installation creates it.
	 * @return \BitsTheater\res\ResI18N Return the SiteLangauges class.
	 */
	public function getSiteLanguageClass()
	{
		$theInstalledLangClass = BITS_NAMESPACE_RES . 'ResI18N';
		return $theInstalledLangClass;
	}
	
	/**
	 * Method to use instead of 'use \BitsTheater\configs\Settings;' since the
	 * class itself does not exist until runtime installation creates it.
	 * @return SiteSettings Return the SiteSettings class.
	 */
	public function getSiteSettingsClass()
	{
		$theInstalledSettingsClass = BITS_NAMESPACE_CFGS . 'Settings';
		return $theInstalledSettingsClass;
	}
	
}//end class

}//end namespace
