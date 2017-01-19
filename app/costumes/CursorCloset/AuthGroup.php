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
use BitsTheater\models\AuthGroups as MyModel;
use BitsTheater\costumes\SimpleCostume;
{//namespace begin

/**
 * Auth accounts can use this costume to wrap auth group info.
 * PDO statements can fetch data directly into this class.
 */
class AuthGroup extends BaseCostume
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
	
	public $group_id;
	public $group_name;
	public $parent_group_id;
	
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
		} else
			return $aExportData;
	}
	
	/**
	 * Return this payload data as a simple class, minus any metadata this class might have.
	 * @return string Return self encoded as a standard class.
	 */
	public function exportData() {
		$o = parent::exportData();
		//$o->group_id = intval($o->group_id); leave as string
		$o->parent_group_id = (!is_null($o->parent_group_id)) ? intval($o->parent_group_id) : null;
		return $this->exportFilter($o);
	}

}//end class
	
}//end namespace
