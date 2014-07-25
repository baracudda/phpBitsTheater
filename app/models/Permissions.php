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

namespace BitsTheater\models;
use BitsTheater\Model as BaseModel;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use com\blackmoonit\Arrays;
use \PDO;
use \PDOStatement;
use BitsTheater\models\Auth;
	/* @var $dbAuth Auth */
{//namespace begin

class Permissions extends BaseModel {
	const VALUE_Allow = '+';
	const VALUE_Deny = 'x';

	public $tnPermissions; const TABLE_Permissions = 'permissions';

	public $_permCache = array();

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnPermissions = $this->tbl_.self::TABLE_Permissions;
		$this->sql_get_namespace = "SELECT * FROM {$this->tnPermissions} WHERE namespace = :ns AND value = :value";
		$this->sql_del_group = "DELETE FROM {$this->tnPermissions} WHERE group_id = :group_id";
		$this->sql_add_right = "INSERT INTO {$this->tnPermissions} (namespace, permission, group_id, value) VALUES (:ns,:perm,:group_id,:value)";
	}
	
	protected function getTableName() {
		return $this->tnPermissions;
	}
	
	public function setupModel() {
		$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnPermissions} ".
				"( namespace CHAR(40) NULL".
				", permission CHAR(40) NOT NULL".
				", group_id INT NOT NULL".
				", value CHAR(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL".
				", PRIMARY KEY (namespace, permission, group_id)".
				", KEY IdxValuePermissions (namespace, value)".
				", UNIQUE KEY IdxGroupPermissions (group_id, namespace, permission)".
				") CHARACTER SET utf8 COLLATE utf8_general_ci";
		$this->execDML($theSql);
	}
	
	public function isEmpty($aTableName=null) {
		if ($aTableName==null)
			$aTableName = $this->tnPermissions;
		return parent::isEmpty($aTableName);
	}
	
	/**
	 * @deprecated
	 */
	public function isAllowed_OLD($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($this->_permCache[$aNamespace])) {
			$this->_permCache[$aNamespace]['allow'] = array();
			$this->_permCache[$aNamespace]['deny'] = array();
			try {
				$r = $this->query($this->sql_get_namespace,array('ns'=>$aNamespace,'value'=>self::VALUE_Allow));
				while ($row = $r->fetch()) {
					if (empty($this->_permCache[$aNamespace]['allow'][$row['permission']]))
						$this->_permCache[$aNamespace]['allow'][$row['permission']] = array();
					$this->_permCache[$aNamespace]['allow'][$row['permission']][] = $row['group_id'];
				}
				$r->closeCursor();
				$this->bindValues($r,array('ns'=>$aNamespace,'value'=>self::VALUE_Deny));
				$r->execute();
				while ($row = $r->fetch()) {
					if (empty($this->_permCache[$aNamespace]['deny'][$row['permission']]))
						$this->_permCache[$aNamespace]['deny'][$row['permission']] = array();
					$this->_permCache[$aNamespace]['deny'][$row['permission']][] = $row['group_id'];
				}
				$r->closeCursor();
			} catch (DbException $dbe) {
				//use default empty arrays which will mean all permissions will be not allowed
			}
		}
		if (empty($acctInfo)) {
			$acctInfo =& $this->director->account_info;
		}
		if (empty($acctInfo)) {
			return false; //if still no account, nothing to check against, return false.
		}
		//Strings::debugLog('acctinfo:'.Strings::debugStr($acctInfo));
		//Strings::debugLog('perms:'.Strings::debugStr($this->_permCache));
		if (!empty($acctInfo['groups']) && !(array_search(1,$acctInfo['groups'],true)===false)) {
			return true; //group 1 is always allowed everything
		}
		//check deny first
		if (!empty($this->_permCache[$aNamespace]['deny'][$aPermission])) {
			if (count(array_intersect($this->_permCache[$aNamespace]['deny'][$aPermission],$acctInfo['groups']))>0)
				return false;
		}
		//check allow next
		//Strings::debugLog($aNamespace.'/'.$aPermission.'&allow:'.Strings::debugStr(array_intersect($this->_permCache[$aNamespace]['allow'][$aPermission],$acctInfo['groups'])));
		if (!empty($this->_permCache[$aNamespace]['allow'][$aPermission])) {
			if (count(array_intersect($this->_permCache[$aNamespace]['allow'][$aPermission],$acctInfo['groups']))>0)
				return true;
		}
		//if neither denied, nor allowed, then it is not allowed
		return false;
	}
	
	/**
	 * Check group permissions to see if current user account (or passed in one) is allowed.
	 * @param string $aNamespace - namespace of permission.
	 * @param string $aPermission - permission to test against.
	 * @param array $acctInfo - account information (optional, defaults current user).
	 * @return boolean Returns TRUE if allowed, else FALSE.
	 */
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($acctInfo)) {
			$acctInfo =& $this->director->account_info;
		}
		if (empty($acctInfo)) {
			return false; //if still no account, nothing to check against, return false.
		}
		//Strings::debugLog('acctinfo:'.Strings::debugStr($acctInfo));
		if (!empty($acctInfo['groups']) && (array_search(1,$acctInfo['groups'],true)!==false)) {
			return true; //group 1 is always allowed everything
		}
		//cache the current users permissions
		if (empty($this->_permCache[$acctInfo['account_id']])) {
			try {
				$this->_permCache[$acctInfo['account_id']] = array();
				foreach ($acctInfo['groups'] as $theGroupId) {
					$this->_permCache[$acctInfo['account_id']][$theGroupId] = $this->getAssignedRights($theGroupId);
				}
			} catch (DbException $dbe) {
				//use default empty arrays which will mean all permissions will be not allowed
			}
		}
		//Strings::debugLog('perms:'.Strings::debugStr($this->_permCache));

		//if any group allows the permission, then we allow it.
		foreach ($this->_permCache[$acctInfo['account_id']] as $theGroupId => $theAssignedRights) {
			$theResult = 'disallow';
			if (!empty($theAssignedRights[$aNamespace]) && !empty($theAssignedRights[$aNamespace][$aPermission]))
				$theResult = $theAssignedRights[$aNamespace][$aPermission];
			//if any group the user is a member of allows the permission, then return true
			if ($theResult=='allow') {
				return true;
			}
		}
		//if neither denied, nor allowed, then it is not allowed
		return false;
	}
	
	/**
	 * Query the permissions table for group rights.
	 * @param int $aGroupId - the group id to load.
	 * @return PDOStatement Returns the executed query statement.
	 */
	protected function getGroupRightsCursor($aGroupId) {
		$theParams = array();
		$theParamTypes = array();
		$theSql = "SELECT * FROM {$this->tnPermissions} WHERE group_id = :group_id";
		$theParams['group_id'] = $aGroupId;
		$theParamTypes['group_id'] = PDO::PARAM_INT;
		return $this->query($theSql,$theParams,$theParamTypes);
	}
	
	/**
	 * Load the group rights and merge it into the passed in array;
	 * loaded "deny" rights will trump array param.
	 * @param int $aGroupId - the group id
	 * @param array $aRightsToMerge - the already defined rights.
	 */
	protected function loadAndMergeRights($aGroupId, &$aRightsToMerge) {
		$rs = $this->getGroupRightsCursor($aGroupId);
		if (!empty($rs)) {
			$bFirstSet = empty($aRightsToMerge);
			while (($theRow = $rs->fetch())!==false) {
				$thePermissionValue = ($theRow['value']==self::VALUE_Allow) ? 'allow' : (($bFirstSet) ? 'deny' : 'deny-disable');
				$aRightsToMerge[$theRow['namespace']][$theRow['permission']] = $thePermissionValue;
			}//while
		}//if
	}
	
	/**
	 * Load up all rights assigned to this group as well as parent groups.
	 * @param int $aGroupId - the group to find assigned rights.
	 * @return array Returns the assigned rights for a given group.
	 */
	public function getAssignedRights($aGroupId) {
		$theGroupId = $aGroupId;
		//check rights for group passed in, and then all its parents
		$theMergeList = array($theGroupId => 1);
		$dbAuth = $this->getProp('Auth');
		$theGroupList = Arrays::array_column_as_key($dbAuth->getGroupList(),'group_id');
		while (!empty($theGroupId) && !empty($theGroupList[$theGroupId]) && !empty($theGroupList[$theGroupId]['parent_group_id'])) {
			$theGroupId = $theGroupList[$theGroupId]['parent_group_id'];
			//to prevent infinite loops
			if (empty($theMergeList[$theGroupId]))
				$theMergeList[$theGroupId] = 1;
			else
				$theGroupId = null;
		}//while

		$theAssignedRights = array();
		foreach ($theMergeList as $theMergeGroupId => $dummy) {
			$this->loadAndMergeRights($theMergeGroupId, $theAssignedRights);
		}//foreach
		return $theAssignedRights;
	}
	
	public function modifyGroupRights($aScene) {
		$theGroupId = $aScene->group_id;
		$this->execDML($this->sql_del_group,array('group_id'=>$theGroupId));
		$right_groups = $aScene->getPermissionRes('namespace');
		foreach ($right_groups as $ns => $nsInfo) {
			foreach ($aScene->getPermissionRes($ns) as $theRight => $theRightInfo) {
				$varName = $ns.'__'.$theRight;
				$theAssignment = $aScene->$varName;
				//Strings::debugLog($varName.'='.$theAssignment);
				if ($theAssignment=='allow') {
					$this->query($this->sql_add_right,array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$theGroupId, 'value'=>self::VALUE_Allow));
				} elseif ($theAssignment=='deny') {
					$this->query($this->sql_add_right,array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$theGroupId, 'value'=>self::VALUE_Deny));
				}
			}//end foreach
		}//end foreach
	}

}//end class

}//end namespace
