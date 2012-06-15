<?php
namespace app\scene; 
use app\Scene;
use app\model\Auth;
{//namespace begin

class Account extends Scene {

	protected function setupDefaults() {
		parent::setupDefaults();
	}
	
	public function getUsernameKey() {
		return Auth::KEY_userinfo;
	}
	
	public function getUsername() {
		$theKey = $this->getUsernameKey();
		return $this->$theKey;
	}
	
	public function getPwInputKey() {
		return Auth::KEY_pwinput;
	}
	
	public function getPwInput() {
		$theKey = $this->getPwInputKey();
		return $this->$theKey;
	}
	
	public function getUseCookieKey() {
		return Auth::KEY_cookie;
	}
	
	public function getUseCookie() {
		$theKey = $this->getUseCookieKey();
		return $this->$theKey;
	}
	
}//end class

}//end namespace

