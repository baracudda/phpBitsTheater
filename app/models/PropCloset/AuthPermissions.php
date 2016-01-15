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

namespace BitsTheater\models\PropCloset;
use BitsTheater\Model as BaseModel;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\models\Auth;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use com\blackmoonit\Arrays;
use PDO;
use PDOStatement;
use PDOException;
{//namespace begin

class AuthPermissions extends BaseModel {
	const VALUE_Allow = '+';
	const VALUE_Deny = 'x';
	const FORM_VALUE_Allow = 'allow';
	const FORM_VALUE_Deny = 'deny';
	const FORM_VALUE_Disallow = 'disallow';
	const FORM_VALUE_DoNotShow = 'deny-disable';

	public $tnPermissions; const TABLE_Permissions = 'permissions';

	public $_permCache = array();

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnPermissions = $this->tbl_.self::TABLE_Permissions;
	}
	
	/**
	 * Future db schema updates may need to create a temp table of one
	 * of the table definitions in order to update the contained data,
	 * putting schema here and supplying a way to provide a different name
	 * allows this process.
	 * @param string $aTABLEconst - one of the defined table name consts.
	 * @param string $aTableNameToUse - (optional) alternate name to use.
	 */
	protected function getTableDefSql($aTABLEconst, $aTableNameToUse=null) {
		switch($aTABLEconst) {
		case self::TABLE_Permissions:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnPermissions;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( namespace CHAR(40) NULL".
						", permission CHAR(40) NOT NULL".
						", group_id INT NOT NULL".
						", value CHAR(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL".
						", PRIMARY KEY (namespace, permission, group_id)".
						", KEY IdxValuePermissions (namespace, value)".
						", UNIQUE KEY IdxGroupPermissions (group_id, namespace, permission)".
						") CHARACTER SET utf8 COLLATE utf8_general_ci";
			}//switch dbType
		}//switch TABLE const
	}
	
	/**
	 * Called during website installation to create whatever the models needs.
	 * Check the database to be sure anything needs to be done and do not assume
	 * a blank database as updates/reinstalls against recovered databases may
	 * occur as well.
	 * @throws DbException
	 */
	public function setupModel() {
		switch ($this->dbType()) {
		case self::DB_TYPE_MYSQL: default:
			try {
				$theSql = $this->getTableDefSql(self::TABLE_Permissions);
				$this->execDML($theSql);
				$this->debugLog($this->getRes('install/msg_create_table_x_success/'.$this->tnPermissions));
			} catch (PDOException $pdoe){
				throw new DbException($pdoe,$theSql);
			}
			break;
		}
	}
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnPermissions : $aTableName );
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnPermissions : $aTableName );
	}
	
	/**
	 * Check group permissions to see if current user account (or passed in one) is allowed.
	 * @param string $aNamespace - namespace of permission.
	 * @param string $aPermission - permission to test against.
	 * @param array $acctInfo - account information (optional, defaults current user).
	 * @return boolean Returns TRUE if allowed, else FALSE.
	 */
	public function isPermissionAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($acctInfo)) {
			$acctInfo =& $this->director->account_info;
		}
		if (empty($acctInfo)) {
			$acctInfo = new AccountInfoCache();
		}
		//Strings::debugLog('acctinfo:'.Strings::debugStr($acctInfo));
		if (!empty($acctInfo->groups) && (array_search(1,$acctInfo->groups,true)!==false)) {
			return true; //group 1 is always allowed everything
		}
		//cache the current users permissions
		if (empty($this->_permCache[$acctInfo->account_id])) {
			try {
				$this->_permCache[$acctInfo->account_id] = array();
				foreach ($acctInfo->groups as $theGroupId) {
					$this->_permCache[$acctInfo->account_id][$theGroupId] = $this->getAssignedRights($theGroupId);
				}
			} catch (DbException $dbe) {
				//use default empty arrays which will mean all permissions will be not allowed
			}
		}
		//$this->debugLog('perms:'.$this->debugStr($this->_permCache));
        //$this->debugLog(__METHOD__.' '.memory_get_usage(true));

		//if any group allows the permission, then we allow it.
		foreach ($this->_permCache[$acctInfo->account_id] as $theGroupId => $theAssignedRights) {
			$theResult = self::FORM_VALUE_Disallow;
			if (!empty($theAssignedRights[$aNamespace]) && !empty($theAssignedRights[$aNamespace][$aPermission]))
				$theResult = $theAssignedRights[$aNamespace][$aPermission];
			//if any group the user is a member of allows the permission, then return true
			if ($theResult==self::FORM_VALUE_Allow) {
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
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'group_id' => $aGroupId,
		));
		$theSql->startWith('SELECT * FROM')->add($this->tnPermissions);
		$theSql->startWhereClause()->mustAddParam('group_id', 0, PDO::PARAM_INT)->endWhereClause();
		return $theSql->query();
	}
	
	/**
	 * Load the group rights and merge it into the passed in array;
	 * loaded "deny" rights will trump array param.
	 * @param int $aGroupId - the group id
	 * @param array $aRightsToMerge - the already defined rights.
	 */
	protected function loadAndMergeRights($aGroupId, &$aRightsToMerge, $bIsFirstSet) {
		$rs = $this->getGroupRightsCursor($aGroupId);
		if (!empty($rs)) {
			while (($theRow = $rs->fetch())!==false) {
				$thePermissionValue = ($theRow['value']==self::VALUE_Allow)
						? self::FORM_VALUE_Allow
						: (($bIsFirstSet) ? self::FORM_VALUE_Deny : self::FORM_VALUE_DoNotShow);
				$theCurrValue =& $aRightsToMerge[$theRow['namespace']][$theRow['permission']];
				if (empty($theCurrValue) || $theCurrValue==self::FORM_VALUE_Allow)
					$theCurrValue = $thePermissionValue;
			}//while
		}//if
	}
	
	/**
	 * Load up all rights assigned to this group as well as parent groups.
	 * @param int $aGroupId - the group to find assigned rights.
	 * @return array Returns the assigned rights for a given group.
	 */
	public function getAssignedRights($aGroupId) {
		$theGroupId = $aGroupId+0;
		//check rights for group passed in, and then all its parents
		$theMergeList = array($theGroupId => -1);
		/* @var $dbAuth Auth */
		$dbAuth = $this->getProp('Auth');
		$theGroupList = Arrays::array_column_as_key($dbAuth->getGroupList(),'group_id');
		//$this->debugLog('grouplist='.$this->debugStr($theGroupList));
		while ($theGroupId>=0 && !empty($theGroupList[$theGroupId]) && !empty($theGroupList[$theGroupId]['parent_group_id'])) {
			$theGroupId = $theGroupList[$theGroupId]['parent_group_id'];
			//to prevent infinite loops
			if (empty($theMergeList[$theGroupId]))
				$theMergeList[$theGroupId] = 1;
			else
				$theGroupId = null;
			//$this->debugLog('merge='.$this->debugStr($theMergeList));
		}//while

		$theAssignedRights = array();
		foreach ($theMergeList as $theMergeGroupId => $bIsFirstIfNegative) {
			$this->loadAndMergeRights($theMergeGroupId, $theAssignedRights, $bIsFirstIfNegative<0);
		}//foreach
		//$this->debugLog('rights='.$this->debugStr($theAssignedRights));
		return $theAssignedRights;
	}
	
	/**
	 * Modify the saved permissions for a particular group.
	 * @param Scene $aScene
	 */
	public function modifyGroupRights($aScene) {
		//remove existing permissions
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aScene);
		$theSql->startWith('DELETE FROM')->add($this->tnPermissions);
		$theSql->startWhereClause()->mustAddParam('group_id')->endWhereClause();
		$theSql->execDML();
		
		//add new permissions
		$theRightsList = array();
		$theRightGroups = $aScene->getPermissionRes('namespace');
		foreach ($theRightGroups as $ns => $nsInfo) {
			foreach ($aScene->getPermissionRes($ns) as $theRight => $theRightInfo) {
				$varName = $ns.'__'.$theRight;
				$theAssignment = $aScene->$varName;
				//Strings::debugLog($varName.'='.$theAssignment);
				if ($theAssignment==self::FORM_VALUE_Allow) {
					array_push($theRightsList, array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$aScene->group_id, 'value'=>self::VALUE_Allow) );
				} else if ($theAssignment==self::FORM_VALUE_Deny) {
					array_push($theRightsList, array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$aScene->group_id, 'value'=>self::VALUE_Deny) );
				}
			}//end foreach
		}//end foreach
		if (!empty($theRightsList)) {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aScene);
			$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
			$theSql->add('(namespace, permission, group_id, value) VALUES (:ns, :perm, :group_id, :value)');
			$theSql->execMultiDML($theRightsList);
		}
	}

}//end class

}//end namespace
