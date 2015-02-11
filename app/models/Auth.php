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

namespace BitsTheater\models;
use BitsTheater\models\AuthBase as BaseModel;
use com\blackmoonit\Strings;
use BitsTheater\costumes\SqlBuilder;
use \PDO;
{//namespace begin

class Auth extends BaseModel {
	const TYPE = 'basic';
	const ALLOW_REGISTRATION = true;
	const REGISTRATION_SUCCESS = 0;
	const REGISTRATION_NAME_TAKEN = 1;
	const REGISTRATION_EMAIL_TAKEN = 2;
	const REGISTRATION_ASK_EMAIL = true;
	const REGISTRATION_ASK_PW = true;

	const KEY_userinfo = 'ticketholder';
	const KEY_pwinput = 'pwinput';
	const KEY_cookie = 'seasontickets';
	const KEY_keyid = 'ticketmaster';

	public $tnAuth;
	protected $tnAuthCookie;
		
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnAuth = $this->tbl_.'auth';
		$this->tnAuthCookie = $this->tbl_.'auth_cookie';
	}
	
	public function cleanup() {
		parent::cleanup();
	}
	
	public function setupModel() {
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnAuth} ".
				"( email NCHAR(255) NOT NULL PRIMARY KEY". //store as typed, but collate as case-insensitive
				", account_id INT NOT NULL".							//link to Accounts
				", pwhash CHAR(85) CHARACTER SET ascii NOT NULL COLLATE ascii_bin".	//blowfish hash of pw & its salt
				", verified DATETIME".									//UTC when acct was verified
				", is_reset INT".										//force pw reset in effect since this unix timestamp (if set)
				", _created TIMESTAMP NOT NULL DEFAULT 0".
				", _changed TIMESTAMP ON UPDATE CURRENT_TIMESTAMP".
				", INDEX IdxAcctId (account_id)".
				") CHARACTER SET utf8 COLLATE utf8_general_ci";
			$this->execDML($theSql);
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnAuthCookie} ".
				"( account_id INT NOT NULL".							//link to Accounts
				", keyid CHAR(128) CHARACTER SET ascii NOT NULL COLLATE ascii_bin".
				", _changed TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP".
				", INDEX IdxAcctIdKeyId (account_id, keyid)".
				") CHARACTER SET utf8 COLLATE utf8_bin";
			$this->execDML($theSql);
		}
	}
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnAuth : $aTableName );
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnAuth : $aTableName );
	}
	
	public function getAuthByEmail($aEmail) {
		$theSql = "SELECT account_id, email, pwhash FROM {$this->tnAuth} WHERE email = :email";
		return $this->getTheRow($theSql,array('email'=>$aEmail));
	}
	
	public function getAuthById($aId) {
		$theSql = "SELECT account_id, email, pwhash FROM {$this->tnAuth} WHERE account_id=:id";
		return $this->getTheRow($theSql,array('id'=>$aId),array(\PDO::PARAM_INT));
	}
	
	public function updateCookie($aAcctId) {
		$keyid = Strings::createUUID().'-'.Strings::randomSalt(64-36-1); //unique 64char gibberish
		$theSql = "INSERT INTO {$this->tnAuthCookie} (account_id, keyid) VALUES (:account_id, :keyid)";
		$this->execDML($theSql,array('account_id'=>$aAcctId, 'keyid'=>$keyid),array(\PDO::PARAM_INT, \PDO::PARAM_STR));
		//expires in 1 month
		$delta = 2629743;
		setcookie(self::KEY_userinfo, $this->director->app_id.'-'.$aAcctId, time() + $delta, BITS_URL);
		setcookie(self::KEY_keyid, $keyid, time() + $delta, BITS_URL);
	}

	public function checkTicket($aAcctName=null, $aAuth=null) {
		if ($this->director->canConnectDb()) {
			$dbAcct = $this->director->getProp('Accounts');
			if (isset($this->director[self::KEY_userinfo]) && empty($aAcctName)) {
				$acct_id = $this->director[self::KEY_userinfo];
				//Strings::debugLog('userid:'.$acct_id);
				$dbAccts = $this->director->getProp('Accounts');
				$this->director->account_info = $dbAccts->getAccount($acct_id);
				$this->director->returnProp($dbAccts);
				if (!empty($this->director->account_info)) {
					$authdata = $this->getAuthById($acct_id);
					$this->director->account_info['email'] = $authdata['email'];
					$this->director->account_info['groups'] = $this->belongsToGroups($acct_id);
					return;
				} else {
					//Strings::debugLog('ripTicket');
					$this->ripTicket();
				}
			}
			$theUserName = (isset($_POST[self::KEY_userinfo])) ? $_POST[self::KEY_userinfo] : $aAcctName;
			if (!empty($theUserName)) {
				//Strings::debugLog('user:'.$theUserName.' pw:'.$_POST[self::KEY_pwinput]);
				$userinfo = $theUserName;
				$pwinput = $aAuth;
				if (isset($_POST[self::KEY_pwinput])) {
					$pwinput = $_POST[self::KEY_pwinput];
					unset($_POST[self::KEY_pwinput]);
				}
				$authdata = null;
				if ($acctdata = $dbAcct->getByName($userinfo)) {
					//Strings::debugLog('getByName:'.Strings::debugStr($acctdata));
					$authdata = $this->getAuthById($acctdata['account_id']);
				} else {
					$authdata = $this->getAuthByEmail($userinfo);
				}
				if (!empty($authdata)) {
					//check pwinput against crypted one
					$pwhash = $authdata['pwhash'];
					//Strings::debugLog('  db:'.$pwhash);
					//Strings::debugLog('check:'.Strings::debugStr(Strings::hasher($pwinput,$pwhash)));
					if (Strings::hasher($pwinput,$pwhash)) {
						//authorized, load acct data
						$this->director[self::KEY_userinfo] = $authdata['account_id'];
						$accounts = $this->director->getProp('Accounts');
						$this->director->account_info = $accounts->getAccount($authdata['account_id']);
						$this->director->returnProp($accounts);
						if (isset($this->director->account_info)) {
							$this->director->account_info['email'] = $authdata['email'];
							$this->director->account_info['groups'] = $this->belongsToGroups($authdata['account_id']);
							//if user asked to remember, save a cookie
							if (isset($_POST[self::KEY_cookie])) {
								$this->updateCookie($authdata['account_id']);
							}
						}
					} else {
						$this->director->account_info = null;
					}
					unset($authdata);
					unset($pwhash);
				}
				unset($userinfo);
				unset($pwinput);
			} elseif (!empty($_COOKIE[self::KEY_userinfo]) && !empty($_COOKIE[self::KEY_keyid])) {
				$acct_id = Strings::strstr_after($_COOKIE[self::KEY_userinfo],$this->director->app_id.'-')+0;
				$keyid = $_COOKIE[self::KEY_keyid];
				$theSql = SqlBuilder::withModel($this)->setDataSet(array(
						'account_id' => $acct_id,
						'keyid' => $keyid,
				));
				$theSql->startWith('SELECT account_id, keyid');
				$theSql->add('FROM')->add($this->tnAuthCookie);
				$theSql->startWhereClause()->mustAddParam('account_id', 0, PDO::PARAM_INT);
				$theSql->setParamPrefix(' AND ')->mustAddParam('keyid');
				$theSql->endWhereClause();
				$baked = $theSql->getTheRow();
				if (!empty($baked) && Strings::beginsWith($_COOKIE[self::KEY_userinfo],$this->director->app_id)) {
					//consume this particular cookie (single browser/device combo)
					$theSql->reset();
					$theSql->startWith('DELETE FROM')->add($this->tnAuthCookie);
					$theSql->add('WHERE ( _changed < (NOW() - INTERVAL 1 MONTH) )');
					$theSql->setParamPrefix(' OR (')->mustAddParam('account_id', 0, PDO::PARAM_INT);
					$theSql->setParamPrefix(' AND ')->mustAddParam('keyid');
					$theSql->add(')');
					$theSql->execDML();
					
					$authdata = $this->getAuthById($acct_id);
					if (isset($authdata)) {
						//authorized, load acct data
						$this->director->account_info = $dbAcct->getAccount($acct_id);
						if (isset($this->director->account_info)) {
							$this->director->account_info['email'] = $authdata['email'];
							$this->director->account_info['groups'] = $this->belongsToGroups($acct_id);
							//bake (create) a new cookie for next time
							$this->updateCookie($acct_id);
						}
					} else {
						$aDirector->account_info = null;
					}
					unset($authdata);
				}
				unset($userinfo);
			} else {
				parent::checkTicket();
			}
		}
	}
	
	public function ripTicket() {
		setcookie(self::KEY_userinfo);
		setcookie(self::KEY_keyid);
		
		//remove all cookie records for current login (logout means from everywhere)
		$theSql = SqlBuilder::withModel($this)->setDataSet(array(
				'account_id' => $this->director->account_info['account_id'],
		));
		$theSql->startWith('DELETE FROM')->add($this->tnAuthCookie);
		$theSql->add('WHERE ( _changed < (NOW() - INTERVAL 1 MONTH) )');
		$theSql->setParamPrefix(' OR ')->mustAddParam('account_id', 0, PDO::PARAM_INT);
		$theSql->execDML();

		parent::ripTicket();
	}
	
	public function canRegister($aAcctName, $aEmailAddy) {
		$dbAccts = $this->director->getProp('Accounts');
		if ($dbAccts->getByName($aAcctName)) {
			return self::REGISTRATION_NAME_TAKEN;
		} else if ($this->getAuthByEmail($aEmailAddy)) {
			return self::REGISTRATION_EMAIL_TAKEN;
		} else {
			return self::REGISTRATION_SUCCESS;
		}
	}
		
	/**
	 * keys: email, account_id, pwinput, verified_timestamp
	 */
	public function registerAccount($aUserData, $aDefaultGroup=0) {
		if ($this->isEmpty()) {
			$aDefaultGroup = 1;
		}
		$theSql = SqlBuilder::withModel($this)->setDataSet(array(
				'email'		=> $aUserData['email'],
				'account_id'	=> $aUserData['account_id'],
				'pwhash'	=> Strings::hasher($aUserData[self::KEY_pwinput]),
				'verified'		=> $aUserData['verified_timestamp'],
		));
		$theSql->startWith('INSERT INTO '.$this->tnAuth);
		$theSql->add('SET _created=NOW()')->setParamPrefix(', ');
		$theSql->mustAddParam('email');
		$theSql->mustAddParam('account_id', 0, PDO::PARAM_STR);
		$theSql->mustAddParam('pwhash');
		$theSql->addParam('verified');		
		if ($theSql->execDML()) {
			$dbGroupMap = $this->getProp('Groups');
			$dbGroupMap->addAcctMap($aDefaultGroup,$aUserData['account_id']);
			$this->returnProp($dbGroupMap);
		}
	}
	
	public function cudo($aAcctId, $aPwInput) {
		$theAuthData = $this->getAuthById($aAcctId);
		if (!empty($theAuthData['pwhash'])) {
			return (Strings::hasher($aPwInput,$theAuthData['pwhash']));
		} else {
			return false;
		}
	}

	/**
	 * Return currently logged in person's group memberships.
	 */
	public function belongsToGroups($aAcctId) {
		if (empty($aAcctId))
			return array();
		$dbGroups = $this->director->getProp('Groups');
		$theResult = $dbGroups->getAcctGroups($aAcctId);
		$this->director->returnProp($dbGroups);
		return $theResult;
	}
	
	public function getGroupList() {
		$dbGroups = $this->director->getProp('Groups');
		$theSql = "SELECT * FROM {$dbGroups->tnGroups} ";
		$r = $dbGroups->query($theSql);
		$theResult = $r->fetchAll();
		$this->director->returnProp($dbGroups);
		return $theResult;
	}
	
}//end class

}//end namespace
