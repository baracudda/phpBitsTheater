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
use BitsTheater\models\Auth as AuthModel;
use BitsTheater\models\AccountPrefs as AuthPrefsModel;
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

	/** @return AuthModel Returns the database model reference. */
	protected function getAuthModel()
	{ return $this->getProp(AuthModel::MODEL_NAME); }
	
	/** @return AuthPrefsModel Returns the database model reference. */
	protected function getAuthPrefsModel()
	{ return $this->getProp(AuthPrefsModel::MODEL_NAME); }
	
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
		$dbAuth = $this->getAuthModel();
		switch ($dbAuth->dbType()) {
			case $dbAuth::DB_TYPE_MYSQL:
				$o->last_seen_ts = CommonMySql::convertSQLTimestampToISOFormat($o->last_seen_ts);
				break;
		}//switch
		$this->returnProp($dbAuth);
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
		else if ( $aOrgData instanceof AuthOrg ) {
			$this->mSeatingSection = $aOrgData;
		}
		else if ( is_array($aOrgData) || is_object($aOrgData) ) {
			$this->mSeatingSection = AuthOrg::getInstance($this, $aOrgData);
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
			return AuthModel::ORG_ID_4_ROOT;
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
					$this->mSeatingSection->org_id != AuthModel::ORG_ID_4_ROOT )
			{
				return AuthModel::ORG_ID_4_ROOT;
			}
		}
		return null;
	}
	
	/**
	 * Sometimes we need to reload the groups list after loading the account
	 * record from the database.
	 * @return $this Returns $this for chaining.
	 */
	public function loadGroupsList()
	{
		if ( !empty($this->auth_id) ) {
			// (#6297) Force re-evaluation of permissions at next check.
			$this->rights = null ;
			// (#6288) reload groups as we may have switched orgs
			$dbAuthGroups = $this->getProp('AuthGroups');
			$this->groups = $dbAuthGroups->getGroupIDListForAuthAndOrg(
					$this->auth_id, $this->getSeatSectionID()
			);
			$this->returnProp($dbAuthGroups);
			// (#6288) Now check a permission to kickstart regeneration of rights.
			$this->isAllowed('auth_orgs', 'transcend');
		}
		return $this;
	}
	
	/**
	 * The state for "current org" is changing for an account.
	 * @param AuthOrg $aOrg - the new org.
	 * @return $this Returns $this for chaining.
	 */
	protected function doWhenChangeOrg( AuthOrg $aNewOrg=null )
	{
		$dbAuth = $this->getAuthModel();
		//ensure we store the current org
		$this->setSeatingSection($aNewOrg);
		// (#6288) Need to reload groups as well, since we may have switched orgs
		$this->loadGroupsList();
		if( $dbAuth->isAccountInSessionCache() )
		{ // Ensure that the session's account cache is really updated.
			$dbAuth->saveAccountToSessionCache($this);
		}
		// Ensure that the if cookies are used, we save org info, too.
		$dbAuth->updateCookieForOrg($aNewOrg);
		//save which org we last accessed.
		$dbAuthPrefs = $this->getAuthPrefsModel();
		$theOrgID = ( !empty($aNewOrg->org_id) ) ? $aNewOrg->org_id : AuthModel::ORG_ID_4_ROOT;
		$dbAuthPrefs->setPreference($this->auth_id, 'organization', 'last_org', $theOrgID);
		$this->returnProp($dbAuthPrefs);
	}
	
	/**
	 * The system is switching to a different org, see if we should act.
	 * @param AuthOrg $aNewOrg - the new org.
	 * @return $this Returns $this for chaining.
	 */
	public function onChangeOrg( AuthOrg $aNewOrg=null )
	{
		//if the org indeed changed from what our account had before, do some stuff
		if ( (empty($aNewOrg) && !empty($this->mSeatingSection)) || //root vs non-root
			(!empty($aNewOrg) && empty($this->mSeatingSection)) ||  //non-root vs root
			(!empty($aNewOrg) && !empty($this->mSeatingSection) &&  //non-root for both, compare IDs
					$aNewOrg->org_id != $this->mSeatingSection->org_id
			)
		) {
			$this->doWhenChangeOrg($aNewOrg);
		}
		return $this;
	}
	
}//end class

}//end namespace
