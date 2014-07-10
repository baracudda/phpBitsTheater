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

namespace BitsTheater\actors; 
use BitsTheater\Actor;
use BitsTheater\scenes\Rights as MyScene;
	/* @var $v MyScene */
use BitsTheater\models\Permissions;
	/* @var $dbRights Permissions */
use BitsTheater\models\Auth;
	/* @var $dbAuth Auth */ 
use com\blackmoonit\Strings;
{//namespace begin

class Rights extends Actor {
	const DEFAULT_ACTION = 'groups';
	
	public function groups() {
		if (!$this->director->isAllowed('permissions','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		$dbAuth = $this->getProp('Auth');
		$v->groups = $dbAuth->getGroupList();
		$this->director->returnProp($dbAuth);

		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
	}
	
	public function group($aGroupId) {
		if (!$this->director->isAllowed('permissions','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		if (is_null($aGroupId) || $aGroupId==1)
			return $this->getMyUrl('/rights');
		if ($aGroupId===0) {
			$v->group = null;
		} else {
			$dbAuth = $this->getProp('Auth');
			$v->groups = $dbAuth->getGroupList();
			$this->returnProp($dbAuth);
			foreach ($v->groups as $theGroup) {
				if ($theGroup['group_id']==$aGroupId) {
					$v->group = $theGroup;
					break;
				}
			}
		}
		$dbRights = $this->getProp('Permissions');
		$this->scene->right_groups = $v->getPermissionRes('namespace');
		$this->scene->assigned_rights = $dbRights->getAssignedRights($aGroupId);
		$this->scene->redirect = $this->getMyUrl('/rights');
		$this->scene->next_action = $this->getMyUrl('/rights/modify');
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
	}
	
	public function modify() {
		$v =& $this->scene;
		if (!$this->isAllowed('permissions','modify'))
			return $this->getHomePage();
		if (is_null($v->group_id) || $v->group_id==1)
			return $this->getMyUrl('/rights');
		//do update of DB
		//print('<pre>');var_dump($v);print('</pre>');
		$dbRights = $this->getProp('Permissions');
		$dbRights->modifyGroupRights($v);
		$this->returnProp($dbRights);
		return $v->redirect;
	}
		
}//end class

}//end namespace

