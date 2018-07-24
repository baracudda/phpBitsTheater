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
	 * Given a connected <code>Model</code>, determines the organization in
	 * whose database that model connection resides.
	 * @param Model $aModel a connected model
	 * @return boolean|NULL|AuthOrg an instance of this costume, populated with
	 *  the data from a discovered organization, or <code>null</code> if no org
	 *  is found, or <code>false</code> under certain error conditions
	 * @since BitsTheater [NEXT]
	 */
	public static function forModelConnection( Model $aModel=null )
	{
		$theContext = $aModel->getDirector() ;
		if( $aModel === null )
		{
			$theContext->errorLog( __METHOD__
					. ' was called with no model reference.' ) ;
			return false ;
		}
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
	
	//specifically excluded until we find a reason to include
	//public $dbconn;
	
	/**
	 * Virtual property populated only when fetching data for a sub-organization
	 * @var AuthOrg
	 * @since BitsTheater [NEXT]
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
			$aFieldList = array_diff(static::getDefinedFields(), array(
					'created_by',
					'created_ts',
					'updated_by',
					'updated_ts',
					'parent_org',      // Don't export the parent org if loaded.
			));
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
		if( !empty( $this->parent_org_id ) && ( $bForce || empty( $this->parent_org ) ) )
		{ // Try to load the parent org, if we haven't already.
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
	
}//end class
	
}//end namespace
