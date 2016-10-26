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
use com\blackmoonit\exceptions\IllegalArgumentException;
use com\blackmoonit\exceptions\DbException;
use BitsTheater\costumes\SqlBuilder ;
use PDOException;
{//begin namespace

class BitsAccounts extends BaseModel {
	public $tnAccounts; const TABLE_Accounts = 'accounts';

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnAccounts = $this->tbl_.self::TABLE_Accounts;
	}
	
	public function setupModel() {
		switch ($this->dbType()) {
		case 'mysql': default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnAccounts} ".
				"( account_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY".
				", account_name NCHAR(60) NOT NULL".
				", external_id INT".
				", KEY idx_external_id (external_id)".
				", UNIQUE KEY idx_account_name_ci (account_name) ".
				") CHARACTER SET utf8 COLLATE utf8_general_ci";
		}
		$this->execDML($theSql);
		$this->debugLog($this->getRes('install/msg_create_table_x_success/'.$this->tnAccounts));
	}
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnAccounts : $aTableName );
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnAccounts : $aTableName );
	}
	
	public function getAccount( $aAccountID )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnAccounts )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountID )
			->endWhereClause()
			;
		try { return $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw new DbException( $pdox, __METHOD__ . ' failed.' ) ; }
	}
	
	public function getByName( $aName )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnAccounts )
			->startWhereClause()
			->mustAddParam( 'account_name', $aName )
			->endWhereClause()
			;
		try { return $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw new DbException( $pdox, __METHOD__ . ' failed.' ) ; }
	}
	
	public function getByExternalId($aExternalId) {
		$theSql = "SELECT * FROM {$this->tnAccounts} WHERE external_id = :external_id";
		return $this->getTheRow($theSql,array('external_id'=>$aExternalId));
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
			$theSql = "INSERT INTO {$this->tnAccounts} ";
			$theSql .= "(account_id, account_name, external_id)";
			$theSql .= " VALUES ";
			$theSql .= "(:account_id, :account_name, :external_id) ";
			$this->db->beginTransaction();
			try {
				if ($this->execDML($theSql,$aData)) {
					$theResult = $this->db->lastInsertId();
					$this->db->commit();
				} else {
					$this->db->rollBack();
				}
			} catch (PDOException $pdoe) {
				$this->db->rollBack();
				throw new DbException($pdoe, 'Add Accout failed.');
			}
		}
		return $theResult;
	}
	
	public function del($aAccountId) {
		$theSql = "DELETE FROM {$this->tnAccounts} WHERE account_id=$aAccountId ";
		return $this->execDML($theSql);
	}
	
	/**
	 * Gets the set of all account records.
	 * TODO - Add paging someday.
	 * @return PDOStatement - the iterable result of the SELECT query
	 * @throws DbException if something goes wrong
	 * @since BitsTheater 3.6
	 */
	public function getAll()
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnAccounts )
			;
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}


}//end class

}//end namespace
