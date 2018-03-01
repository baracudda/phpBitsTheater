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
use BitsTheater\costumes\CursorCloset\ARecord as BaseCostume;
use BitsTheater\models\Auth as MyModel;
use BitsTheater\costumes\colspecs\CommonMySql;
{//namespace begin

/**
 * AuthBasic accounts can use this costume to wrap account info.
 * PDO statements can fetch data directly into this class.
 */
class AuthAccount extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;
	
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
	
	/**
	 * Return the fields that should be exported.
	 * @param object $aExportData - the data to export.
	 * @return object Returns the data to be exported.
	 */
	protected function exportFilter($aExportData) {
		if (!empty($aExportData) && !empty($this->mExportTheseFields)) {
			return parent::exportFilter($aExportData);
		} else {
			unset($aExportData->groups);
			return $aExportData;
		}
	}
	
	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		unset($o->pwhash); //never export this value
		$o->is_active = ($o->is_active === '1') ? true : false;
		if ($this->dbModel->dbType()===MyModel::DB_TYPE_MYSQL)
		{
			$o->verified_ts = CommonMySql::convertSQLTimestampToISOFormat($o->verified_ts);
			$o->created_ts = CommonMySql::convertSQLTimestampToISOFormat($o->created_ts);
			$o->updated_ts = CommonMySql::convertSQLTimestampToISOFormat($o->updated_ts);
		}
		return $o;
	}

}//end class
	
}//end namespace
