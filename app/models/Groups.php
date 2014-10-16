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
use com\blackmoonit\Arrays;
use \PDO;
{//begin namespace

class Groups extends BaseModel {
	public $tnGroups;			const TABLE_Groups = 'groups';
	public $tnGroupMap;			const TABLE_GroupMap = 'groups_map';
	public $tnGroupRegCodes;	const TABLE_GroupRegCodes = 'groups_reg_codes';

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnGroups = $this->tbl_.self::TABLE_Groups;
		$this->tnGroupMap = $this->tbl_.self::TABLE_GroupMap;
		$this->tnGroupRegCodes = $this->tbl_.self::TABLE_GroupRegCodes;
	}
	
	protected function getTableName() {
		return $this->tnGroups;
	}
	
	public function setupModel() {
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnGroups} ".
				"( group_id INT NOT NULL AUTO_INCREMENT".
				", group_name NCHAR(60) NOT NULL".
				", parent_group_id INT NULL".
				//", group_desc NCHAR(200) NULL".
				", PRIMARY KEY (group_id)".
				") CHARACTER SET utf8 COLLATE utf8_general_ci";
		}
		$r = $this->execDML($theSql);
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnGroupMap} ".
				"( account_id INT NOT NULL".
				", group_id INT NOT NULL".
				", PRIMARY KEY (account_id, group_id)".
				//", UNIQUE KEY (group_id, account_id)".  IDK if it'd be useful
				") CHARACTER SET utf8 COLLATE utf8_general_ci";
		}
		$r = $this->execDML($theSql);
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnGroupRegCodes} ".
				"( group_id INT NOT NULL".
				", reg_code NCHAR(64) NOT NULL".
				", PRIMARY KEY (reg_code, group_id)".
				") CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT='Auto-assign group_id if Registration Code matches reg_code'";
		}
		$r = $this->execDML($theSql);
	}
	
	public function setupDefaultData($aScene) {
		if ($this->isEmpty()) {
			$group_names = $aScene->getRes('setupDefaultData/group_names');
			$default_data = array(
					array('group_id'=>1, 'group_name'=>$group_names[1],),
					array('group_id'=>2, 'group_name'=>$group_names[2],),
					array('group_id'=>3, 'group_name'=>$group_names[3],),
					array('group_id'=>4, 'group_name'=>$group_names[4],),
					array('group_id'=>5, 'group_name'=>$group_names[0],),
			);
			$theSql = "INSERT INTO {$this->tnGroups} ".
					"(group_id, group_name) VALUES (:group_id, :group_name)";
			$theParamTypes = array('group_id'=>PDO::PARAM_INT, 'group_name'=>PDO::PARAM_STR,);
			$this->execMultiDML($theSql,$default_data,$theParamTypes);
			//set group_id 5 to 0, cannot set 0 on insert since auto-inc columns in MySQL interpret 0 as "next id" instead of just 0.
			$theSql = 'UPDATE '.$this->tnGroups.' SET group_id=0 WHERE group_id=5';
			$this->execDML($theSql);
		}
	}
	
	public function isEmpty($aTableName=null) {
		if ($aTableName==null)
			$aTableName = $this->tnGroups;
		return parent::isEmpty($aTableName);
	}
	
	public function getGroup($aGroupId) {
		$theParams = array();
		$theParamTypes = array();
		$theSql = "SELECT * FROM {$this->tnGroups} WHERE group_id = :group_id";
		$theParams['group_id'] = $aGroupId;
		$theParamTypes['group_id'] = PDO::PARAM_INT;
		$rs = $this->query($theSql,$theParams,$theParamTypes);
		return $rs->fetch();
	}
	
	public function add($aGroupData) {
		if (empty($aGroupData))
			return;
		$cols = array_keys($aGroupData);
		$theSql = "INSERT INTO {$this->tnGroups} (".implode(', ',$cols).") VALUES (:".implode(', :',$cols).")";
		return $this->execDML($theSql,$aGroupData);
	}
	
	public function del($aId) {
		if ($aId>1) {
			$theSql = "DELETE FROM {$this->tnGroupMap} WHERE group_id=$aId ";
			$this->execDML($theSql);
			$theSql = "DELETE FROM {$this->tnGroups} WHERE group_id=$aId ";
			$this->execDML($theSql);
		}
	}
	
	public function addAcctMap($aGroupId, $aAcctId) {
		$theSql = "INSERT INTO {$this->tnGroupMap} (account_id,group_id) VALUES ($aAcctId,$aGroupId)";
		return $this->execDML($theSql);
	}
	
	public function delAcctMap($aGroupId, $aAcctId) {
		$theSql = "DELETE FROM {$this->tnGroupMap} WHERE account_id=$aAcctId AND group_id=$aGroupId";
		return $this->execDML($theSql);
	}
	
	public function getAcctGroups($aAcctId) {
		$theParams = array();
		$theParamTypes = array();
		$theSql = "SELECT group_id FROM {$this->tnGroupMap} WHERE account_id = :acct_id";
		$theParams['acct_id'] = $aAcctId;
		$theParamTypes['acct_id'] = PDO::PARAM_INT;
		$rs = $this->query($theSql,$theParams,$theParamTypes);
		$theResult = $rs->fetchAll(PDO::FETCH_COLUMN,0);
		foreach ($theResult as &$theGroupId) {
			$theGroupId += 0;
		}
		return $theResult;
	}
	
	public function createGroup($aGroupName, $aGroupParentId, $aGroupRegCode) {
		$theSql = "INSERT INTO {$this->tnGroups} (group_name,parent_group_id) VALUES ('$aGroupName',$aGroupParentId)";
		$theNewGroupId = $this->addAndGetId($theSql);
		if (!empty($aGroupRegCode)) {
			$theRegCode = substr($aGroupRegCode,0,64);
			$theSql = "INSERT INTO {$this->tnGroupRegCodes} (group_id,reg_code) VALUES ($theNewGroupId,'$theRegCode')";
			$theNewGroupId = $this->addAndGetId($theSql);
		}
	}

	public function modifyGroup($aScene) {
		$v = &$aScene;
		if (!empty($this->db) && isset($v->group_id) && $v->group_id>=0 && $v->group_id!=1) {
			try {
				$theParams = array();
				$theParamTypes = array();
				$theSql = 'UPDATE '.$this->tnGroups;
				$theSql .= ' SET group_name=:group_name, parent_group_id=:parent_group_id';
				$theSql .= ' WHERE group_id=:group_id';
				$theParams['group_id'] = $v->group_id;
				$theParamTypes['group_id'] = PDO::PARAM_INT;
				$theParams['group_name'] = $v->group_name;
				$theParamTypes['group_name'] = PDO::PARAM_STR;
				$theParams['parent_group_id'] = $v->group_parent;
				$theParamTypes['parent_group_id'] = PDO::PARAM_INT;
				$this->execDML($theSql, $theParams, $theParamTypes);

				$theSql = "DELETE FROM {$this->tnGroupRegCodes} WHERE group_id=:group_id";
				$this->execDML($theSql, array('group_id'=>$v->group_id), array('group_id'=>PDO::PARAM_INT));
								
				if (!empty($v->group_reg_code)) {
					$theRegCode = substr($v->group_reg_code,0,64);
					$theSql = "INSERT INTO {$this->tnGroupRegCodes} (group_id,reg_code) VALUES ({$v->group_id},'{$theRegCode}')";
					$theNewGroupId = $this->addAndGetId($theSql);
				}
			} catch (PDOException $pdoe) {
				throw new DbException($pdoe, 'modifyGroup() failed.');
			}
		}
	}
	
	public function getGroupRegCodes() {
		$theSql = "SELECT * FROM {$this->tnGroupRegCodes} ORDER BY group_id";
		$ps = $this->query($theSql);
		$theResult = Arrays::array_column_as_key($ps->fetchAll(), 'group_id');
		return $theResult;
	}
	
	public function findGroupIdByRegCode($aAppId, $aRegCode) {
		if (!$this->isEmpty($this->tnGroupRegCodes)) {
			$theParams = array();
			$theParamTypes = array();
			$theSql = "SELECT group_id FROM {$this->tnGroupRegCodes} WHERE reg_code = :reg_code";
			$theParams['reg_code'] = $aRegCode;
			$theParamTypes['reg_code'] = PDO::PARAM_STR;
			$theRow = $this->getTheRow($theSql,$theParams,$theParamTypes);
			return (!empty($theRow)) ? $theRow['group_id'] : 0;
		} else {
			return ($aRegCode==$aAppId) ? 3 : 0;
		}
	}

}//end class

}//end namespace
