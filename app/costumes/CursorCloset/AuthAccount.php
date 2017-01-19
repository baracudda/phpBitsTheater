<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
use BitsTheater\models\Auth as MyModel;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\costumes\SimpleCostume;
{//namespace begin

/**
 * AuthBasic accounts can use this costume to wrap account info.
 * PDO statements can fetch data directly into this class.
 */
class AuthAccount extends BaseCostume
{
	/**
	 * My fully qualified classname.
	 * @var string
	 */
	const ITEM_CLASS = __CLASS__;
	/**
	 * The model I need to access to.
	 * @var MyModel
	 */
	protected $dbModel = null;
	/**
	 * Export only these fields. All fields, if NULL.
	 * @var string[]
	 */
	protected $mExportTheseFields = null;
	
	public $account_id;
	public $account_name;
	//public $external_id;
	public $auth_id;
	public $email;
	//public $pwhash; do not export!
	public $verified_ts;
	public $is_active;
	public $hardware_ids;
	public $created_by;
	public $updated_by;
	public $created_ts;
	public $updated_ts;
	
	//extended info (optional - must be explicitly asked for via mExportTheseFields)
	public $groups;
	
	public function __construct($aDbModel=null, $aFieldList=null) {
		$this->dbModel = $aDbModel;
		$this->mExportTheseFields = $aFieldList;
	}
	
	/**
	 * Return the fields that should be exported.
	 * @param object $aExportData - the data to export.
	 * @return object Returns the data to be exported.
	 */
	protected function exportFilter($aExportData) {
		if (!empty($aExportData) && !empty($this->mExportTheseFields)) {
			$o = new SimpleCostume();
			foreach ($this->mExportTheseFields as $theField) {
				$o->{$theField} = $aExportData->{$theField};
			}
			return $o;
		} else {
			unset($aExportData->groups);
			return $aExportData;
		}
	}
	
	/**
	 * Return this payload data as a simple class, minus any metadata this class might have.
	 * @return string Return self encoded as a standard class.
	 */
	public function exportData() {
		$o = parent::exportData();
		$o->is_active = ($o->is_active === '1') ? true : false;
		if ($this->dbModel->dbType()===MyModel::DB_TYPE_MYSQL)
		{
			$o->verified_ts = CommonMySql::convertSQLTimestampToISOFormat($o->verified_ts);
			$o->created_ts = CommonMySql::convertSQLTimestampToISOFormat($o->created_ts);
			$o->updated_ts = CommonMySql::convertSQLTimestampToISOFormat($o->updated_ts);
		}
		return $this->exportFilter($o);
	}

}//end class
	
}//end namespace
