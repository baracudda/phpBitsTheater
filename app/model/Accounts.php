<?php
namespace app\model;
use app\Model;
use app\DbException;
{

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
		$row = null;
		$rs = $this->query($this->sql_acct_get,array('acct_id'=>$aAcctId));
		$row = $rs->fetchRow();
		return $row;
	}
	
	public function isEmpty($aTableName=null) {
		if ($aTableName==null)
			$aTableName = $this->tnAccounts;
		return parent::isEmpty($aTableName);
	}
	
	public function add($aData) {
		if (!empty($aData))
			return $this->execDML('acct_add',$aData);
	}
	
	public function del($aAccountId) {
		$theSql = "DELETE FROM {$this->tnAccounts} WHERE account_id=$aAccountId ";
		return $this->execDML($theSql);
	}
	

}//end class

}//end namespace
