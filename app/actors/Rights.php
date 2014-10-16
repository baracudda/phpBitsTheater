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
use com\blackmoonit\Arrays;
use BitsTheater\models\Permissions;
	/* @var $dbRights Permissions */
use BitsTheater\models\Auth;
	/* @var $dbAuth Auth */
use BitsTheater\models\Groups;
	/* @var $dbGroups Groups */
use com\blackmoonit\Strings;
{//namespace begin

class Rights extends Actor {
	const DEFAULT_ACTION = 'groups';
	
	public function groups() {
		if (!$this->director->isAllowed('auth','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		$dbAuth = $this->getProp('Auth');
		$v->groups = Arrays::array_column_as_key($dbAuth->getGroupList(),'group_id');
		$this->director->returnProp($dbAuth);

		//TODO need a better UI for dealing with group reg codes
		$dbGroups = $this->getProp('Groups');
		$v->group_reg_codes = $dbGroups->getGroupRegCodes();
	}
	
	public function group($aGroupId) {
		if (!$this->director->isAllowed('auth','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		if (is_null($aGroupId) || $aGroupId==1)
			return $this->getMyUrl('/rights');
		if ($aGroupId==0) {
			$v->group = null;
		} else {
			$dbAuth = $this->getProp('Auth');
			$v->groups = Arrays::array_column_as_key($dbAuth->getGroupList(),'group_id');
			$v->group = $v->groups[$aGroupId];
		}
		$dbRights = $this->getProp('Permissions');
		$this->scene->right_groups = $v->getPermissionRes('namespace');
		$this->scene->assigned_rights = $dbRights->getAssignedRights($aGroupId);
		$this->scene->redirect = $this->getMyUrl('/rights');
		$this->scene->next_action = $this->getMyUrl('/rights/modify');
	}
	
	public function modify() {
		$v =& $this->scene;
		if (!$this->isAllowed('auth','modify'))
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
	
	public function ajaxUpdateGroup() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//do not render anything
		$this->renderThisView = '_blank';
		if (isset($v->group_id) && $this->isAllowed('auth','modify') && $v->group_id>=0 && $v->group_id!=1) {
			$dbGroups = $this->getProp('Groups');
			$dbGroups->modifyGroup($v);
		} else if ( (!isset($v->group_id) || $v->group_id<0) && $this->isAllowed('auth','create')) {
			$dbGroups = $this->getProp('Groups');
			$dbGroups->createGroup($v->group_name, $v->group_parent, $v->group_reg_code);
		}
	}
		
}//end class

}//end namespace

