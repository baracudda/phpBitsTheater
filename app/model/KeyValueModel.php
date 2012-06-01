<?php
namespace app\model; 
use app\Model;
use app\DbException;
{//namespace begin

abstract class KeyValueModel extends Model implements \ArrayAccess {
	const TABLE_NAME = 'map'; //excluding prefix
	protected $_mapdata = array();
	//protected $value_select; auto-created on first use
	//protected $value_update; auto-created on first use
	//protected $value_insert; auto-created on first use
	
	protected function getTableName() {
		return $this->tbl_.static::TABLE_NAME;
	}
	
	public function setup($aDbConn) {
		parent::setup($aDbConn);
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
				", mapkey CHAR(40) NOT NULL COLLATE utf8_unicode_ci".
				", value NVARCHAR(250) NULL".
				", val_def NVARCHAR(250) NULL".
				", PRIMARY KEY (namespace, mapkey)".
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
				$theSql = "INSERT INTO {$this->getTableName()} ".
						"(namespace, mapkey, value, val_def) VALUES (:ns, :key, :value, :default)";
				$this->execMultiDML($theSql,$default_data);
			} else {
				foreach ($default_data as $mapInfo) {
					$this->defineMapValue($mapInfo);
				}
			}
		}
	}
	
	public function __get($aVarName) {
		try {
			if ($aVarName=='value_select') {
				$theSql = "SELECT value FROM {$this->getTableName()} WHERE namespace = :ns AND mapkey = :key";
				$this->$aVarName = $theSql;
				return $this->$aVarName;
			} elseif ($aVarName=='value_update') {
				$theSql = "UPDATE {$this->getTableName()} SET value=:new_value WHERE namespace = :ns AND mapkey = :key";
				$this->$aVarName = $theSql;
				return $this->$aVarName;
			} elseif ($aVarName=='value_insert') {
				$theSql = "INSERT INTO {$this->getTableName()} ".
						"(namespace, mapkey, value, val_def) VALUES (:ns, :key, :value, :default)";
				$this->$aVarName = $theSql;
				return $this->$aVarName;
			}
		} catch (DbException $dbe) {
			throw $dbe->setContextMsg('dbError@'.$this->getTableName().".".$aVarName."\n");
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
					return $this->execDML($this->value_insert,$aMapInfo);
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
		$sa = $this->splitKeyName($aKey);
		$sa[0] = $this->quote($sa[0],'text');
		$sa[1] = $this->quote($sa[1],'text');
		$theSql = "SELECT * FROM {$this->getTableName()} WHERE namespace = {$sa[0]} AND mapkey = {$sa[1]}";
		return $this->getTheRow($theSql);
	}

	public function getMapValue($aKey) {
		//Strings::debugLog($aKey);
		if (empty($this->_mapdata[$aKey])) try {
			$sa = $this->splitKeyName($aKey);
			$rs = $this->query($this->value_select,array('ns'=>$sa[0], 'key'=>$sa[1]));
			$row = $rs->fetchRow();
			$this->_mapdata[$aKey] = (isset($row['value']))?$row['value']:'';
			$rs->closeCursor();
		} catch (DbException $dbe) {
			if ($this->exists($this->getTableName())) {
				throw $dbe->setContextMsg('dbError@'.$this->getTableName().".getMapValue($aKey)\n");
			} else {
				return null;
			}
		}
		return $this->_mapdata[$aKey];
	}

	public function setMapValue($aKey, $aNewValue) {
		$this->_mapdata[$aKey] = $aNewValue;
		if (is_null($this->value_update))
			return;
		$sa = $this->splitKeyName($aKey);
		try {
			$rs = $this->query($this->value_select,array('ns'=>$sa[0], 'key'=>$sa[1]));
			$row = $rs->fetchRow();
			if (isset($row['value'])) {
				if ($row['value']!=$aNewValue)
					$this->execDML($this->value_update,array('ns'=>$sa[0], 'key'=>$sa[1], 'new_value'=>$aNewValue));
			} else {
				$this->execDML($this->value_insert,array('ns'=>$sa[0],'key'=>$sa[1],'value'=>$aNewValue,'default'=>''));
			}
		} catch (DbException $dbe) {
			if ($this->exists($this->getTableName())) {
				throw $dbe->setContextMsg('dbError@'.$this->getTableName().".setMapValue($aKey,$aNewValue)\n");
			}
		}
	}

	//----- methods required for various IMPLEMENTS interfaces
	
	public function offsetSet($aKey, $aValue) {
		if (!empty($aKey)) {
			$this->setMapValue($aKey,$aValue);
		} else {
			throw new InvalidArgumentException('key required, v:'.$aValue);
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
	
	public function exists() {
		return parent::exists($this->getTableName());
	}

}//end class

}//end namespace
