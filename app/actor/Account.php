<?php
namespace app\actor; 
use app\SystemExit;
use app\Actor;
use app\model\Auth;
use app\model\Accounts;
use app\config\Settings;
use com\blackmoonit\Strings;
{//namespace begin

class Account extends Actor {
	const DEFAULT_ACTION = 'register';

	public function view($aAcctId=null) {
		if ($this->director->isGuest()) {
			return $this->config['auth/register_url'];
		}
		$this->scene->dbAccounts = $this->getProp('Accounts');
		if (!empty($aAcctId) && $this->isAllowed('account','modify')) {
			$this->scene->ticket_info = $this->scene->dbAccounts->getAccount($aAcctId);
			$dbAuth = $this->getProp('Auth');
			$authdata = $dbAuth->getAuthById($aAcctId);
			$this->scene->ticket_info['email'] = $authdata['email'];
		} else {
			$this->scene->ticket_info = $this->director->account_info;
		}
		$this->scene->action_modify = BITS_URL.'/account/modify';
		$this->scene->redirect = BITS_URL.'/account/view';
	}
	
	public function register($aTask='data-entry') {
		$dbAccts = $this->getProp('Accounts');
		//make sure user/pw reg fields will not interfere with any login user/pw field in header
		$userKey = $this->scene->getUsernameKey().'_reg';
		$pwKey = $this->scene->getPwInputKey().'_reg';
		if ($aTask==='new' && $this->scene->reg_code===Settings::APP_ID &&
				$this->scene->$pwKey===$this->scene->password_confirm) {
			$dbAuth = $this->getProp('Auth');
			$theRegResult = $dbAuth->canRegister($this->scene->$userKey,$this->scene->email);
			switch ($theRegResult) {
			case Auth::REGISTRATION_EMAIL_TAKEN:
				return $this->getMyUrl('/account/register',
						array('err_msg'=>$this->getRes('account/msg_acctexists/'.$this->getRes('account/label_email'))));
			case Auth::REGISTRATION_NAME_TAKEN:
				return $this->getMyUrl('/account/register',
						array('err_msg'=>$this->getRes('account/msg_acctexists/'.$this->getRes('account/label_name'))));
			default: //create new acct
				$theNewAcct['account_name'] = $this->scene->$userKey;
				$theNewId = $dbAccts->add($theNewAcct);
				if (!empty($theNewId)) {
					$verified_ts = null;
					if ($this->scene->reg_code===Settings::APP_ID) {
						$verified_ts = $dbAccts->utc_now();
					}
					$theNewAcct = array(
							'email' => $this->scene->email,
							'account_id' => $theNewId,
							'pwinput' => $this->scene->$pwKey,
							'verified_timestamp' => $verified_ts,
					);
					$dbAuth->registerAccount($theNewAcct);
					return BITS_URL.'/rights';
				}
			}//end switch
		} else {
			//$this->scene->err_msg = $_SERVER['QUERY_STRING'];
			//$this->scene->err_msg = array_key_exists('err_msg',$_GET)?$_GET['err_msg']:null;
			$this->scene->form_name = 'register_user';
			$this->scene->action_register = BITS_URL.'/account/register/new';
			$this->scene->post_key = Settings::APP_ID;
			if ($dbAccts->isEmpty()) {
				$this->scene->redirect = BITS_URL.'/rights';
				$this->scene->reg_code = Settings::APP_ID;
			} else {
				$this->scene->redirect = BITS_URL.Settings::PAGE_Landing;
			}
		}
	}
	
	public function login() {
		if (!$this->director->isGuest()) {
			if ($this->scene->redirect)
				return $this->scene->redirect;
			else
				return BITS_URL.Settings::PAGE_Landing;
		} else {
			$this->scene->action_login = $this->config['auth/login_url'];
			$this->scene->redirect = BITS_URL.Settings::PAGE_Landing;
		}
	}
	
	public function logout() {
		$s = $this->director->logout();
		if (!empty($this->scene->redirect))
			$s = $this->scene->redirect;
		return $s;
	}
	
	/**
	 * Renders the login/logout area of a page.
	 */
	public function buildAuthArea() {
		$this->scene->action_login = $this->config['auth/login_url'];
		$this->scene->action_logout = $this->config['auth/logout_url'];
		
	}
	
	public function modify() {
		$dbAccts = $this->getProp('Accounts');
		$theAcctId = $this->scene->ticket_num;
		$dbAuth = $this->getProp('Auth');
		$pwKeyOld = $this->scene->getPwInputKey().'_old';
		if ($dbAuth->isCallable('cudo') && $dbAuth->cudo($theAcctId,$this->scene->$pwKeyOld)) {
			//if current pw checked out ok, see if its our own acct or have rights to modify other's accounts.
			if ($theAcctId==$this->director->account_info['account_id'] || $this->isAllowed('account','modify')) {
				$theOldEmail = trim($this->scene->ticket_email);
				$theNewEmail = trim($this->scene->email);
				/* !== returned TRUE, === retruend FALSE, but strcmp() returned 0 (meaning they are the same) O.o 
				$b1 = ($theOldEmail!==$theNewEmail);
				$b2 = ($theOldEmail===$theNewEmail);
				$b3 = (strcmp($theOldEmail,$theNewEmail)!=0);
				Strings::debugLog('b:'.$b1.','.$b2.',',$b3);
				Strings::debugLog(Strings::bin2hex($theOldEmail));
				Strings::debugLog(Strings::bin2hex($theNewEmail));
				/* */
				if (strcmp($theOldEmail,$theNewEmail)!=0) {
					//Strings::debugLog('email is not 0:'.strcmp($theOldEmail,$theNewEmail));
					if ($dbAuth->getAuthByEmail($theNewEmail)) {
						return $this->getMyUrl('/account/view',
								array('err_msg'=>$this->getRes('account/msg_acctexists/'.$this->getRes('account/label_email'))));
					} else {
						$theSql = 'UPDATE '.$dbAuth->tnAuth.' SET email = :email WHERE account_id=:acct_id';
						$dbAuth->execDML($theSql,array('acct_id'=>$theAcctId, 'email'=>$theNewEmail));
					}
				}
				$pwKeyNew = $this->scene->getPwInputKey().'_new';
				if (!empty($this->scene->$pwKeyNew) && $this->scene->$pwKeyNew===$this->scene->password_confirm) {
					$thePwHash = Strings::hasher($this->scene->$pwKeyNew);
					$theSql = 'UPDATE '.$dbAuth->tnAuth.' SET pwhash = :pwhash WHERE account_id=:acct_id';
					$dbAuth->execDML($theSql,array('acct_id'=>$theAcctId, 'pwhash'=>$thePwHash));
				}
				return $this->getMyUrl('/account/view',
						array('err_msg'=>$this->getRes('account/msg_update_success')));
			}
		} else {
			return $this->getMyUrl('/account/view',
					array('err_msg'=>$this->getRes('generic/msg_permission_denied')));
		}
	}
	
}//end class

}//end namespace

