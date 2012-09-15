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
use com\blackmoonit\AdamEve as BaseDirector;
use com\blackmoonit\Strings;
use com\blackmoonit\database\DbUtils;
use \ArrayAccess;
use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionException;
{//begin namespace

class Director extends BaseDirector implements ArrayAccess {
	public $account_info = null;//array('account_id'=>-1, 'account_name'=>'', 'email'=>'', 'groups'=>array(), 'tz'=>'',);
	public $table_prefix = ''; //prefix for every table used by this app
	public $dbConn = null; //single database connection to share with anyone using getModel()
	protected $_propMaster = array(); //cache models created so app doesn't need to create 12 instances of any single model
	protected $_resMaster = array(); //cache of res classes
	protected $auth = null; //cache of Auth model

	public function setup() {
		parent::setup();
		if (session_id() === "")
			session_start();
		if ($this->isInstalled()) {
			$this['app_id'] = config\Settings::APP_ID;
		}
	}

	public function cleanup() {
		if (session_id()!='') {
			session_write_close();
		}
		unset($this->account_info);
		//destroy all cashed models
		array_walk($this->_propMaster, function(&$n) {$n['model'] = null;} );
		unset($this->_propMaster);
		//disconnect db
		if (isset($this->dbConn)) {
			//PDO does not have a disconnect at this time
			//$this->dbConn->disconnect();
		}
		unset($this->dbConn);
		//free all resources
		$this->freeRes();
		//call parent
		parent::cleanup();
	}
	
	//----- methods required for various IMPLEMENTS interfaces
	
	public function offsetSet($aOffset, $aValue) {
		$_SESSION[$aOffset] = $aValue;
	}

	public function offsetExists($aOffset) {
		return isset($_SESSION[$aOffset]);
	}

	public function offsetUnset($aOffset) {
		unset($_SESSION[$aOffset]);
	}

	public function offsetGet($aOffset) {
		return isset($_SESSION[$aOffset])?$_SESSION[$aOffset]:null;
	}
	
	//----- IMPLEMENTS handled, get on with being a Director below -----

	public function resetSession() {
		//throw new \Exception('resetSession');
		session_unset();
		session_destroy();
		session_start();
	}
	
	public function isInstalled() {
		return class_exists(BITS_BASE_NAMESPACE.'\\app\\config\\Settings');
	}

	public function canCheckTickets() {
		return $this->canConnectDb() && class_exists(BITS_BASE_NAMESPACE.'\\app\\model\\Auth');
	}
	
	public function canConnectDb() {
		return file_exists(BITS_DB_INFO);
	}
	
	public function canGetRes() {
		return class_exists(BITS_BASE_NAMESPACE.'\\app\\config\\I18N');
	}
	
	//===== Actor methods =====

	public function raiseCurtain($anActorClass, $anAction, $aQuery=array()) {
		//Strings::debugLog('rC: class='.$anActorClass.', exist?='.class_exists($anActorClass));
		if (class_exists($anActorClass)) {
			if (empty($anAction))
				$anAction = $anActorClass::DEFAULT_ACTION;
			$methodExists = method_exists($anActorClass,$anAction) && is_callable(array($anActorClass,$anAction));
			if ($methodExists && $anActorClass::ALLOW_URL_ACTIONS) {
				if ($this->isInstalled()) {
					$this['played'] = config\Settings::APP_ID; //app_id -> play_id -> "played"
				}
				//Strings::debugLog('raiseCurtain: '.$anActorClass.', '.$anAction.', '.Strings::debugStr($aQuery));
				$anActorClass::perform($this,$anAction,$aQuery);
				return true;
			} else {
        	    return false;
			}
		} else {
			return false;
		}
	}
	
	public function cue($aScene, $anActorName, $anAction, $args=array()) {
		$anActorClass = BITS_BASE_NAMESPACE.'\\app\\actor\\'.$anActorName;
		//Strings::debugLog('rC: class='.$anActorClass.', exist?='.class_exists($anActorClass));
		if (class_exists($anActorClass)) {
			if (empty($anAction))
				$anAction = $anActorClass::DEFAULT_ACTION;
			try {
				$theMethod = new ReflectionMethod($anActorClass,$anAction);
				//if no exception, instantiate the class and call the method
				$theActor = new $anActorClass($this,$anAction);
				$theMethod->setAccessible(true); //protected from direct "raiseCurtain" calls, but ok for cue().
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
	}
	
	//===== Model methods =====
	
	public function getModel($aModelClass) {
		if (empty($this->dbConn) && $this->canConnectDb()) {
			$theDbInfo = DbUtils::readDbConnInfo(BITS_DB_INFO);
			$this->table_prefix = $theDbInfo['dbopts']['table_prefix'];
			$this->dbConn = Model::getConnection($theDbInfo);
		}
		if (is_string($aModelClass)) {
			$theModelClass = BITS_BASE_NAMESPACE.'\\app\\model\\'.$aModelClass;
		} elseif ($aModelClass instanceof ReflectionClass) {
			$theModelClass = $aModelClass->getName();
		}
		if (empty($this->_propMaster[$theModelClass])) {
			$this->_propMaster[$theModelClass]['model'] = new $theModelClass($this,$this->dbConn);
			$this->_propMaster[$theModelClass]['ref_count'] = 0;
		}
		$this->_propMaster[$theModelClass]['ref_count'] += 1;
		return $this->_propMaster[$theModelClass]['model'];
	}
	
	public function unsetModel($aModel) {
		if (isset($aModel)) {
			$theModelClass = get_class($aModel);
			if (isset($this->dbConn) && isset($this->_propMaster[$theModelClass])) {
				$this->_propMaster[$theModelClass]['ref_count'] -= 1;
				if ($this->_propMaster[$theModelClass]['ref_count']<1) {
					$this->_propMaster[$theModelClass]['model'] = null;
					unset($this->_propMaster[$theModelClass]);
				}
			}
			$aModel = null;
		}
	}
	
	//alias for getModel
	public function getProp($aModelClass) {
		return $this->getModel($aModelClass);
	}
	
	public function returnProp($aModel) {
		$this->unsetModel($aModel);
	}
	
	
	//===== RESOURCE management =====

	public function getRes($aResName) {
		//explode on "\" or "/"
		$theResUri = explode('/',str_replace('\\','/',$aResName));
		if (count($theResUri)>=2) {
			$theResClassName = Strings::getClassName(array_shift($theResUri));
			$theResClass = config\I18N::findClassNamespace($theResClassName);
			$theRes = array_shift($theResUri);
		} else {
			$theResClass = BITS_BASE_NAMESPACE.'\\res\\Resources';
			$theRes = array_shift($theResUri);
		}
		try {
			if (!empty($theResUri))
				return $this->loadRes($theResClass,$theRes,$theResUri);
			else
				return $this->loadRes($theResClass,$theRes);
		} catch (ResException $re) {
			if (config\I18N::LANG==config\I18N::DEFAULT_LANG && config\I18N::REGION==config\I18N::DEFAULT_REGION) {
				throw $re;
			} else {
				$theResClass = config\I18N::findDefaultClassNamespace($theResClassName);
				return $this->loadRes($theResClass,$theRes,$theResUri);
			}
		}
	}
	
	protected function loadRes($aResClass, $aRes, $args=null) {
		if (empty($this->_resMaster[$aResClass])) {
			$this->_resMaster[$aResClass] = new $aResClass($this);
		}
		$resObj = $this->_resMaster[$aResClass];
		if (is_callable(array($resObj,$aRes))) {
			try {
				return call_user_func_array(array($resObj,$aRes),$args);
			} catch (\Exception $e) {
				throw new ResException($aRes,$aResClass,$args,$e);
			}
		} else {
			if (isset($resObj->$aRes)) {
				if (isset($args)) {
					//Strings::debugLog('b: '.$resObj->$aRes.Strings::debugStr($args));
					return call_user_func_array(array('com\blackmoonit\Strings','format'),array_merge(array($resObj->$aRes),$args));
				} else {
					return $resObj->$aRes;
				}
			} else {
				throw new ResException($aRes);
			}
		}			
	}
	
	public function freeRes() {
		array_walk($this->_resMaster, function(&$n) {$n = null;} );
	}

	//===== LOGIN INFO ========
	
	public function admitAudience() {
		if ($this->canCheckTickets()) {
			$this->auth = $this->getProp('Auth'); //director will close this on cleanup
			return $this->auth->checkTicket($this);
		}
		return false;
	}
	
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (isset($this->auth))
			return $this->auth->isAllowed($aNamespace, $aPermission, $acctInfo);
		else
			return false;
	}
	
	public function isGuest() {
		if ($this->auth->isCallable('isGuest')) {
			return $this->auth->isGuest();
		} else {
			return (empty($this->account_info) || empty($this->account_info['groups']) || count($this->account_info['groups'])<1);
		}
	}
	
	public function logout() {
		if (!$this->isGuest() && isset($this->auth)) {
			$this->auth->ripTicket($this);
			unset($this->account_info);
		}
		return BITS_URL;
	}
	
	public function getForumUrl() {
		if ($this->auth->isCallable('getForumUrl')) {
			return $this->auth->getForumUrl();
		} else {
			return "";
		}
	}
	
}//end class

}//end namespace
