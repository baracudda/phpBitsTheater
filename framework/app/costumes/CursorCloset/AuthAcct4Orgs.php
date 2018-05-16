<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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
use BitsTheater\costumes\CursorCloset\AuthAccount as BaseCostume;
{//namespace begin

/**
 * AuthOrg accounts can use this costume to wrap account info.
 * PDO statements can fetch data directly into this class.
 */
class AuthAcct4Orgs extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;
	
	// basic info
	public $last_seen_ts;
	
	//extended info (optional - must be explicitly asked for via mExportTheseFields)
	//  alias: "with_map_info" will include all of these fields
	//public $groups will be included if you specify the alias ^
	public $org_ids;
	
	/**
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList($aFieldList)
	{
		$theIndex = array_search('with_map_info', $aFieldList);
		$bIncMapInfo = ( $theIndex!==false );
		if ( $theIndex!==false )
		{ unset($aFieldList[$theIndex]); }
		if ( empty($aFieldList) ) {
			$aFieldList = array_diff(static::getDefinedFields(), array(
					'groups',
					'org_ids',
			));
		}
		if ( $bIncMapInfo ) {
			$aFieldList[] = 'groups';
			$aFieldList[] = 'org_ids';
		}
		return parent::setExportFieldList($aFieldList);
	}
	
	/** @return \BitsTheater\models\Auth */
	protected function getAuthProp()
	{ return $this->getModel()->getProp( 'Auth' ); }
	
	/**
	 * Event called after fetching data from db and setting all our properties.
	 */
	public function onFetch()
	{
		if ( !empty($this->auth_id) ) try {
			if ( array_search('org_ids', $this->getExportFieldList())!==false ) {
				$this->org_ids = $this->getAuthProp()
					->getOrgsForAuthCursor($this->auth_id, 'org_id')
					->fetchAll(\PDO::FETCH_COLUMN, 0)
					;
			}
		}
		catch (\Exception $x)
		{ $this->getModel()->logErrors(__METHOD__, $x->getMessage()); }
		parent::onFetch();
	}
	
}//end class

}//end namespace
