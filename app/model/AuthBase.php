<?php
namespace app\model; 
use app\Model;
use app\config\Settings;
{//namespace begin

abstract class AuthBase extends Model {
	const TYPE = 'abstract'; //decendants must override this
	const ALLOW_REGISTRATION = false; //only 1 type allows it
	protected $permissions = null;
	
	public function cleanup() {
		if (isset($this->director))
			$this->director->returnProp($this->permissions);
		parent::cleanup();
	}
	
	public function checkTicket() {
		if ($this->director->isInstalled()) {
			if ($this->director['app_id'] != Settings::APP_ID) {
				$this->ripTicket();
			}
		}
	}
	
	public function ripTicket() {
		unset($this->director->account_info);
		$this->director->resetSession();
	}
	
	public function canRegister($aAcctName, $aEmailAddy) {
		return static::ALLOW_REGISTRATION;
	}
		
	public function registerAccount($aUserData) {
		//overwrite this
	}
	
	public function renderInstallOptions($anActor) {
		return $anActor->renderFragment('auth_'.static::TYPE.'_options');
	}
	
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($this->permissions))
			$this->permissions = $this->director->getProp('Permissions'); //cleanup will close this model
		return $this->permissions->isAllowed($aNamespace, $aPermission, $acctInfo);
	}
	
	abstract public function getGroupList();
	
}//end class

}//end namespace
