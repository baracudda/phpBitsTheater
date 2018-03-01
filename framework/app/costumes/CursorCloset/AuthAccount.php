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
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList($aFieldList)
	{
		$theIndex = array_search('with_map_info', $aFieldList);
		$bIncMapInfo = ( $theIndex!==false );
		unset($aFieldList[$theIndex]);
		if ( empty($aFieldList) ) {
			$aFieldList = array_diff(static::getDefinedFields(), array(
					'groups',
			));
		}
		if ( $bIncMapInfo ) {
			$aFieldList[] = 'groups';
		}
		return parent::setExportFieldList($aFieldList);
	}
	
	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		unset($o->pwhash); //never export this value
		$o->is_active = filter_var($o->is_active, FILTER_VALIDATE_BOOLEAN);
		return $o;
	}

	/** @return \BitsTheater\models\AuthGroups */
	protected function getAuthGroupsProp()
	{ return $this->getModel()->getProp( 'AuthGroups' ); }
	
	/**
	 * Event called after fetching data from db and setting all our properties.
	 */
	public function onFetch()
	{
		if ( !empty($this->account_id) ) try {
			if ( array_search('groups', $this->getExportFieldList())!==false ) {
				$this->groups = $this->getAuthGroupsProp()->getAcctGroups($this->account_id);
				if (!empty($aRow->groups)) {
					foreach ($aRow->groups as &$theGroupId) {
						if ( is_numeric($theGroupId) ) {
							$theGroupId = strval($theGroupId);
						}
					}
				}
			}
			if ( !empty($this->hardware_ids) )
			{
				//convert string field to a proper list of items
				$this->hardware_ids = explode('|', $this->hardware_ids);
				foreach ($this->hardware_ids as &$theToken) {
					list($thePrefix, $theHardwareId, $theUUID) = explode(':', $theToken);
					$theToken = $theHardwareId;
				}
				//if there is only 1 item, ensure it is just a string, not an array
				if (count($this->hardware_ids)==1)
				{ $this->hardware_ids = $this->hardware_ids[0]; }
			}
		}
		catch (\Exception $x)
		{ $this->getModel()->logErrors(__METHOD__, $x->getMessage()); }
	}
	
}//end class
	
}//end namespace
