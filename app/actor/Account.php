<?php
namespace app\actor; 
use app\Actor;
use app\model\Accounts;
use app\config\Settings;
{//namespace begin

class Account extends Actor {
	const DEFAULT_ACTION = 'view';

	public function view($aAcctId=null) {
		if (empty($aAcctId)) {
		
		} else {
		
		}
	}
	
	public function register() {
	
	}
	
	public function login() {
		//$this->scene->next_action = ""; 
	
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
		//optional, not used by Auth_Basic, but may be used by others
	}

	public function buildAuthLogin() {
		//view dictates content
	}
	
	public function buildAuthLogout() {
		//view dictates content
	}
	
}//end class

}//end namespace

