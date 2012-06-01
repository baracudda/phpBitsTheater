<?php
namespace app\actor; 
use app\Actor;
use app\DbException;
use com\blackmoonit\Strings;
{//namespace begin

class Rights extends Actor {
	const DEFAULT_ACTION = 'groups';
	
	public function groups() {
		if (!$this->director->isAllowed('permissions','modify'))
			return BITS_URL.Settings::PAGE_Landing;
		$auth = $this->director->getProp('Auth');
		$this->scene->groups = $auth->getGroupList();
		$this->director->returnProp($auth);
	}
	
	public function group($aGroupId) {
		if (!$this->director->isAllowed('permissions','modify'))
			return BITS_URL.Settings::PAGE_Landing;
		if (is_null($aGroupId) || $aGroupId==1)
			return BITS_URL.'/rights';
		if ($aGroupId===0) {
			$this->scene->group = 0;
		} else {
			$auth = $this->director->getProp('Auth');
			$this->scene->groups = $auth->getGroupList();
			$this->director->returnProp($auth);
			foreach ($this->scene->groups as $theGroup) {
				if ($theGroup['group_id']==$aGroupId) {
					$this->scene->group = $theGroup;
					break;
				}
			}
		}
		$this->scene->rights = $this->director->getProp('Permissions');
		$this->scene->right_groups = $this->scene->rights->getPermissionRes('namespace');
		$this->scene->assigned_rights = $this->scene->rights->getAssignedRights($this->scene->group);
		$this->scene->redirect = BITS_URL.'/rights';
		$this->scene->next_action = BITS_URL.'/rights/modify';
	}
	
	public function modify() {
		if (!$this->director->isAllowed('permissions','modify'))
			return BITS_URL.Settings::PAGE_Landing;
		if (is_null($this->scene->group_id) || $this->scene->group_id==1)
			return BITS_URL.'/rights';
		//do update of DB
		//print('<pre>');var_dump($this->scene);print('</pre>');
		$rights = $this->director->getProp('Permissions');
		$rights->modifyGroupRights($this->scene);
		$this->director->returnProp($rights);
		
		return $this->scene->redirect;
	}
		
}//end class

}//end namespace

