<?php

namespace BitsTheater\models\PropCloset;
use BitsTheater\Model as BaseModel;
use BitsTheater\costumes\JokaPackage;
use BitsTheater\costumes\SqlBuilder;
use com\blackmoonit\Strings;
use com\blackmoonit\database\DbUtils;
use com\blackmoonit\database\FinallyCursor;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\exceptions\IllegalArgumentException;
use com\blackmoonit\Arrays;
use \PDO;
use \PDOStatement;
use \PDOException;
use \Exception;
use \DateTime;
use \DateInterval;
{//begin namespace

class JokaQueues extends BaseModel {
	public $tnPayloadLog; const TABLE_PayloadLog = 'payloads_log';
	public $tnInboundPayloads; const TABLE_InboundPayloads = 'payloads_in';
	public $tnOutboundPayloads; const TABLE_OutboundPayloads = 'payloads_out';
	protected $mSqlPayloadClass = 'BitsTheater\costumes\JokaPackage';
	protected $mSqlPayloadLimit = 'LIMIT 20';
	//WebSocket tech is optional, but if used, the following fields are useful.
	public $myPid = 0; //used to signal WebSocketChild server process about more data in queue
	public $signalPid = 0; //used to signal WebSocket server about a change in queue state
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnPayloadLog = $this->tbl_.self::TABLE_PayloadLog;
		$this->tnInboundPayloads = $this->tbl_.self::TABLE_InboundPayloads;
		$this->tnOutboundPayloads = $this->tbl_.self::TABLE_OutboundPayloads;
	}
	
	public function setupModel() {
		switch ($this->dbType()) {
		case self::DB_TYPE_MYSQL: default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnInboundPayloads} ".
					"( payload_id NCHAR(36) NOT NULL".
					", payload LONGTEXT CHARACTER SET utf8 NOT NULL".
					", package_name NCHAR(255) NOT NULL".
					", device_id NCHAR(255) NOT NULL".
					//", transmit_ts timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'".
					//", received_ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP".
					//timestamp cannot handle microseconds in MySQL < 5.6.4, use CHAR and fake it
					", transmit_ts CHAR(27) NOT NULL DEFAULT '0000-00-00T00:00:00.000000Z'".
					", received_ts CHAR(27) NOT NULL DEFAULT '0000-00-00T00:00:00.000000Z'".
					", PRIMARY KEY (payload_id)".
					", KEY idx_device_id (device_id, transmit_ts)".
					", KEY idx_package_name (package_name, transmit_ts)".
					", KEY idx_transmit_ts (transmit_ts)".
					") DEFAULT CHARSET=ascii COLLATE=ascii_bin";
			$this->execDML($theSql);
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnOutboundPayloads} ".
					"( payload_id NCHAR(36) NOT NULL".
					", payload LONGTEXT CHARACTER SET utf8 NOT NULL".
					", package_name NCHAR(255) NOT NULL".
					", device_id NCHAR(255) NOT NULL".
					//", transmit_ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP".
					//", received_ts timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'".
					//timestamp cannot handle microseconds in MySQL < 5.6.4, use CHAR and fake it
					", transmit_ts CHAR(27) NOT NULL DEFAULT '0000-00-00T00:00:00.000000Z'".
					", received_ts CHAR(27) NULL DEFAULT '0000-00-00T00:00:00.000000Z'".
					", PRIMARY KEY (payload_id)".
					", KEY idx_device_id (device_id, transmit_ts)".
					", KEY idx_package_name (package_name, transmit_ts)".
					", KEY idx_transmit_ts (transmit_ts)".
					") DEFAULT CHARSET=ascii COLLATE=ascii_bin";
			$this->execDML($theSql);
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnPayloadLog} ".
					"( log_id int(11) NOT NULL AUTO_INCREMENT".
					", payload_id char(36) NOT NULL".
					", payload LONGTEXT CHARACTER SET utf8 NOT NULL".
					", package_name char(255) NOT NULL".
					", device_id char(255) NOT NULL".
					//", transmit_ts timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'".
					//", received_ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP".
					//timestamp cannot handle microseconds in MySQL < 5.6.4, use CHAR and fake it
					", transmit_ts CHAR(27) NOT NULL DEFAULT '0000-00-00T00:00:00.000000Z'".
					", received_ts CHAR(27) NULL DEFAULT '0000-00-00T00:00:00.000000Z'".
					", direction enum('in','out') COLLATE ascii_bin NOT NULL DEFAULT 'in'".
					", PRIMARY KEY (log_id)".
					", UNIQUE KEY idx_payload_id (payload_id)".
					", KEY idx_device_id (device_id,transmit_ts)".
					", KEY idx_package_name (package_name,transmit_ts)".
					", KEY idx_transmit_ts (transmit_ts)".
					") DEFAULT CHARSET=ascii COLLATE=ascii_bin";
			$this->execDML($theSql);
			break;
		}//switch
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnPayloadLog : $aTableName );
	}

	/**
	 * Logs the out/in payloads.
	 * @param JokaPackage $aJokaPackage - what info to log.
	 * @param in/out $aDirection - either "in" or "out".
	 * @throws DbException - a PDO exception may occur.
	 */
	public function logPayload(JokaPackage $aJokaPackage, $aDirection) {
		if ($this->isConnected() && !empty($aJokaPackage)) try {
			$theSql = 'INSERT INTO '.$this->tnPayloadLog.' SET';
			$theSql .= ' payload_id=:payload_id';
			$theSql .= ', payload=:payload';
			$theSql .= ', package_name=:package_name';
			$theSql .= ', device_id=:device_id';
			$theSql .= ', transmit_ts=:transmit_ts';
			$theSql .= ', received_ts=:received_ts';
			$theSql .= ', direction=:direction';
				
			//param values to save
			$theParams['payload_id'] = $aJokaPackage->payload_id;
			$theParams['payload'] = $aJokaPackage->payload;
			$theParams['package_name'] = $aJokaPackage->package_name;
			$theParams['device_id'] = $aJokaPackage->device_id;
			$theParams['transmit_ts'] = $aJokaPackage->transmit_ts;
			$theParams['received_ts'] = $aJokaPackage->received_ts;
			$theParams['direction'] = $aDirection;
			
			$theParamTypes['payload_id'] = PDO::PARAM_STR;
			$theParamTypes['payload'] = PDO::PARAM_STR;
			$theParamTypes['package_name'] = PDO::PARAM_STR;
			$theParamTypes['device_id'] = PDO::PARAM_STR;
			$theParamTypes['transmit_ts'] = PDO::PARAM_STR;
			$theParamTypes['received_ts'] = PDO::PARAM_STR;
			$theParamTypes['direction'] = PDO::PARAM_STR;

			$this->execDML($theSql,$theParams,$theParamTypes);
		} catch (PDOException $pdoe) {
			if (stripos($pdoe->getMessage(),'duplicate entry')===false) {
				throw new DbException($pdoe, 'logPayload() failed.');
			}
			//if reached here, all is ok, already logged
		}
	}

	public function addIncomingPayload($aPayloadId, $aPayload, $aPackageName, $aDeviceId, $aXmitTs) {
		$theResult = null;
		if ($this->isConnected() && !empty($aPayloadId) && !empty($aPayload) && !empty($aPackageName)
				&& !empty($aDeviceId) && !empty($aXmitTs)) {
			$theParams = array();
			$theParamTypes = array();
			
			$theSql = 'INSERT INTO '.$this->tnInboundPayloads.' SET';
			$theSql .= ' payload_id=:payload_id';
			$theSql .= ', payload=:payload';
			$theSql .= ', package_name=:package_name';
			$theSql .= ', device_id=:device_id';
			$theSql .= ', transmit_ts=:transmit_ts';
			$theSql .= ', received_ts=:received_ts';
				
			//param values to save
			$theParams['payload_id'] = $aPayloadId;
			$theParams['payload'] = $aPayload;
			$theParams['package_name'] = $aPackageName;
			$theParams['device_id'] = $aDeviceId;
			$theParams['transmit_ts'] = $aXmitTs;
			$theParams['received_ts'] = $theResult = $this->utc_now(true);
			
			$theParamTypes['payload_id'] = PDO::PARAM_STR;
			$theParamTypes['payload'] = PDO::PARAM_STR;
			$theParamTypes['package_name'] = PDO::PARAM_STR;
			$theParamTypes['device_id'] = PDO::PARAM_STR;
			$theParamTypes['transmit_ts'] = PDO::PARAM_STR;
			$theParamTypes['received_ts'] = PDO::PARAM_STR;
			
			try {
				if ($this->execDML($theSql,$theParams,$theParamTypes)) {
					$this->logPayload(JokaPackage::fromArray($this->director, $theParams),'in');
				}
			} catch (PDOException $pdoe) {
				//possible primary key exception, lets try and get the received_ts
				$theParams = array();
				$theParamTypes = array();
				$theSql = 'SELECT * FROM '.$this->tnInboundPayloads;
				$theSql .= ' WHERE payload_id=:payload_id';
				$theParams['payload_id'] = $aPayloadId;
				$theParamTypes['payload_id'] = PDO::PARAM_STR;
				$theExistingRow = $this->getTheRow($theSql,$theParams,$theParamTypes);
				if (!empty($theExistingRow)) {
					$theResult = $theExistingRow['received_ts'];
				}
			}
		}
		return $theResult;
	}
	
	public function getIncomingPayloads($aPackageName=null) {
		$theResultSet = array();
		if ($this->isConnected()) try {
			$rs = null;
			$myFinally = FinallyCursor::forDbCursor($rs);
		
			$theParams = array();
			$theParamTypes = array();
			$theSql = 'SELECT * FROM '.$this->tnInboundPayloads;
			if (!empty($aPackageName)) {
				$theSql .= ' WHERE package_name=:package_name';
				$theParams['package_name'] = $aPackageName;
				$theParamTypes['package_name'] = PDO::PARAM_STR;
			}
			$theSql .= ' ORDER BY transmit_ts '.$this->mSqlPayloadLimit;
			$rs = $this->query($theSql,$theParams,$theParamTypes);
			$theResultSet = $rs->fetchAll(PDO::FETCH_CLASS, $this->mSqlPayloadClass);
			if (!empty($theResultSet)) {
				foreach($theResultSet as &$theRow) {
					$theRow->setDirector($this->director);
				}
			}
			$rs->closeCursor();
			
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe, 'getIncomingPayloads('.$aPackageName.') failed.');
		}
		return $theResultSet;
	}
	
	public function removeIncomingPayloads($aRowSet) {
		if ($this->isConnected() && !empty($aRowSet) && is_array($aRowSet)) try {
			$theSql = 'DELETE FROM '.$this->tnInboundPayloads.' WHERE payload_id=?';
			if (is_array($aRowSet[0])) {
				$theParams = Arrays::array_column($aRowSet, 'payload_id');
			} elseif ($aRowSet[0] instanceof JokaPackage) {
				$theParams = array_map(function($e) {return $e->payload_id;}, $aRowSet);
			} elseif (is_string($aRowSet[0])) {
				$theParams = $aRowSet;
			} else {
				//we cannot handle what was passed in
				throw new DbException($pdoe, 'removeIncomingPayloads() could not understand the parameter.');
			}
			//echo(Strings::debugStr($theParams));
			$this->execMultiDML($theSql, $theParams);
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe, 'removeIncomingPayloads() failed.');
		}
	}

	public function removeIncomingPayload(JokaPackage $aJokaPackage) {
		if ($this->isConnected() && !empty($aJokaPackage)) try {
			$theSql = 'DELETE FROM '.$this->tnInboundPayloads.' WHERE payload_id=:payload_id';
			$theParams = array('payload_id' => $aJokaPackage->payload_id);
			$theParamTypes = array('payload_id' => PDO::PARAM_STR);
			$this->execDML($theSql, $theParams, $theParamTypes);
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe, 'removeIncomingPayload() failed.');
		}
	}

	public function addOutgoingPayload(JokaPackage $aJokaPackage) {
		if ($this->isConnected() && !empty($aJokaPackage)) {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'payload_id' => $aJokaPackage->getOrCreatePayloadId(),
					'payload' => $aJokaPackage->payload,
					'package_name' => $aJokaPackage->package_name,
					'device_id' => $aJokaPackage->device_id,
					'transmit_ts' => (empty($aJokaPackage->transmit_ts)) ? $this->utc_now(true) : $aJokaPackage->transmit_ts,
			));
			$theSql->startWith('INSERT INTO')->add($this->tnOutboundPayloads);
			$theSql->setParamPrefix(' SET ')->mustAddParam('payload_id')->setParamPrefix(', ');
			$theSql->mustAddParam('payload');
			$theSql->mustAddParam('package_name');
			$theSql->mustAddParam('device_id');
			$theSql->mustAddParam('transmit_ts');
			$theSql->execDML();
			//$this->debugLog(__METHOD__.' sql='.$this->debugStr($theSql));
			if ($this->signalPid>0) {
				posix_kill($this->signalPid, SIGUSR2);
			}
		} else {
			$this->debugLog(__METHOD__.' pkg='.$this->debugStr($aJokaPackage));
		}
		return $aJokaPackage->payload_id;
	}
	
	public function removeOutgoingPayload(JokaPackage $aJokaPackage) {
		if ($this->isConnected() && !empty($aJokaPackage)) try {
			$theSql = 'DELETE FROM '.$this->tnOutboundPayloads.' WHERE payload_id=:payload_id';
			$theParams = array('payload_id' => $aJokaPackage->payload_id);
			$theParamTypes = array('payload_id' => PDO::PARAM_STR);
			$theNumRowsAffected = $this->execDML($theSql, $theParams, $theParamTypes);
			if (!empty($theNumRowsAffected)) {
				$this->logPayload($aJokaPackage,'out');
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe, 'removeOutgoingPayload() failed.');
		}
	}
	
	public function getOutgoingPayload($aPayloadIds) {
		$theResultSet = array();
		if ($this->isConnected()) try {
			$rs = null;
			$myFinally = FinallyCursor::forDbCursor($rs);
		
			$theParams = array();
			$theParamTypes = array();
			$theSql = 'SELECT payload_id, payload, package_name, device_id, transmit_ts';
			$theSql .= ' FROM '.$this->tnOutboundPayloads;
			if (is_array($aPayloadIds)) {
				$theSql .= ' WHERE payload_id IN ("0"';
				foreach ($aPayloadIds as $key=>$thePayloadId) {
					$theParamName = 'payload_id_'.$key;
					$theSql .= ', :'.$theParamName;
					$theParams[$theParamName] = $thePayloadId;
					$theParamTypes[$theParamName] = PDO::PARAM_STR;
				}
				$theSql .= ')';
			} else {
				$thePayloadId = $aPayloadIds;
				$theSql .= ' WHERE payload_id=:payload_id';
				$theParams['payload_id'] = $thePayloadId;
				$theParamTypes['payload_id'] = PDO::PARAM_STR;
			}
			$theSql .= ' ORDER BY transmit_ts';
			$rs = $this->query($theSql,$theParams,$theParamTypes);
			$theResultSet = $rs->fetchAll(PDO::FETCH_CLASS, $this->mSqlPayloadClass);
			if (!empty($theResultSet)) {
				foreach($theResultSet as &$theRow) {
					$theRow->setDirector($this->director);
				}
			}
			$rs->closeCursor();
			
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe, 'getOutgoingPayloads("'.$aPackageName.'") failed.');
		}
		return $theResultSet;
	}
	
	public function getOutgoingPayloadsAsClass($aPackageName, $aDeviceId, $aClass) {
		$theResultSet = array();
		if ($this->isConnected()) try {
			$rs = null;
			$myFinally = FinallyCursor::forDbCursor($rs);
		
			$theSql = SqlBuilder::withModel($this)->setDataSet(array(
					'package_name' => $aPackageName,
					'device_id' => $aDeviceId,
			));
			$theSql->startWith('SELECT payload_id, payload, package_name, device_id, transmit_ts');
			$theSql->add('FROM')->add($this->tnOutboundPayloads);
			$theSql->setParamPrefix(' WHERE ')->mustAddParam('package_name');
			$theSql->setParamPrefix(' AND ')->mustAddParam('device_id');
			$theSql->add('ORDER BY transmit_ts '.$this->mSqlPayloadLimit);
			$rs = $theSql->query();
			$theResultSet = $rs->fetchAll(PDO::FETCH_CLASS, $aClass);
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe, __METHOD__.'("'.$aPackageName.', '.$aDeviceId.'") failed.');
		}
		return $theResultSet;
	}
	
	public function getOutgoingPayloads($aPackageName, $aDeviceId) {
		$theResultSet = $this->getOutgoingPayloadsAsClass($aPackageName, $aDeviceId, $this->mSqlPayloadClass);
		if (!empty($theResultSet)) {
			foreach($theResultSet as &$theRow) {
				$theRow->setDirector($this->director);
			}
		}
		return $theResultSet;
	}
	
	/**
	 * Log and then remove the outgoing payload.
	 * @param String/Array aPayloadIds - payload_id(s) of outgoing package to log/remove.
	 */
	public function ackOutgoingPayload($aPayloadIds) {
		$thePayloadList = $this->getOutgoingPayload($aPayloadIds);
		$theReceivedDbStr = $this->utc_now(true);
		foreach ($thePayloadList as $theJokaPackage) {
			$theJokaPackage->received_ts = $theReceivedDbStr;
			$this->logPayload($theJokaPackage,'out');
			$this->removeOutgoingPayload($theJokaPackage);
		}
	}
	
	public function processIncomingPayloads() {
		if ($this->isConnected()) {
			$rs = $this->getIncomingPayloads();
			foreach ((array)$rs as $theJokaPackage) {
				//load model based on package name
				$theModelHandlerName = $theJokaPackage->getModelHandlerName();
				$dbModel = $this->getProp($theModelHandlerName);
				try {
					if (empty($dbModel)) {
						throw new Exception('Model '.$theModelHandlerName.' not found for payload: '.Strings::debugStr($theJokaPackage));
					}
					if ($dbModel->processIncomingPayload($theJokaPackage)) {
						$this->removeIncomingPayload($theJokaPackage);
					}
				} catch (Exception $e) {
					$theErrMsg = $theModelHandlerName.": {$e->getMessage()}\n";
					if ($this->myPid>0)
						echo($theErrMsg);
					syslog(LOG_ERR,$theErrMsg);
					//once we log the error, remove the record from the incoming payload table (it is still in log table)
					$this->removeIncomingPayload($theJokaPackage);
				}
			}
			//if we had records to process, signal ourselves again to see if there is anymore
			if ($this->myPid>0 && !empty($rs) && empty($theErrMsg)) {
				posix_kill($this->myPid, SIGUSR1);
			} elseif ($this->signalPid>0 && empty($theErrMsg)) {
				echo('Done processing USR1 incoming, tell server its time to process USR2 outgoing.'."\n");
				posix_kill($this->signalPid, SIGUSR2);
			}
		} else {
			throw new DbException(null,'Not connected to the database.');
		}
	}
	
	/**
	 * Get payload log for display purposes.
	 * @param Scene $aScene - scene being used in case we need user-defined query limits.
	 * @throws DbException
	 * @return array Returns rows from the payload log.
	 */
	public function displayPayloadLog($aScene) {
		$theQueryLimit = $aScene->getQueryLimit($this->dbType());
		$theResult = array();
		if (!empty($this->db)) {
			try {
				$rs = null;
				$myFinally = FinallyCursor::forDbCursor($rs);
		
				if (!empty($theQueryLimit)) {
					//if we have a query limit, we may be using a pager, get total count for pager display
					$theSql = 'SELECT count(log_id) as total_rows FROM '.$this->tnPayloadLog;
					$rs = $this->getTheRow($theSql);
					if (!empty($rs)) {
						$aScene->setPagerTotalRowCount($rs['total_rows']+0);
					}
				}
				
				$theSql = 'SELECT * FROM '.$this->tnPayloadLog;
				$theSql .= ' ORDER BY received_ts';
				if (!empty($theQueryLimit)) {
					$theSql .= $theQueryLimit;
				}
				$rs = $this->query($theSql);
				$theResult = $rs->fetchAll();
			} catch (PDOException $pdoe) {
				throw new DbException($pdoe, 'displayPayloadLog failed.');
			}
		}
		return $theResult;
	}
	
	/**
	 * Get payload outgoing queue for display purposes.
	 * @param Scene $aScene - scene being used in case we need user-defined query limits.
	 * @throws DbException
	 * @return array Returns rows from the payload outgoing queue.
	 */
	public function displayPayloadOutgoingQueue($aScene) {
		$theQueryLimit = $aScene->getQueryLimit($this->dbType());
		$theResult = array();
		if (!empty($this->db)) {
			try {
				$rs = null;
				$myFinally = FinallyCursor::forDbCursor($rs);
				
				if (!empty($theQueryLimit)) {
					//if we have a query limit, we may be using a pager, get total count for pager display
					$theSql = 'SELECT count(payload_id) as total_rows FROM '.$this->tnOutboundPayloads;
					$rs = $this->getTheRow($theSql);
					if (!empty($rs)) {
						$aScene->setPagerTotalRowCount($rs['total_rows']+0);
					}
				}
				
				$theSql = 'SELECT * FROM '.$this->tnOutboundPayloads;
				$theSql .= ' ORDER BY transmit_ts';
				if (!empty($theQueryLimit)) {
					$theSql .= $theQueryLimit;
				}
				$rs = $this->query($theSql);
				$theResult = $rs->fetchAll();
				$rs->closeCursor();
			} catch (PDOException $pdoe) {
				throw new DbException($pdoe, 'displayPayloadOutgoingQueue failed.');
			}
		}
		return $theResult;
	}
	
}//end class

}//end namespace
