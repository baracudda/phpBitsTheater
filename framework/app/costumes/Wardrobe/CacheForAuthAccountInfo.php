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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\WornForExportData;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\models\AuthGroups;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Helper class for session caching current non-sensitive login account info.
 * @since BitsTheater 4.0.0
 */
class CacheForAuthAccountInfo extends BaseCostume
{ use WornForExportData;

	/** @var string */
	public $auth_id = null;
	/** @var integer */
	public $account_id = null;
	/** @var string */
	public $account_name = null;
	/** @var integer */
	public $external_id = null;
	/** @var string */
	public $email = null;
	/** @var string Db value of last_seen_ts timestamp (varies by dbtype). */
	public $last_seen_ts = null;
	/** @var \DateTime */
	public $last_seen_dt = null;
	/** @var boolean Will always be TRUE for current logged in account info. */
	public $is_active = true;
	/** @var string[] account has membership in these AuthGroups. */
	public $groups = array( AuthGroups::UNREG_GROUP_ID );

	public function __construct($aContext=null, $aFieldList=null) {
		if ( !empty($aContext) )
		{ $this->setDirector( $aContext->getDirector() ); }
		$this->mExportTheseFields = $aFieldList;
	}

	/**
	 * Copies values into matching property names
	 * based on the array keys or object property names.
	 * @param array|object $aThing - array or object to copy from.
	 */
	protected function copyFrom( &$aThing )
	{
		foreach ($aThing as $theName => $theValue) {
			if (property_exists($this, $theName)) {
				$this->{$theName} = $theValue;
			}
		}
		$this->account_id = Strings::toInt($this->account_id);
		$this->external_id = Strings::toInt($this->external_id);
		if ( !empty($this->last_seen_ts) )
		{ $this->last_seen_dt = new \DateTime( $this->last_seen_ts ); }
		$this->is_active = boolval($this->is_active);
		if ( empty($this->account_name) )
		{ $this->is_active = false; }
	}

	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		$o->account_id = intval( $o->account_id ) ;
		unset($o->last_seen_dt);
		$dbAuth = $this->getDirector()->getProp('Auth');
		switch ($dbAuth->dbType()) {
			case $dbAuth::DB_TYPE_MYSQL:
				$o = CommonMySql::deepConvertSQLTimestampsToISOFormat($o);
				break;
		}//switch
		return $o;
	}
	
}//end class

}//end namespace