<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace BitsTheater\models\PropCloset;
use BitsTheater\Model as BaseModel;
use BitsTheater\Scene;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornForAuditFields;
use BitsTheater\costumes\WornForFeatureVersioning;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Arrays;
use BitsTheater\BrokenLeg ;
use PDO;
use PDOException;
use Exception;
{//begin namespace

/**
 * Groups were made its own model so that you could have a
 * auth setup where groups and group memebership were
 * defined by another entity (BBS auth or WordPress or whatever).
 */
class BitsGroups extends BaseModel implements IFeatureVersioning
{
	use WornForFeatureVersioning, WornForAuditFields;
	
	/**
	 * Used by meta data mechanism to keep the database up-to-date with the code.
	 * A non-NULL string value here means alter-db-schema needs to be managed.
	 * @var string
	 */
	const FEATURE_ID = 'BitsTheater/groups';
	const FEATURE_VERSION_SEQ = 4; //always ++ when making db schema changes
	//v 4 - added Audit fields to all tables
	//v 3 - removed group_type field from groups table
	//v 2 - added groups_reg_codes child table

	public $tnGroups;			const TABLE_Groups = 'groups';
	public $tnGroupMap;			const TABLE_GroupMap = 'groups_map';
	public $tnGroupRegCodes;	const TABLE_GroupRegCodes = 'groups_reg_codes';

	/** The constant, assumed ID of the "unregistered user" group. */
	const UNREG_GROUP_ID = 0 ;
	/** The constant, assumed ID of the "titan" superuser group. */
	const TITAN_GROUP_ID = 1 ;
	/** The constant, assumed ID of the "default APP_ID reg code group. */
	const DEFAULT_REG_GROUP_ID = 2;

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnGroups = $this->tbl_.self::TABLE_Groups;
		$this->tnGroupMap = $this->tbl_.self::TABLE_GroupMap;
		$this->tnGroupRegCodes = $this->tbl_.self::TABLE_GroupRegCodes;
	}
	
	/**
	 * Future db schema updates may need to create a temp table of one
	 * of the table definitions in order to update the contained data,
	 * putting schema here and supplying a way to provide a different name
	 * allows this process.
	 * @param string $aTABLEconst - one of the defined table name consts.
	 * @param string $aTableNameToUse - (optional) alternate name to use.
	 */
	protected function getTableDefSql($aTABLEconst, $aTableNameToUse=null) {
		switch($aTABLEconst) {
		case self::TABLE_Groups:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnGroups;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( group_id INT NOT NULL AUTO_INCREMENT".
						", group_name NCHAR(60) NOT NULL".
						", parent_group_id INT NULL".
						//", group_desc NCHAR(200) NULL".
						", ".CommonMySQL::getAuditFieldsForTableDefSql().
						", PRIMARY KEY (group_id)".
						") CHARACTER SET utf8 COLLATE utf8_general_ci";
			}//switch dbType
		case self::TABLE_GroupMap:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnGroupMap;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( account_id INT NOT NULL".
						", group_id INT NOT NULL".
						", ".CommonMySQL::getAuditFieldsForTableDefSql().
						", PRIMARY KEY (account_id, group_id)".
						//", UNIQUE KEY (group_id, account_id)".  IDK if it'd be useful
						") CHARACTER SET utf8 COLLATE utf8_general_ci";
			}//switch dbType
		case self::TABLE_GroupRegCodes:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnGroupRegCodes;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( group_id INT NOT NULL".
						", reg_code NCHAR(64) NOT NULL".
						", ".CommonMySQL::getAuditFieldsForTableDefSql().
						", PRIMARY KEY (reg_code, group_id)".
						") CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT='Auto-assign group_id if Registration Code matches reg_code'";
			}//switch dbType
		}//switch TABLE const
	}
	
	/**
	 * Called during website installation and db re-setupDb feature.
	 * Never assume the database is empty.
	 */
	public function setupModel() {
		$this->setupTable( self::TABLE_Groups, $this->tnGroups ) ;
		$this->setupTable( self::TABLE_GroupMap, $this->tnGroupMap ) ;
		$this->setupTable( self::TABLE_GroupRegCodes, $this->tnGroupRegCodes ) ;
	}
	
	/**
	 * If the groups table is empty, supply some default starter-data.
	 */
	protected function setupDefaultDataForGroups()
	{
		if ($this->isEmpty($this->tnGroups)) {
			// Start building the query until we have the audit field values...
			$theSql = SqlBuilder::withModel($this);
			$theSql->startWith('INSERT INTO')->add($this->tnGroups);
			$this->setAuditFieldsOnInsert($theSql);
			
			// Now use this to construct each of the rows of group data.
			// This works because audit fields are the only params set in the
			// SqlBuilder, from setAuditFieldsOnInsert().
			$theDefaultData =
					$this->buildDefaultGroupDataArray( $theSql->myParams ) ;
			
			// Now continue building the query.
			$theSql->mustAddParam('group_id', 0, PDO::PARAM_INT);
			$theSql->mustAddParam('group_name');
			try {
				$theSql->execMultiDML($theDefaultData);
			} catch (PDOException $pdoe) {
				throw $theSql->newDbException(__METHOD__, $pdoe);
			}
		
			// As required by buildDefaultGroupDataArray(), go back and update
			// the group zero ID to be zero. The fake ID that we're updating is
			// equal to the count of rows we're about to insert.
			$theSql = SqlBuilder::withModel($this);
			$theSql->startWith('UPDATE')->add($this->tnGroups);
			$this->setAuditFieldsOnUpdate($theSql);
			$theSql->mustAddParam('group_id', 0, PDO::PARAM_INT)
				->startWhereClause()
				->mustAddFieldAndParam( 'group_id', 'fake_group_id',
						count($theDefaultData), PDO::PARAM_INT )
				->endWhereClause()
				;
			$theSql->logSqlDebug(__METHOD__) ; // DEBUG
			try
			{
				$theResult = $theSql->execDML() ;
				$this->debugLog( $theResult ) ;
			}
			catch (PDOException $pdoe)
			{ throw $theSql->newDbException(__METHOD__, $pdoe) ; }
		}
	}
	
	/**
	 * Constructs the group information for insertion into the database.
	 *
	 * The group ID for "group zero" (unregistered users) will be assigned a
	 * fake value by this function, because the group_id column is
	 * auto-incremented and will start at 1, because a value of 0 may have a
	 * special meaning to some database engines. The calling function will need
	 * to retroactively update this ID back to zero. Since the "fake" value is
	 * the count of the groups that are inserted, the calling function can
	 * discover the fake value by simply counting the size of the returned
	 * array.
	 *
	 * @param array $aAuditFields A map of audit fields for the row.
	 * @see BitsGroups::setupDefaultDataForGroups()
	 */
	protected function buildDefaultGroupDataArray( $aAuditFields )
	{
		$theGroupNames = $this->getRes('AuthGroups/group_names');
		// Substitute group ID in databases where 0 is meaningful in auto-inc
		// columns. This needs to be retro-updated in the calling function.
		// The fake value is the count of the number of groups to be inserted.
		$theFakeZeroID = count($theGroupNames) ;
		$theDefaultData = array() ;
		$theID = 0 ;
		foreach( $theGroupNames as $theGroupName )
		{ // Construct value set for each row with group data and audit fields.
			array_push( $theDefaultData,
				array_merge(
					array(
							'group_id' => ( $theID === 0 ? $theFakeZeroID : $theID ),
							'group_name' => $theGroupName
						),
					$aAuditFields
				));
			$theID++ ;
		}
		return $theDefaultData ;
	}
	
	/**
	 * If the group registration code table is empty,
	 * supply some default starter-data.
	 */
	protected function setupDefaultDataForGroupRegCodes()
	{
		if ($this->isEmpty($this->tnGroupRegCodes)) {
			$theSql = SqlBuilder::withModel($this);
			$theSql->startWith('INSERT INTO')->add($this->tnGroupRegCodes);
			$this->setAuditFieldsOnInsert($theSql);
			$theSql->mustAddParam('group_id', static::DEFAULT_REG_GROUP_ID, PDO::PARAM_INT);
			$theSql->mustAddParam('reg_code', $this->getDirector()->app_id);
			try { $theSql->execDML(); }
			catch (PDOException $pdoe)
			{ throw $theSql->newDbException(__METHOD__, $pdoe); }
		}
	}
	
	/**
	 * When tables are created, default data may be needed in them. Check
	 * the table(s) for isEmpty() before filling it with default data.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function setupDefaultData($aScene=null) {
		//only want default data if the groups table is empty
		if ($this->isEmpty($this->tnGroups)) {
			$this->setupDefaultDataForGroups();
			$this->setupDefaultDataForGroupRegCodes();
		}
	}
	
	/**
	 * Other models may need to query ours to determine our version number
	 * during Site Update. Without checking SetupDb, determine what version
	 * we may be running as.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function determineExistingFeatureVersion($aScene) {
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				if (!$this->exists($this->tnGroupRegCodes))
					return 1 ;
				else if( $this->isFieldExists( 'group_type', $this->tnGroups ) )
					return 2 ;
				else if ( !$this->isFieldExists('created_by', $this->tnGroups)
						|| !$this->isFieldExists('created_by', $this->tnGroupMap)
						|| !$this->isFieldExists('created_by', $this->tnGroupRegCodes) )
					return 3;
		}//switch
		return self::FEATURE_VERSION_SEQ ;
	}
	
	/**
	 * Check current feature version and compare it to the
	 * current version, upgrading the db schema as needed.
	 * @param array $aFeatureMetaData - the models current feature metadata.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function upgradeFeatureVersion($aFeatureMetaData, $aScene) {
		$theSeq = $aFeatureMetaData['version_seq'];
		$this->debugLog('Running '.__METHOD__.' v'.$theSeq.'->v'.self::FEATURE_VERSION_SEQ);

		// This switch block's cases are tests against the current version
		// sequence number. The cases must be implemented sequentially (low to
		// high) and not use break, so that all changes between versions will
		// fall through cumulatively to the end.
		switch (true) {
			case ($theSeq<2):
				//create new GroupRegCodes table
				$this->setupTable( self::TABLE_GroupRegCodes, $this->tnGroupRegCodes ) ;
				$this->setupDefaultDataForGroupRegCodes();
				$this->debugLog('v2: added table '.$this->tnGroupRegCodes);
			case ($theSeq<3):
				if( $this->isFieldExists( 'group_type', $this->tnGroups ) )
				{
					//two step process to remove the unused field: re-number the group_id=5, 0-group-type to ID=0
					$theSql = 'UPDATE '.$this->tnGroups.' SET group_id=0 WHERE group_id=5 AND group_type=0 LIMIT 1';
					$this->execDML($theSql);
					//now alter the table and drop the column
					$theSql = 'ALTER TABLE '.$this->tnGroups.' DROP group_type';
					$this->execDML($theSql);
					$this->debugLog('v3: removed field group_type from '.$this->tnGroups);
				} else {
					$this->debugLog('v3: already removed field group_type from '.$this->tnGroups);
				}
			case ($theSeq < 4):
			{
				$this->addAuditFieldsForTable($this->tnGroups, 4);
				$this->addAuditFieldsForTable($this->tnGroupMap, 4);
				$this->addAuditFieldsForTable($this->tnGroupRegCodes, 4);
			}
		}//switch
	}
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnGroups : $aTableName );
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnGroups : $aTableName );
	}
	
	/**
	 * Retrieve a single group row.
	 * @param number $aGroupId - the group_id to get.
	 * @param string $aFieldList - which fields to return, default is all of them.
	 * @return array Returns the row data.
	 */
	public function getGroup($aGroupId, $aFieldList=null) {
		if ($aGroupId<0)
			throw new \InvalidArgumentException('invalid $aGroupId param');
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT')->addFieldList($aFieldList);
		$theSql->add('FROM')->add($this->tnGroups);
		$theSql->startWhereClause();
		$theSql->mustAddParam('group_id', $aGroupId, PDO::PARAM_INT);
		$theSql->endWhereClause();
		try { return $theSql->getTheRow(); }
		catch (PDOException $pdoe)
		{ throw $theSql->newDbException(__METHOD__, $pdoe); }
	}
	
	/**
	 * Insert a group record.
	 * @param Object|Scene $aDataObject - the (usually Scene) object containing
	 *   the POST data used for insert.
	 * @return Returns the data posted to the database.
	 */
	public function add($aDataObject) {
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		$theSql->startWith('INSERT INTO')->add($this->tnGroups);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_name');
		$theSql->mustAddParam('parent_group_id', null, PDO::PARAM_INT);
		$theSql->addParamIfDefined('group_id', PDO::PARAM_INT);
		//$theSql->logSqlDebug(__METHOD__);
		try {
			return $theSql->execDMLandGetParams();
		} catch (PDOException $pdoe) {
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}
	
	/**
	 * Remove a group and its child data.
	 * @param integer $aGroupId - the group ID.
	 * @return Returns an array('group_id'=>$aGroupId).
	 */
	public function del($aGroupId) {
		$theSql = SqlBuilder::withModel($this);
		$theGroupId = intval($aGroupId);
		if ($theGroupId>self::TITAN_GROUP_ID) try {
			$this->db->beginTransaction();
			
			$theSql->startWith('DELETE FROM')->add($this->tnGroupMap);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupId, PDO::PARAM_INT);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$theSql->reset()->startWith('DELETE FROM')->add($this->tnGroups);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupId, PDO::PARAM_INT);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$this->db->commit();
			return $theSql->myParams;
		} catch (PDOException $pdoe) {
			$this->db->rollBack();
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}
	
	/**
	 * Add a set of records to the account/group map table.
	 * @param number $aAcctId - the account ID.
	 * @param array $aGroupIds - the group IDs.
	 * @throws DbException if there was a problem.
	 */
	public function addGroupsToAccount($aAcctId, $aGroupIds)
	{
		$theAcctId = intval($aAcctId);
		if ($theAcctId<1)
			throw new \InvalidArgumentException('invalid $aAcctId param');
		if (empty($aGroupIds) || !is_array($aGroupIds))
			throw new \InvalidArgumentException('invalid $aGroupIds param');
		$theSql = SqlBuilder::withModel($this);
		try {
			$theSql->startWith('INSERT INTO')->add($this->tnGroupMap);
			$this->setAuditFieldsOnInsert($theSql);
			$theSql->mustAddParam('account_id', $theAcctId, PDO::PARAM_INT);
			$theSql->mustAddParam('group_id', 0, PDO::PARAM_INT);
			//use the params added so far to help create our multi-DML array
			//  every entry needs to match the number of SQL parameters used
			$theParamList = array();
			foreach ($aGroupIds as $anID) {
				$theParamList[] = array(
						'created_ts' => $theSql->getParam('created_ts'),
						'updated_ts' => $theSql->getParam('updated_ts'),
						'created_by' => $theSql->getParam('created_by'),
						'updated_by' => $theSql->getParam('updated_by'),
						'account_id' => $theSql->getParam('account_id'),
						'group_id' => $anID,
				);
			}
			$theSql->execMultiDML($theParamList);
		} catch (PDOException $pdoe) {
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}
	
	/**
	 * Add a record to the account/group map table.
	 * @param number $aGroupId - the group ID.
	 * @param number $aAcctId - the account ID.
	 * @return Returns the data added.
	 */
	public function addAcctMap($aGroupId, $aAcctId) {
		$theGroupId = intval($aGroupId);
		$theAcctId = intval($aAcctId);
		if ($theGroupId<self::UNREG_GROUP_ID)
			throw new \InvalidArgumentException('invalid $aGroupId param');
		if ($theAcctId<1)
			throw new \InvalidArgumentException('invalid $aAcctId param');
		$theSql = SqlBuilder::withModel($this);
		try {
			$theSql->startWith('INSERT INTO')->add($this->tnGroupMap);
			$this->setAuditFieldsOnInsert($theSql);
			$theSql->mustAddParam('account_id', $theAcctId, PDO::PARAM_INT);
			$theSql->mustAddParam('group_id', $theGroupId, PDO::PARAM_INT);
			return $theSql->execDMLandGetParams();
		} catch (PDOException $pdoe) {
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}
	
	/**
	 * Remove a record from the account/group map table.
	 * @param integer $aGroupId - the group ID.
	 * @param integer $aAcctId - the account ID.
	 * @return Returns the data removed.
	 */
	public function delAcctMap($aGroupId, $aAcctId) {
		$theGroupId = intval($aGroupId);
		$theAcctId = intval($aAcctId);
		if ($theGroupId<self::UNREG_GROUP_ID)
			throw new \InvalidArgumentException('invalid $aGroupId param');
		if ($theAcctId<1)
			throw new \InvalidArgumentException('invalid $aAcctId param');
		$theSql = SqlBuilder::withModel($this);
		try {
			$theSql->startWith('DELETE FROM')->add($this->tnGroupMap);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupId, PDO::PARAM_INT);
			$theSql->setParamPrefix(' AND ');
			$theSql->mustAddParam('account_id', $theAcctId, PDO::PARAM_INT);
			$theSql->endWhereClause();
			return $theSql->execDMLandGetParams();
		} catch (PDOException $pdoe) {
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}
	
	/**
	 * Get the groups a particular account belongs to.
	 * @param integer $aAcctId - the account ID.
	 * @return Returns the array of group IDs.
	 */
	public function getAcctGroups($aAcctId) {
		$theAcctId = intval($aAcctId);
		if ($theAcctId<1)
			throw new \InvalidArgumentException('invalid $aAcctId param');
		$theSql = SqlBuilder::withModel($this);
		try {
			$theSql->startWith('SELECT group_id FROM')->add($this->tnGroupMap);
			$theSql->startWhereClause();
			$theSql->mustAddParam('account_id', $theAcctId, PDO::PARAM_INT);
			$theSql->endWhereClause();
			return $theSql->query()->fetchAll(PDO::FETCH_FUNC, function($id) {
				return intval($id);
			});
		} catch (PDOException $pdoe) {
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}
	
	/**
	 * Creates a new user group.
	 * @param string $aGroupName the new group's name
	 * @param integer $aGroupParentId the ID of the group from which permission
	 *  settings should be inherited (default null) (deprecated in Pulse 3.0)
	 * @param string $aGroupRegCode the group's registration code (default
	 *  blank)
	 * @param integer $aGroupCopyID the ID of a group from which permissions
	 *  should be *copied* into the new group.
	 * @throws DbException if something goes wrong in the DB
	 */
	public function createGroup( $aGroupName, $aGroupParentId=null, $aGroupRegCode=null, $aGroupCopyID=null )
	{
		$theGroupParentId = intval($aGroupParentId);
		if ($theGroupParentId<=self::UNREG_GROUP_ID)
			$theGroupParentId = null;
		
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'group_name' => $aGroupName,
				'parent_group_id' => $theGroupParentId,
		));
		$theSql->startWith( 'INSERT INTO ' . $this->tnGroups ) ;
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_name');
		$theSql->addParam('parent_group_id');
		$theGroupID = 0 ;
		try { $theGroupID = $theSql->addAndGetId() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
		
		if( ! empty( $theGroupID ) && ! empty( $aGroupRegCode ) )
			$this->insertGroupRegCode( $theGroupID, $aGroupRegCode ) ;

		$theResults = $theSql->myParams;
		$theResults['group_id'] = $theGroupID;
		$theResults['reg_code'] = (!empty($aGroupRegCode)) ? $aGroupRegCode : null;

		if( ! empty( $theGroupID ) && ! empty( $aGroupCopyID ) )
		{
			$dbPerms = $this->getProp('Permissions') ;
			try
			{
				$theCopyResult =
					$dbPerms->copyPermissions( $aGroupCopyID, $theGroupID ) ;
				$theResults['copied_group'] = $aGroupCopyID ;
				$theResults['copied_perms'] = $theCopyResult['count'] ;
			}
			catch( Exception $x )
			{
				$theExClass = (new \ReflectionClass($x))->getShortName();
				$this->errorLog( __METHOD__
						. ' failed to copy permissions for group ['
						. $aGroupCopyID . '] because of a '
						. $theExClass . ': '
						. $x->getMessage()
						);
				$theResults['copied_group'] = -1 ;
				$theResults['group_copy_error'] = $x->getMessage() ;
			}
		}

		return $theResults ;
	}

	/**
	 * Updates an existing group.
	 * @param Scene $aScene a scene containing account group data
	 * @throws DbException if something goes wrong in the DB
	 */
	public function modifyGroup( Scene $v )
	{
		$theGroup = ((object)( $this->getGroup( $v->group_id ) )) ;
		if( empty( $theGroup ) )
			throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $v->group_id ) ;
		$theCols = array( 'group_name', 'parent_group_id' ) ;
		foreach( $theCols as $theCol )
		{ // Update the values of the existing rights group in cache.
			if( property_exists( $v, $theCol ) ) // isset() doesn't pick up null
				$theGroup->{$theCol} = $v->{$theCol} ;
			else
				$theGroup->{$theCol} = null ;
		}

		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($theGroup)
			->startWith( 'UPDATE ' . $this->tnGroups )
			;
		$this->setAuditFieldsOnUpdate($theSql)
			->mustAddParam( 'group_name', 'Group ' . $v->group_id )
			->mustAddParam( 'parent_group_id', null, PDO::PARAM_INT )
			->startWhereClause()
			->mustAddParam( 'group_id', null, PDO::PARAM_INT )
			->endWhereClause()
			;
		//$theSql->logSqlDebug(__METHOD__);
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox) ; }

		$theRegCode = $this->getGroupRegCode( $theGroup->group_id ) ;

		if( property_exists( $v, 'reg_code' ) )
		{ // Modify the reg code only if specified in the request (BitsTheater 3.6.0)
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($v)
				->startWith( 'DELETE FROM ' . $this->tnGroupRegCodes )
				->startWhereClause()
				->mustAddParam( 'group_id' )
				->endWhereClause()
				;
			try { $theSql->execDML() ; }
			catch( PDOException $pdox )
			{
				$errMsg = __METHOD__ . ' failed when deleting the old registration code.';
				$this->errorLog($errMsg);
				throw new DbException( $pdox, $errMsg ) ;
			}

			if( ! empty( $v->reg_code ) )
				$this->insertGroupRegCode( $v->group_id, $v->reg_code ) ;

			$theRegCode = $v->reg_code ;
		}

		return array(
				'group_id' => $theGroup->group_id,
				'group_name' => $theGroup->group_name,
				'parent_group_id' => $theGroup->parent_group_id,
				'reg_code' => $theRegCode,
			);
	}

	/**
	 * Fetches the registration code for a given group ID.
	 * @param string $aGroupID the group ID
	 * @throws DbException if something goes wrong while searching
	 * @return string the registration code for that group
	 */
	protected function getGroupRegCode( $aGroupID )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT reg_code FROM ' . $this->tnGroupRegCodes )
			->startWhereClause()
			->mustAddParam( 'group_id', $aGroupID )
			->endWhereClause()
			;
		try { $theRow = $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
		return $theRow['reg_code'] ;
	}
	
	/**
	 * Consumed by createGroup() and modifyGroup() to insert a new registration
	 * code for an existing group ID.
	 * @param integer $aGroupID the group ID
	 * @param string $aRegCode the new registration code
	 */
	protected function insertGroupRegCode( $aGroupID, $aRegCode )
	{
		$theGroupId = intval($aGroupID);
		$theRegCode = trim($aRegCode);
		if ($theGroupId>self::UNREG_GROUP_ID && !empty($theRegCode))
		{
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'group_id' => $theGroupId,
					'reg_code' => $theRegCode,
			));
			$theSql->startWith( 'INSERT INTO ' . $this->tnGroupRegCodes );
			$this->setAuditFieldsOnInsert($theSql);
			$theSql->mustAddParam('group_id');
			$theSql->mustAddParam('reg_code');
			try { $theSql->execDML() ; }
			catch( PDOException $pdox )
			{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
		}
	}

	/**
	 * @return Return array(group_id => reg_code).
	 */
	public function getGroupRegCodes() {
		$theSql = "SELECT * FROM {$this->tnGroupRegCodes} ORDER BY group_id";
		$ps = $this->query($theSql);
		$theResult = Arrays::array_column_as_key($ps->fetchAll(), 'group_id');
		return $theResult;
	}
	
	/**
	 * See if an entered registration code matches a group_id.
	 * @param string $aAppId - the site app_id.
	 * @param string $aRegCode - the entered registration code.
	 * @return integer Returns the group_id which matches or 0 if none.
	 */
	public function findGroupIdByRegCode($aAppId, $aRegCode) {
		$theRegCode = trim($aRegCode);
		if (!$this->isEmpty($this->tnGroupRegCodes)) {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'reg_code' => $theRegCode,
			));
			$theSql->startWith('SELECT group_id FROM')->add($this->tnGroupRegCodes);
			$theSql->startWhereClause()->mustAddParam('reg_code')->endWhereClause();
			$theRow = $theSql->getTheRow();
			return (!empty($theRow)) ? intval($theRow['group_id']) : self::UNREG_GROUP_ID;
		} else {
			return ($theRegCode==$aAppId) ? static::DEFAULT_REG_GROUP_ID : self::UNREG_GROUP_ID;
		}
	}

	/**
	 * Returns a dictionary of all permission group data.
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *  "unregistered" and "titan" groups that are defined by default when the
	 *  system is installed
	 * @throws DbException if an error happens in the query itself
	 */
	public function getAllGroups( $bIncludeSystemGroups=false )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT G.group_id, G.group_name,' )
			->add( 'G.parent_group_id, GRC.reg_code' )
			->add( 'FROM ' . $this->tnGroups . ' AS G' )
			->add( 'LEFT JOIN ' . $this->tnGroupRegCodes )
			->add(   'AS GRC USING (group_id)' )
			;
		if( ! $bIncludeSystemGroups )
		{
			$theSql->startWhereClause()
				->setParamOperator( '<>' )
			 	->addFieldAndParam( 'group_id', 'unreg_group_id',
			 			self::UNREG_GROUP_ID, PDO::PARAM_INT )
			 	->setParamPrefix( ' AND ' )
				->addFieldAndParam( 'group_id', 'titan_group_id',
						self::TITAN_GROUP_ID, PDO::PARAM_INT )
				->endWhereClause()
				;
		}
		$theSql->add( 'ORDER BY G.group_id' ) ;

		try { return $theSql->query()->fetchAll() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	/**
	 * Indicates whether a group with the specified ID is defined.
	 * @param integer $aGroupID the sought group ID
	 * @return boolean true if the group is found, false otherwise
	 */
	public function groupExists( $aGroupID=null )
	{
		$theGroupId = intval($aGroupID);
		if( $theGroupId <= self::UNREG_GROUP_ID ) return false ;

		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT group_id FROM ' . $this->tnGroups )
			->startWhereClause()
			->mustAddParam( 'group_id', $theGroupId, PDO::PARAM_INT )
			->endWhereClause()
			;
		$theResult = $theSql->getTheRow() ;
		return ( empty( $theResult ) ? false : true ) ;
	}

	/**
	 * Fetches the account record for any account that is mapped into the
	 * specified group.
	 * @param integer $aGroupID
	 * @return PDOStatement - the result set
	 * @throws DbException if something goes wrong
	 * @since BitsTheater 3.6
	 */
	public function getAccountsInGroup( $aGroupID )
	{
		$theGroupID = intval($aGroupID) ;
		$dbAccounts = $this->getProp( 'Accounts' ) ;
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT A.* FROM ' )
			->add( $dbAccounts->tnAccounts . ' AS A' )
			->add( ' LEFT JOIN ' )
			->add( $this->tnGroupMap . ' AS GM USING (account_id) ' )
			->startWhereClause()
			->mustAddParam( 'group_id', $aGroupID )
			->endWhereClause()
			;
		$this->returnProp( $dbAccounts ) ;
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	/**
	 * Returns a dictionary of all permission groups IDs and names.
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *   "unregistered" and "titan" groups that are defined by default when the
	 *   system is installed
	 * @param string[]|string $aFieldList - (optional) which fields to return,
	 *   the default is all of them.
	 * @param bool[] $aSortList - (optional) how to sort the data, use array
	 *   format of <code>array[fieldname => ascending=true]</code>.
	 * @throws DbException if an error happens in the query itself
	 */
	public function getListForPicker( $bIncludeSystemGroups=false,
			$aFieldList=null, $aSortList=null )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnGroups)
		;
		if ( !$bIncludeSystemGroups ) {
			$theSql->startWhereClause();
			$theSql->setParamOperator( '<>' );
			$theSql->addFieldAndParam( 'group_id', 'unreg_group_id',
					self::UNREG_GROUP_ID, PDO::PARAM_INT );
			$theSql->setParamPrefix( ' AND ' );
			$theSql->addFieldAndParam( 'group_id', 'titan_group_id',
					self::TITAN_GROUP_ID, PDO::PARAM_INT );
			$theSql->endWhereClause();
		}
		$theSql->applyOrderByList( $aSortList ) ;
		//$theSql->logSqlDebug(__METHOD__);
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
}//end class

}//end namespace
