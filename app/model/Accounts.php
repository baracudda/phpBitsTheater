<?php
namespace com\blackmoonit\bits_theater\app\model;
use com\blackmoonit\bits_theater\app\Model;
use com\blackmoonit\bits_theater\app\DbException;
use com\blackmoonit\bits_theater\app\IllegalArgumentException;
{//begin namespace

class Accounts extends Model {
	public $tnAccounts; const TABLE_Accounts = 'accounts';

	public function setup($aDbConn) {
		parent::setup($aDbConn);
		$this->tnAccounts = $this->tbl_.self::TABLE_Accounts;
		$this->sql_acct_get = "SELECT * FROM {$this->tnAccounts} WHERE account_id = :acct_id";
		$this->sql_acct_add = "INSERT INTO {$this->tnAccounts} ".
				"(account_id, account_name, external_id) VALUES (:account_id, :account_name, :external_id) ";
	}
	
	public function setupModel() {
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnAccounts} ".
				"( account_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY".
				", account_name NCHAR(60) NOT NULL COLLATE utf8_unicode_ci".
				", external_id INT".
				", KEY idx_external_id (external_id)".
				", UNIQUE KEY idx_account_name_ci (account_name) ".
				") CHARACTER SET utf8 COLLATE utf8_bin";
		}
		$this->execDML($theSql);
	}
	
	public function getAccount($aAcctId) {
		$rs = $this->query($this->sql_acct_get,array('acct_id'=>$aAcctId));
		return $rs->fetch();
	}
	
	public function isEmpty($aTableName=null) {
		if ($aTableName==null)
			$aTableName = $this->tnAccounts;
		return parent::isEmpty($aTableName);
	}
	
	public function getByName($aName) {
		$theSql = "SELECT * FROM {$this->tnAccounts} WHERE account_name = :acct_name";
		$theStatement = $this->query($theSql,array('acct_name'=>$aName));
		return $theStatement->fetch();
	}
	
	public function add($aData) {
		$theResult = null;
		if (!empty($aData)) {
			if (!array_key_exists('account_id',$aData))
				$aData['account_id'] = null;
			if (!array_key_exists('external_id',$aData))
				$aData['external_id'] = null;
			if (!array_key_exists('account_name',$aData))
				throw new IllegalArgumentException('account_name undefined');
			$this->db->beginTransaction();
			if ($this->execDML($this->sql_acct_add,$aData)==1) {
				$theResult = $this->db->lastInsertId();
				$this->db->commit();
			} else {
				$this->db->rollBack();
			}
		}
		return $theResult;
	}
	
	public function del($aAccountId) {
		$theSql = "DELETE FROM {$this->tnAccounts} WHERE account_id=$aAccountId ";
		return $this->execDML($theSql);
	}
	

}//end class

}//end namespace
