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

namespace com\blackmoonit\bits_theater\app\actor; 
use com\blackmoonit\bits_theater\app\Actor;
use com\blackmoonit\Strings;
{//namespace begin

class Rights extends Actor {
	const DEFAULT_ACTION = 'groups';
	
	public function groups() {
		if (!$this->director->isAllowed('permissions','modify'))
			return $this->getHomePage();
		$auth = $this->director->getProp('Auth');
		$this->scene->groups = $auth->getGroupList();
		$this->director->returnProp($auth);
	}
	
	public function group($aGroupId) {
		if (!$this->director->isAllowed('permissions','modify'))
			return $this->getHomePage();
		if (is_null($aGroupId) || $aGroupId==1)
			return $this->getMyUrl('/rights');
		if ($aGroupId===0) {
			$this->scene->group = null;
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
		$this->scene->assigned_rights = $this->scene->rights->getAssignedRights($aGroupId);
		$this->scene->redirect = $this->getMyUrl('/rights');
		$this->scene->next_action = $this->getMyUrl('/rights/modify');
	}
	
	public function modify() {
		if (!$this->director->isAllowed('permissions','modify'))
			return $this->getHomePage();
		if (is_null($this->scene->group_id) || $this->scene->group_id==1)
			return $this->getMyUrl('/rights');
		//do update of DB
		//print('<pre>');var_dump($this->scene);print('</pre>');
		$rights = $this->director->getProp('Permissions');
		$rights->modifyGroupRights($this->scene);
		$this->director->returnProp($rights);
		
		return $this->scene->redirect;
	}
		
}//end class

}//end namespace

