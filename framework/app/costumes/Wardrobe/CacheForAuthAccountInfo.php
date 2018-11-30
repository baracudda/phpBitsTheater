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
use BitsTheater\costumes\AuthOrg;
use BitsTheater\costumes\WornForExportData as ExportDataTrait;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\models\Auth as AuthDB;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Helper class for session caching current non-sensitive login account info.
 * @since BitsTheater 4.0.0
 */
class CacheForAuthAccountInfo extends BaseCostume
{ use ExportDataTrait;

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
	public $groups = null;
	/** @var string[] 3D boolean leaf array of group_id[namespace[right[]]] */
	public $rights = null;
	/** @var AuthOrg The property to store the current org. */
	public $mSeatingSection = null;
	
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
	protected function copyFrom( $aThing )
	{
		foreach ($aThing as $theName => $theValue) {
			if (property_exists($this, $theName)) {
				$this->{$theName} = $theValue;
			}
			if ( $theName=='curr_org' && !empty($theValue) ) {
				$this->setSeatingSection($theValue);
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
		unset($o->mSeatingSection);
		if ( !empty($this->mSeatingSection) )
		{ $o->curr_org = $this->mSeatingSection->exportData(); }
		$dbAuth = $this->getProp(AuthDB::MODEL_NAME);
		switch ($dbAuth->dbType()) {
			case $dbAuth::DB_TYPE_MYSQL:
				$o = CommonMySql::deepConvertSQLTimestampsToISOFormat($o);
				break;
		}//switch
		return $o;
	}
	
	/**
	 * Set the org data to our object.
	 * @param array|AuthOrg $aOrgData - the mSeatingSection data as obj or array.
	 * @return $this Returns $this for chaining.
	 */
	public function setSeatingSection( $aOrgData )
	{
		if ( empty($aOrgData) ) {
			$this->mSeatingSection = null;
		}
		if ( is_array($aOrgData) ) {
			$this->mSeatingSection = AuthOrg::getInstance($this, $aOrgData);
		}
		else if ( $aOrgData instanceof AuthOrg ) {
			$this->mSeatingSection = $aOrgData;
		}
		return $this;
	}
	
	/**
	 * Return the current org ID as something other than NULL to mean Root.
	 * @return string Returns the magic root ID for root, else the org_id in use.
	 */
	public function getSeatSectionID()
	{
		if ( !empty($this->mSeatingSection) && !empty($this->mSeatingSection->org_id) ) {
			return $this->mSeatingSection->org_id;
		}
		else {
			return AuthDB::ORG_ID_4_ROOT;
		}
	}
	
	/**
	 * Return the parent ID of the current org as something other than NULL to mean Root.
	 * NULL will be returned if the current org is the Root org since Root has no parent.
	 * @return string|NULL Returns the magic root ID for root, else the parent org ID or NULL
	 *   if current org is already Root.
	 */
	public function getSeatSectionParentID()
	{
		if ( !empty($this->mSeatingSection) ) {
			if ( !empty($this->mSeatingSection->parent_org_id) ) {
				return $this->mSeatingSection->parent_org_id;
			}
			else if ( !empty($this->mSeatingSection->org_id) &&
					$this->mSeatingSection->org_id != AuthDB::ORG_ID_4_ROOT )
			{
				return AuthDB::ORG_ID_4_ROOT;
			}
		}
		return null;
	}
	
}//end class

}//end namespace
