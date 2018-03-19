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
use BitsTheater\costumes\colspecs\CommonMySql;
use com\blackmoonit\exceptions\IllegalArgumentException;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\FinallyBlock;
use ArrayAccess;
{//namespace begin

abstract class KeyValueModel extends BaseModel implements ArrayAccess {
	const TABLE_NAME = 'map'; //excluding prefix
	const MAPKEY_NAME = 'mapkey';
	protected $_mapcached = array();
	protected $_mapdata = array();
	protected $_mapdefault = array();
	//protected $value_select; auto-created on first use
	//protected $value_update; auto-created on first use
	//protected $value_insert; auto-created on first use
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['_mapcached']);
		unset($vars['_mapdata']);
		unset($vars['_mapdefault']);
		unset($vars['sql_value_select']);
		unset($vars['sql_value_update']);
		unset($vars['sql_value_insert']);
		unset($vars['value_select']);
		unset($vars['value_update']);
		unset($vars['value_insert']);
		return $vars;
	}
	
	protected function getTableName() {
		return $this->tbl_.static::TABLE_NAME;
	}
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->sql_value_select = "SELECT * FROM {$this->getTableName()} WHERE namespace = :ns AND ".
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
		array_walk($this->_mapcached, function(&$n) {$n = null;} );
		array_walk($this->_mapdata, function(&$n) {$n = null;} );
		array_walk($this->_mapdefault, function(&$n) {$n = null;} );
		parent::cleanup();
	}
	
	//be SURE to override this and call parent:: in descendants!!!
	public function setupModel() {
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->getTableName()} ".
				"( namespace CHAR(40) NOT NULL".
				", ".static::MAPKEY_NAME." CHAR(40) NOT NULL".
				", value NVARCHAR(250) NULL".
				", val_def NVARCHAR(250) NULL".
				", PRIMARY KEY (namespace, ".static::MAPKEY_NAME.")".
				') ' . CommonMySql::TABLE_SPEC_FOR_UNICODE;
		}
		$this->execDML($theSql);
		$this->debugLog($this->getRes('install/msg_create_table_x_success/'.$this->getTableName()));
	}
	
	protected function getDefaultData($aScene) {
		//descendants would override this method
		return array();
	}
	
	public function setupDefaultData($aScene) {
		$default_data = $this->getDefaultData($aScene);
		if (!empty($default_data)) {
			if ($this->isEmpty($this->getTableName())) {
				try {
					$this->execMultiDML($this->sql_value_insert,$default_data);
				} catch (DbException $dbe) {
					throw $dbe->setContextMsg('dbError@'.$this->getTableName().".setupDefaultData()");
				}
			} else {
				foreach ($default_data as $mapInfo) {
					$this->defineMapValue($mapInfo);
				}
			}
		}
	}
	
	//----- methods required for various IMPLEMENTS interfaces
	
	public function offsetSet($aNsKey, $aValue) {
		if (!empty($aNsKey)) {
			$this->setMapValue($aNsKey,$aValue);
		} else {
			throw new IllegalArgumentException('key required, v:'.$aValue);
		}
	}

	public function offsetExists($aNsKey) {
		$r = $this->getMapValue($aNsKey);
		return (isset($r));
	}

	public function offsetUnset($aNsKey) {
		$this->setMapValue($aNsKey,null);
	}

	public function offsetGet($aNsKey) {
		return $this->getMapValue($aNsKey);
	}

	//----- IMPLEMENTS handled -----
	
	public function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->getTableName() : $aTableName );
	}

	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->getTableName() : $aTableName );
	}
	
	/**
	 * Convert whatever is passed in to the standard array['ns','key'] format.
	 * @param array|string $aNsKey - either an array[0,1], array['ns','key'] or string format.
	 * @return array Returns an array['ns','key'] filled appropriately.
	 */
	public function splitKeyName($aNsKey) {
		$theResult = array('ns' => null, 'key' => null);
		if (is_array($aNsKey)) {
			$theResult['ns'] = (!empty($aNsKey['ns'])) ? $aNsKey['ns'] : array_shift($aNsKey);
			$theResult['key'] = (!empty($aNsKey['key'])) ? $aNsKey['key'] : array_shift($aNsKey);
		} else {
			if (strpos($aNsKey, '/')>=0)
				list($theResult['ns'], $theResult['key']) = explode('/',$aNsKey,2);
			else
				$theResult['key'] = $aNsKey;
		}
		return $theResult;
	}
	
	/**
	 * Given the output of splitKeyName(), return the string name representing it.
	 * @param array $aNsKeyArray - array['ns', 'key] format.
	 * @return string Returns the "ns/key" string format.
	 */
	public function implodeKeyName(array $aNsKeyArray) {
		return ((!empty($aNsKeyArray['ns'])) ? $aNsKeyArray['ns'].'/' : '').$aNsKeyArray['key'];
	}
	
	/**
	 * Given the data returned by the getMapData() function, should this data
	 * be inserted into the model's table or not.  Descendants might need
	 * some crazy criteria besides if empty() or not.
	 * @param array $aMapData - associative array of map data.
	 * @return boolean Returns TRUE if the data should be inserted into the table.
	 */
	protected function shouldInsertMapData($aMapData) {
		return empty($aMapData);
	}
	
	/**
	 * Set up a ns/key for the first time.
	 * @param array|string $aMapInfo - array with 'ns' and 'key' keys or an "ns/key" string;
	 * typcially, this should be array[ns, key, value, default].
	 * @throws DbException only if the table already exists.
	 * @return boolean|NULL Returns TRUE if inserted, FALSE if already exists, NULL when cache-only.
	 */
	public function defineMapValue($aMapInfo) {
		if (!empty($this->db) && !empty($aMapInfo)) {
			//make sure map info is ok
			if (is_string($aMapInfo))
				$aMapInfo = $this->splitKeyName($aMapInfo);
			if (!isset($aMapInfo['key']))
				return;
			if (!array_key_exists('value', $aMapInfo))
				$aMapInfo['value'] = null;
			if (!array_key_exists('default',$aMapInfo))
				$aMapInfo['default'] = $aMapInfo['value'];
			try {
				$theMapData = $this->getMapData($aMapInfo);
				if ($this->shouldInsertMapData($theMapData)) {
					$this->bindValues($this->value_insert,$aMapInfo);
					$this->value_insert->execute();
					$this->value_insert->closeCursor(); //close the cursor so we can re-use the PDOStatement
					return true;
				} else {
					return false;
				}
			} catch (\PDOException $dbe) {
				if ($this->exists($this->getTableName())) {
					throw new DbException($e,'dbError@'.$this->getTableName().' '.__METHOD__."({$this->debugStr($aMapInfo)})\n");
				} else {
					$this->setMapValue($aMapInfo,$aMapInfo['default']);
					return null;
				}
			}
		}
	}
	
	/**
	 * Get map data from the db table.
	 * @param array|string $aNsKey - array with 'ns' and 'key' keys or an "ns/key" string.
	 * @throws DbException only if the table already exists.
	 * @return array|NULL Returns the table data or NULL if not found.
	 */
	public function getMapData($aNsKey) {
		$theResult = null;
		try {
			$this->bindValues($this->value_select, $this->splitKeyName($aNsKey));
			if ($this->value_select->execute()) {
				$theResult = $this->value_select->fetch();
				if (empty($theResult))
					$theResult = null;
			}
			$this->value_select->closeCursor(); //close the cursor so we can re-use the PDOStatement
		} catch (\PDOException $e) {
			if ($this->exists($this->getTableName())) {
				throw new DbException($e,'dbError@'.$this->getTableName().' '.__METHOD__."($aNsKey)\n");
			}
		}
		return $theResult;
	}
	
	/**
	 * Get map data by first checking cached value, then get from db.
	 * @param array|string $aNsKey - array with 'ns' and 'key' keys or an "ns/key" string.
	 * @throws DbException only if the table already exists.
	 * @return string Returns the value.
	 */
	public function getMapValue($aNsKey) {
		$theNsKey = $this->splitKeyName($aNsKey);
		$theNsKeyStr = $this->implodeKeyName($theNsKey);
		if (empty($this->_mapcached[$theNsKeyStr])) {
			$row = $this->getMapData($theNsKey);
			$this->_mapcached[$theNsKeyStr] = 1;
			$this->_mapdata[$theNsKeyStr] = (isset($row['value'])) ? $row['value'] : null;
			$this->_mapdefault[$theNsKeyStr] = (isset($row['val_def'])) ? $row['val_def'] : null;
		}
		//$this->debugLog('key='.$aKey.' val='.$this->_mapdata[$aKey]);
		return $this->_mapdata[$theNsKeyStr];
	}
	
	/**
	 * Get the default value for a particular ns/key.
	 * @param array|string $aNsKey - array with 'ns' and 'key' keys or an "ns/key" string.
	 * @throws DbException only if the table already exists.
	 * @return string Returns the default value.
	 */
	public function getMapDefault($aNsKey) {
		$this->getMapValue($aNsKey); //ensure data is loaded
		$theNsKeyStr = $this->implodeKeyName($this->splitKeyName($aNsKey));
		return (isset($this->_mapdefault[$theNsKeyStr])) ? $this->_mapdefault[$theNsKeyStr] : '';
	}
	
	/**
	 * Sets the value for the ns/key and saves it in the db.
	 * @param array|string $aNsKey - array with 'ns' and 'key' keys or an "ns/key" string.
	 * @param string $aNewValue - the value to save.
	 * @throws DbException only if the table already exists.
	 */
	public function setMapValue($aNsKey, $aNewValue) {
		$theNsKey = $this->splitKeyName($aNsKey);
		$theNsKeyStr = $this->implodeKeyName($theNsKey);
		$old_value = $this->getMapValue($aNsKey);
		if ($old_value !== $aNewValue) {
			//$this->debugLog(__METHOD__.' o='.$this->debugStr($old_value).' n='.$this->debugStr($aNewValue));
			$this->_mapdata[$theNsKeyStr] = $aNewValue;
			$theFinally = new FinallyBlock(function($aModel) {
				if (!is_null($aModel->value_update)) {
					$aModel->value_update->closeCursor();
				}
				if (!is_null($aModel->value_insert)) {
					$aModel->value_insert->closeCursor();
				}
			},$this);
			if (!is_null($this->value_update) && !is_null($this->value_insert)) try {
				$this->bindValues($this->value_update,array(
						'ns' => $theNsKey['ns'],
						'key' => $theNsKey['key'],
						'new_value' => $aNewValue,
				));
				$bExecResult = $this->value_update->execute();
				$numRowsUpdated = ($bExecResult) ? $this->value_update->rowCount() : 0;
				if ($numRowsUpdated<1) {
					//$this->debugLog(__METHOD__.' update count='.$numRowsUpdated.', inserting: '.$this->debugStr($theNsKey).', '.$aNewValue);
					if ($this->isNoResults($this->sql_value_select, array('ns' => 'namespace', 'key' => $theNsKey['ns']))) {
						$this->execDML($this->sql_value_insert,	array(
								'ns' => 'namespace',
								'key' => $theNsKey['ns'],
								'value' => null,
								'default' => null,
						));
					}
					$this->bindValues($this->value_insert, array(
							'ns' => $theNsKey['ns'],
							'key' => $theNsKey['key'],
							'value' => $aNewValue,
							'default' => (isset($this->_mapdefault[$theNsKeyStr])) ? $this->_mapdefault[$theNsKeyStr] : '',
					));
					$this->value_insert->execute();
				}
			} catch (\PDOException $e) {
				if ($this->exists($this->getTableName())) {
					throw new DbException($e2,'dbError@'.$this->getTableName().' '.__METHOD__."({$this->debugStr($aNsKey)},{$aNewValue})\n");
				}
			}
		}
	}

}//end class

}//end namespace
