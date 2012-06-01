<?php
namespace app\model; 
use app\model\AuthBase;
use app\config\Settings;
use com\blackmoonit\Strings;
{//namespace begin

class Auth extends AuthBase {
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
	const KEY_pwhash = 'ticketmaster';
	const KEY_app_id = 'venue_id';

	public $tnAuth;
	protected $sql_get_auth;
	protected $sql_add_auth;
	protected $pt_add_auth;
	protected $sql_register;
	protected $pt_register;
		
	public function setup($aDbConn) {
		parent::setup($aDbConn);
		$this->tnAuth = Settings::TABLE_PREFIX.'auth';
		$this->sql_get_auth = "SELECT account_id, account_name, email, pwhash FROM {$this->tnAuth} WHERE ".
				"account_name = :acctname OR email = :email";
		$this->sql_add_auth = "INSERT INTO {$this->tnAuth} ".
				"(email, account_id, verified, is_reset, _created) VALUES ".
				"(:email, :account_id, :verified, :curr_ts, :_created)";
		$this->pt_add_auth = array(PDO::PARAM_STR,PDO::PARAM_INT,PDO::PARAM_STR,PDO::PARAM_STR,PDO::PARAM_STR);
		$this->sql_register = "INSERT INTO {$this->tnAuth} ".
				"(email, account_id, account_name, pwhash, verified, _created) VALUES ".
				"(:email, :acct_id, :acct_name, :pwhash, :ts, NOW())";
		$this->pt_register = array(PDO::PARAM_INT,PDO::PARAM_STR,PDO::PARAM_STR,PDO::PARAM_STR,PDO::PARAM_INT);
	}
	
	public function cleanup() {
		parent::cleanup();
	}
	
	public function setupModel() {
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnAuth} ".
				"( email NCHAR(255) NOT NULL PRIMARY KEY COLLATE utf8_unicode_ci". //store as typed, but collate as case-insensitive
				", account_id INT NOT NULL".							//link to Accounts
				", pwhash CHAR(85) CHARACTER SET ascii COLLATE ascii_bin".	//blowfish hash of pw & its salt
				", verified INT".										//unix timestamp when acct was verified
				", is_reset INT".										//force pw reset in effect since this unix timestamp (if set)
				", _created TIMESTAMP NOT NULL DEFAULT 0".
				", _changed TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP".
				", INDEX IdxAcctId (account_id)".
				") CHARACTER SET utf8 COLLATE utf8_bin";
		}
		$this->execDML($theSql);
	}
	
	public function setupPermissions($aScene, $modelPermissions) {
		parent::setupPermissions($aScene,$modelPermissions);
		$modelPermissions->registerPermission($this->myAppNamespace,'create'); //add permission groups
		$modelPermissions->registerPermission($this->myAppNamespace,'delete'); //remove existing permission groups
	}
	
	public function getAuthData($aUserInfo) {
		$theValues = array('acctname'=>strtolower($aUserInfo),'email'=>strtolower($aUserInfo));
		return $this->query($this->sql_get_auth,$theValues)->fetch();
	}

	public function checkTicket() {
		if ($this->director->canConnectDb()) {
			if (isset($_POST[self::KEY_userinfo])) {
				$userinfo = strtolower($_POST[self::KEY_userinfo]);
				$pwinput = $_POST[self::KEY_pwinput];
				$authdata = $this->getAuthData($userinfo);
				if (isset($authdata)) {
					//check pwinput against crypted one
					$pwhash = $authdata['pwhash'];
					if ($pwhash == Strings::hasher($pwinput,$pwhash)) {
						//authorized, load acct data
						$accounts = $this->director->getProp('Accounts');
						$this->director->account_info = $accounts->getAccount($authdata['account_id']);
						$this->director->returnProp($accounts);
						if (isset($this->director->account_info)) {
							$this->director->account_info['email'] = $authdata['email'];
							$this->director->account_info['groups'] = $this->belongsToGroups($authdata['account_id']);
							//if user asked to remember, save a cookie
							if (isset($_POST[self::KEY_cookie])) {
								//expires in 1 month
								setcookie(self::KEY_userinfo, $authdata['account_name'], time() + 2629743);
								setcookie(self::KEY_pwhash, $pwhash, time() + 2629743);
								setcookie(self::KEY_app_id, $this->director['app_id']);
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
			} elseif (!empty($_COOKIE[self::KEY_userinfo]) && !empty($_COOKIE[self::KEY_pwhash])) {
				$userinfo = strtolower($_COOKIE[self::KEY_userinfo]);
				$pwhash = $_COOKIE[self::KEY_pwhash];
				$authdata = $this->getAuthData($userinfo);
				if (isset($authdata)) {
					//check pwhash against saved one
					if ($pwhash == $authdata['pwhash']) {
						//authorized, load acct data
						$accounts = $this->director->getProp('Accounts');
						$this->director->account_info = $accounts->getAccount($authdata['account_id']);
						$this->director->returnProp($accounts);
						if (isset($this->director->account_info)) {
							$this->director->account_info['email'] = $authdata['email'];
							$this->director->account_info['groups'] = $this->belongsToGroups($authdata['account_id']);
							//update cookie - expires in 1 month
							setcookie(self::KEY_userinfo, $authdata['account_name'], time() + 2629743);
							setcookie(self::KEY_pwhash, $pwhash, time() + 2629743);
							setcookie(self::KEY_app_id, $this->director['app_id']);
						}
					} else {
						$aDirector->account_info = null;
					}
					unset($authdata);
				}
				unset($userinfo);
				unset($pwhash);
			} else {
				parent::checkTicket();
			}
		}
	}
	
	public function ripTicket() {
		setcookie(self::KEY_userinfo);
		setcookie(self::KEY_pwhash);
		setcookie(self::KEY_app_id);
		parent::ripTicket();
	}
	
	public function canRegister($aAcctName, $aEmailAddy) {
		// check if username exists
		$theParam = strtolower($aAcctName);
		$theSql = "SELECT account_name FROM {$this->tnAuth} WHERE account_name = :acctname";
		$authdata = $this->query($theSql,array('acctname'=>$theParam))->fetch();
		if (isset($authdata) && ($authdata['account_name']===$theParam)) {
			return REGISTRATION_NAME_TAKEN;
		}
		//check to see if email is taken
		$theParam = strtolower($aEmailAddy);
		$theSql = "SELECT email FROM {$this->tnAuth} WHERE email = :email";
		$authdata = $this->query($theSql,array('email'=>$theParam))->fetch();
		if (isset($authdata) && ($authdata['email']===$theParam)) {
			return REGISTRATION_EMAIL_TAKEN;
		}
		return REGISTRATION_SUCCESS;
	}
		
	public function registerAccount($aUserData) {
		if (parent::registerAccount($aUserData)) {
			$theValues = array(
					'email'		=> strtolower($aUserData['email']),
					'acct_id'	=> $aUserData['account_id'],
					'acct_name'	=> strtolower($aUserData['account_name']),
					'pwhash'	=> Strings::hasher($aUserData['pw']),
					'ts'		=> now(),
			);
			$this->execDML($this->sql_register,$theValues,$this->pt_register);
			return true;
		} else {
			return false;
		}
	}

	public function addAuthAccount($aData) {	
		if (empty($aData)) 
			return;
		return $this->execDML($this->sql_add_auth,$aData,$this->pt_add_auth);
	}
	
	/**
	 * Return currently logged in person's group memberships.
	 */
	public function belongsToGroups($acctInfo) {
		if (empty($acctInfo) || empty($acctInfo['account_id']))
			return array();
		$groups = $this->director->getProp('Groups');
		$theResult = $groups->getAcctGroups($authdata['account_id']);
		$this->director->returnProp($groups);
		return $theResult;
	}
	
	public function getGroupList() {
		$groups = $this->director->getProp('Groups');
		$theSql = "SELECT * FROM {$groups->tnGroups} ";
		$r = $groups->query($theSql);
		$theResult = $r->fetchAll();
		$this->director->returnProp($groups);
		return $theResult;
	}

}//end class

}//end namespace
