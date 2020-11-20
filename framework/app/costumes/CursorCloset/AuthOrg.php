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
use com\blackmoonit\Strings ;
use com\blackmoonit\exceptions\DbException ;
use BitsTheater\Model ;
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\CursorCloset\ARecord as BaseCostume;
{//namespace begin

/**
 * Auth accounts can use this costume to wrap auth org info.
 * PDO statements can fetch data directly into this class.
 */
class AuthOrg extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;
	
	/**
	 * Validates the input string as a potential "short name" for a new
	 * organization.
	 *
	 * <ul>
	 * <li>The string must be composed entirely of letters, digits, and
	 *     underscores.</li>
	 * <li>The string may not begin with an underscore.</li>
	 * <li>The string may not begin with a digit. (#6268)</li>
	 * </ul>
	 *
	 * @param string $aInput the input to be validated
	 * @return boolean <code>true</code> iff the input is valid
	 * @since BitsTheater v4.1.0
	 */
	public static function validateOrgShortName( $aInput )
	{
		if( ! preg_match( "/^((?![0-9_])\w)([\w_])*$/", $aInput ) )
			return false ; // Must be letters, numbers, or underscores.
		
		// Future: Additional disqualification criteria here, returning false.
			
		return true ;
	}

	/**
	 * Given a connected <code>Model</code>, determines the organization in
	 * whose database that model connection resides.
	 * @param Model $aModel a connected model
	 * @return boolean|NULL|AuthOrg an instance of this costume, populated with
	 *  the data from a discovered organization, or <code>null</code> if no org
	 *  is found, or <code>false</code> under certain error conditions
	 * @since BitsTheater v4.1.0
	 */
	public static function forModelConnection( Model $aModel=null )
	{
		if( $aModel === null )
		{
			Strings::errorLog( __METHOD__
					. ' was called with no model reference.' ) ;
			return false ;
		}
		$theContext = $aModel->getDirector() ;
		$theDbName = $aModel->getDbConnInfo()->dbName ;
		$dbOrgs = $theContext->getProp( 'Auth' ) ;     // descends from AuthOrgs
		try
		{
			$theOrgInfo = $dbOrgs->getOrgByName( $theDbName ) ;
			if( $theOrgInfo === null ) return null ;   // No matching org found.
			
			$thisClass = get_called_class() ;
			$theOrg = new $thisClass( $dbOrgs, null ) ;
			$theOrg->setDataFrom( $theOrgInfo ) ;
			
			return $theOrg ;
		}
		catch( DbException $dbx )
		{
			$theContext->errorLog( __METHOD__ . ' failed: '
					. $dbx->getMessage() ) ;
			return false ;
		}
	}
	
	//included by default
	public $org_id;
	public $org_name;
	public $org_title;
	public $org_desc;
	public $parent_org_id;
	public $parent_authgroup_id;
	
	//specifically excluded until we find a reason to include
	//public $dbconn;
	
	/**
	 * Virtual property populated only when fetching data for a sub-organization
	 * @var AuthOrg
	 * @since BitsTheater v4.1.0
	 */
	public $parent_org = null ;
	
	//not included by default
	public $created_by;
	public $updated_by;
	public $created_ts;
	public $updated_ts;
	
	/**
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList($aFieldList)
	{
		if ( empty($aFieldList) ) {
			$aFieldList = array_values(array_diff(static::getDefinedFields(), array(
					'created_by',
					'created_ts',
					'updated_by',
					'updated_ts',
					'parent_org',      // Don't export the parent org if loaded.
			)));
		}
		return parent::setExportFieldList($aFieldList);
	}
	
	/**
	 * Loads this org's parent org into the <code>parent_org</code> property.
	 * If this isn't a sub-org, then the method returns trivially.
	 * @return boolean|\BitsTheater\costumes\CursorCloset\AuthOrg $this, or
	 *  <code>false</code> if the costume instance isn't bound to a model.
	 */
	public function loadParentOrg( $bForce=false )
	{
		if( !empty( $this->parent_org_id ) )
		{ // Try to load the parent org, if we haven't already.
			if( !empty( $this->parent_org ) && !$bForce )
			{
//				Strings::debugLog( __METHOD__ . ' skipped; org already loaded.' ) ;
				return $this ;
			}
			if( empty( $this->getModel() ) )
			{ // ...but we can't, because we have no model.
				Strings::errorLog( __METHOD__ . ' has no model to load orgs.' );
				return false ;
			}
			$theParentInfo = $this->getModel()
					->getOrganization( $this->parent_org_id ) ;
			$thisClass = get_called_class() ;
			$this->parent_org = new $thisClass( $this->getModel(), null ) ;
			$this->parent_org->setDataFrom( $theParentInfo ) ;
		}
		else $this->parent_org = null ;
		
		return $this ;
	}
	
	/**
	 * Return the default columns to sort by and whether or not they should be ascending.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	static public function getDefaultSortColumns()
	{ return array('org_title' => true); }
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	static public function isFieldSortable($aFieldName)
	{
		$theAllowedSorts = array_diff(static::getDefinedFields(), array(
				'dbconn',
		));
		return ( array_search($aFieldName, $theAllowedSorts)!==false );
	}
	
	/**
	 * Create this object given a context to grab the model and inital data.
	 * @param IDirected $aContext - the context to use to get the model.
	 * @param array|object $aData - the initial data to use.
	 * @return $this Returns the new object.
	 */
	static public function getInstance( IDirected $aContext, $aData=null )
	{
		$o = static::withModel( $aContext->getProp('Auth') ) ;
		if( $aData !== null )
			$o->setDataFrom($aData) ;
		return $o ;
	}
	
}//end class
	
}//end namespace
