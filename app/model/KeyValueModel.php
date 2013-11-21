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

namespace com\blackmoonit\bits_theater\app\model;
use com\blackmoonit\bits_theater\app\Director;
use com\blackmoonit\bits_theater\app\Model;
use com\blackmoonit\exceptions\IllegalArgumentException;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\FinallyBlock;
use com\blackmoonit\Strings;
use \ArrayAccess;
use \PDOExeption;
{//namespace begin

abstract class KeyValueModel extends Model implements ArrayAccess {
	const TABLE_NAME = 'map'; //excluding prefix
	const MAPKEY_NAME = 'mapkey';
	protected $_mapdata = array();
	//protected $value_select; auto-created on first use
	//protected $value_update; auto-created on first use
	//protected $value_insert; auto-created on first use
	
	protected function getTableName() {
		return $this->tbl_.static::TABLE_NAME;
	}
	
	public function setup(Director $aDirector, $aDbConn) {
		parent::setup($aDirector, $aDbConn);
		$this->sql_value_select = "SELECT value FROM {$this->getTableName()} WHERE namespace = :ns AND ".
				static::MAPKEY_NAME." = :key";
		$this->sql_value_update = "UPDATE {$this->getTableName()} SET value=:new_value WHERE namespace = :ns AND ".
				static::MAPKEY_NAME." = :key";
		$this->sql_value_insert = "INSERT INTO {$this->getTableName()} ".
				"(namespace, ".static::MAPKEY_NAME.", value, val_def) VALUES (:ns, :key, :value, :default)";
		try {
			$this->value_select = $this->db->prepare($this->sql_value_select);
			$this->value_update = $this->db->prepare($this->sql_value_update);
			$this->value_insert = $this->db->prepare($this->sql_value_insert);
		} catch (DbException $dbe) {
			throw $dbe->setContextMsg('dbError@'.$this->getTableName().".".$aVarName."\n");
		}
	}
	
	public function cleanup() {
		array_walk($this->_mapdata, function(&$n) {$n = null;} );
		parent::cleanup();
	}
	
	//be SURE to override this and call parent:: in descendants!!!
	public function setupModel() {
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->getTableName()} ".
				"( namespace CHAR(40) NULL COLLATE utf8_unicode_ci".
				", ".static::MAPKEY_NAME." CHAR(40) NOT NULL COLLATE utf8_unicode_ci".
				", value NVARCHAR(250) NULL".
				", val_def NVARCHAR(250) NULL".
				", PRIMARY KEY (namespace, ".static::MAPKEY_NAME.")".
				") CHARACTER SET utf8 COLLATE utf8_bin";
		}
		$this->execDML($theSql);
	}
	
	protected function getDefaultData($aScene) {
		//descendants would override this method
	}
	
	public function setupDefaultData($aScene) {
		$default_data = $this->getDefaultData($aScene);
		if (!empty($default_data)) {
			if ($this->isEmpty($this->getTableName())) {
				$this->execMultiDML($this->sql_value_insert,$default_data);
			} else {
				foreach ($default_data as $mapInfo) {
					$this->defineMapValue($mapInfo);
				}
			}
		}
	}
	
	public function splitKeyName($aKey) {
		if (is_array($aKey))
			return $aKey;
		$sa = explode('/',$aKey,2);
		if (empty($sa[1])) {
			return array(null,$sa[0]);
		} else {
			return $sa;
		}
	}
	
	public function defineMapValue($aMapInfo) {
		if ($this->director->canConnectDb() && !empty($aMapInfo)) {
			//make sure map info is ok
			if (!is_array($aMapInfo)) {
				$aMapInfo = array('key'=>$aMapInfo);
			}
			if (!isset($aMapInfo['key'])) 
				return;
			if (!isset($aMapInfo['ns'])) {
				$sa = $this->splitKeyName($aMapInfo['key']);
				$aMapInfo['ns'] = $sa[0];
				$aMapInfo['key'] = $sa[1];
			}
			if (!isset($aMapInfo['value'])) $aMapInfo['value'] = null;
			if (!isset($aMapInfo['default'])) $aMapInfo['default'] = $aMapInfo['value'];
			try {
				$existing_data = $this->getMapData(array($aMapInfo['ns'],$aMapInfo['key']));
				if (empty($existing_data)) {
					$theStatement = $this->value_insert;
					$this->bindValues($theStatement,$aMapInfo);
					$theResult = $theStatement->execute();
					$theStatement->closeCursor();
					return $theResult;
				}
			} catch (DbException $dbe) {
				if ($this->exists($this->getTableName())) {
					throw $dbe->setContextMsg('dbError@'.$this->getTableName().".defineMapValue()\n");
				} else {
					$this->setMapValue(array($aMapInfo['ns'],$aMapInfo['key']),$aMapInfo['default']);
				}
			}
		}
	}

	public function getMapData($aKey) {
		$theResult = null;
		try {
			$sa = $this->splitKeyName($aKey);
			$theStatement = $this->value_select;
			$this->bindValues($theStatement,array('ns'=>$sa[0],'key'=>$sa[1]));
			if ($theStatement->execute()) {
				$theResult = $theStatement->fetch();
			}
			$theStatement->closeCursor();
		} catch (PDOException $e) {
			if ($this->exists($this->getTableName())) {
				throw new DbException($e,'dbError@'.$this->getTableName().".getMapValue($aKey)\n");
			}
		}
		return $theResult;
	}

	public function getMapValue($aKey) {
		if (empty($this->_mapdata[$aKey])) {
			$row = $this->getMapData($aKey);
			$this->_mapdata[$aKey] = (isset($row['value']))?$row['value']:'';
		}
		//Strings::debugLog('key='.$aKey.' val='.$this->_mapdata[$aKey]);
		return $this->_mapdata[$aKey];
	}

	public function setMapValue($aKey, $aNewValue) {
		$old_value = $this->getMapValue($aKey);
		if ($old_value != $aNewValue) {
			$this->_mapdata[$aKey] = $aNewValue;
			$theFinally = new FinallyBlock(function($aModel) {
				if (!is_null($aModel->value_update)) {
					$aModel->value_update->closeCursor();
				}
				if (!is_null($aModel->value_insert)) {
					$aModel->value_insert->closeCursor();
				}
			},$this);
			if (!is_null($this->value_update) && !is_null($this->value_insert)) try {
				$sa = $this->splitKeyName($aKey);
				$this->bindValues($this->value_update,array('ns'=>$sa[0], 'key'=>$sa[1], 'new_value'=>$aNewValue));
				$this->value_update->execute();
				if ($this->value_update->rowCount()<1) {
					if ($this->isNoResults($this->sql_value_select,array('ns'=>'namespace','key'=>$sa[0]))) {
						$this->execDML($this->sql_value_insert,
								array('ns'=>'namespace','key'=>$sa[0],'value'=>null,'default'=>null));
					}
					$this->bindValues($this->value_insert,array('ns'=>$sa[0],'key'=>$sa[1],'value'=>$aNewValue,'default'=>''));
					$this->value_insert->execute();
				}
			} catch (PDOException $e) {
				if ($this->exists($this->getTableName())) {
					throw new DbException($e2,'dbError@'.$this->getTableName().".setMapValue($aKey,$aNewValue)\n");
				}
			}
		}
	}

	//----- methods required for various IMPLEMENTS interfaces
	
	public function offsetSet($aKey, $aValue) {
		if (!empty($aKey)) {
			$this->setMapValue($aKey,$aValue);
		} else {
			throw new IllegalArgumentException('key required, v:'.$aValue);
		}
	}

	public function offsetExists($aKey) {
		$r = $this->getMapValue($aKey);
		return (!empty($r));
	}

	public function offsetUnset($aKey) {
		$this->setMapValue($aKey,null);
	}

	public function offsetGet($aKey) {
		return $this->getMapValue($aKey);
	}

	//----- IMPLEMENTS handled -----
	
	public function exists($aTableName = NULL) {
		if (empty($aTableName)) {
			$aTableName = $this->getTableName();
		}
		return parent::exists($aTableName);
	}

}//end class

}//end namespace
