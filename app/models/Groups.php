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
{

class Groups extends BaseModel {
	const GROUPTYPE_guest = 0;
	const GROUPTYPE_titan = 1;
	const GROUPTYPE_admin = 2;
	const GROUPTYPE_privileged = 3;
	const GROUPTYPE_restricted = 4;

	public $tnGroups;
	public $tnGroupMap;
	protected $get_group;
	protected $get_map_groups;
	protected $get_map_accts;

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnGroups = $this->tbl_.'groups';
		$this->tnGroupMap = $this->tbl_.'groups_map';
		try {
			$this->get_group = "SELECT * FROM {$this->tnGroups} WHERE group_id = :group_id";
			$this->get_map_groups = "SELECT account_id FROM {$this->tnGroupMap} WHERE group_id = :group_id";
			$this->get_map_accts = "SELECT group_id FROM {$this->tnGroupMap} WHERE account_id = :acct_id";
		} catch (DbException $dbe) {
			if ($this->exists($this->tnGroups) && $this->exists($this->tnGroupMap)) {
				throw $dbe->setContextMsg("dbError@groups.setup()\n");
			}
		}
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
				", group_type SMALLINT NOT NULL DEFAULT 0".
				", parent_group_id INT NULL".
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
	}
	
	public function setupDefaultData($aScene) {
		if ($this->isEmpty()) {
			$group_names = $aScene->getRes('setupDefaultData/group_names');
			$default_data = array(
					array('group_name'=>$group_names[1],'group_type'=>self::GROUPTYPE_titan),
					array('group_name'=>$group_names[2],'group_type'=>self::GROUPTYPE_admin),
					array('group_name'=>$group_names[3],'group_type'=>self::GROUPTYPE_privileged),
					array('group_name'=>$group_names[4],'group_type'=>self::GROUPTYPE_restricted),
					array('group_name'=>$group_names[5],'group_type'=>self::GROUPTYPE_guest),
			);
			$theSql = "INSERT INTO {$this->tnGroups} ".
					"(group_name, group_type) VALUES (:group_name, :group_type)";
			$theParamTypes = array('group_name'=>\PDO::PARAM_STR,'group_type'=>\PDO::PARAM_INT);
			return $this->execMultiDML($theSql,$default_data,$theParamTypes);
		}
	}
	
	public function isEmpty($aTableName=null) {
		if ($aTableName==null)
			$aTableName = $this->tnGroups;
		return parent::isEmpty($aTableName);
	}
	
	public function getGroup($aId) {
		$rs = $this->query($this->get_group,array('group_id'=>$aId));
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
		$r = $this->query($this->get_map_accts,array('acct_id'=>$aAcctId));
		$theResult = array();
		while (($row = $r->fetch()) !== false) {
			$theResult[] = intval($row['group_id']);
		}
		return $theResult;
	}

}//end class

}//end namespace
