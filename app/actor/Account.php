<?php
namespace app\actor; 
use app\Actor;
use app\model\Auth;
use app\model\Accounts;
use app\config\Settings;
use com\blackmoonit\Strings;
{//namespace begin

class Account extends Actor {
	const DEFAULT_ACTION = 'register';

	public function view($aAcctId=null) {
		if (empty($aAcctId)) {
			return $this->config['auth/register_url'];
		} else {
			//for now
			return $this->config['auth/register_url'];
		}
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

}//end class

}//end namespace

