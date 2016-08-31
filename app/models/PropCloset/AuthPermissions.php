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
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\models\Auth;
use BitsTheater\models\PropCloset\BitsGroups ;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use com\blackmoonit\Arrays;
use PDO;
use PDOStatement;
use PDOException;
use BitsTheater\BrokenLeg ;
use BitsTheater\outtakes\RightsException ;
{//namespace begin

class AuthPermissions extends BaseModel {
	const VALUE_Allow = '+';
	const VALUE_Deny = 'x';
	const FORM_VALUE_Allow = 'allow';
	const FORM_VALUE_Deny = 'deny';
	const FORM_VALUE_Disallow = 'disallow';
	const FORM_VALUE_DoNotShow = 'deny-disable';

	public $tnPermissions; const TABLE_Permissions = 'permissions';

	public $_permCache = array();

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnPermissions = $this->tbl_.self::TABLE_Permissions;
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
		case self::TABLE_Permissions:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnPermissions;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( namespace CHAR(40) NULL".
						", permission CHAR(40) NOT NULL".
						", group_id INT NOT NULL".
						", value CHAR(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL".
						", PRIMARY KEY (namespace, permission, group_id)".
						", KEY IdxValuePermissions (namespace, value)".
						", UNIQUE KEY IdxGroupPermissions (group_id, namespace, permission)".
						") CHARACTER SET utf8 COLLATE utf8_general_ci";
			}//switch dbType
		}//switch TABLE const
	}
	
	/**
	 * Called during website installation to create whatever the models needs.
	 * Check the database to be sure anything needs to be done and do not assume
	 * a blank database as updates/reinstalls against recovered databases may
	 * occur as well.
	 * @throws DbException
	 */
	public function setupModel() {
		switch ($this->dbType()) {
		case self::DB_TYPE_MYSQL: default:
			try {
				$theSql = $this->getTableDefSql(self::TABLE_Permissions);
				$this->execDML($theSql);
				$this->debugLog($this->getRes('install/msg_create_table_x_success/'.$this->tnPermissions));
			} catch (PDOException $pdoe){
				throw new DbException($pdoe,$theSql);
			}
			break;
		}
	}
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnPermissions : $aTableName );
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnPermissions : $aTableName );
	}
	
	/**
	 * Check group permissions to see if current user account (or passed in one) is allowed.
	 * @param string $aNamespace - namespace of permission.
	 * @param string $aPermission - permission to test against.
	 * @param array $acctInfo - account information (optional, defaults current user).
	 * @return boolean Returns TRUE if allowed, else FALSE.
	 */
	public function isPermissionAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($acctInfo)) {
			$acctInfo =& $this->director->account_info;
		}
		if (empty($acctInfo)) {
			$acctInfo = new AccountInfoCache();
		}
		//Strings::debugLog('acctinfo:'.Strings::debugStr($acctInfo));
		if (!empty($acctInfo->groups) && (array_search(1,$acctInfo->groups,true)!==false)) {
			return true; //group 1 is always allowed everything
		}
		//cache the current users permissions
		if (empty($this->_permCache[$acctInfo->account_id])) {
			try {
				$this->_permCache[$acctInfo->account_id] = array();
				foreach ($acctInfo->groups as $theGroupId) {
					$this->_permCache[$acctInfo->account_id][$theGroupId] = $this->getAssignedRights($theGroupId);
				}
			} catch (DbException $dbe) {
				//use default empty arrays which will mean all permissions will be not allowed
			}
		}
		//$this->debugLog('perms:'.$this->debugStr($this->_permCache));
        //$this->debugLog(__METHOD__.' '.memory_get_usage(true));

		//if any group allows the permission, then we allow it.
		foreach ($this->_permCache[$acctInfo->account_id] as $theGroupId => $theAssignedRights) {
			$theResult = self::FORM_VALUE_Disallow;
			if (!empty($theAssignedRights[$aNamespace]) && !empty($theAssignedRights[$aNamespace][$aPermission]))
				$theResult = $theAssignedRights[$aNamespace][$aPermission];
			//if any group the user is a member of allows the permission, then return true
			if ($theResult==self::FORM_VALUE_Allow) {
				return true;
			}
		}
		//if neither denied, nor allowed, then it is not allowed
		return false;
	}
	
	/**
	 * Query the permissions table for group rights.
	 * @param int $aGroupId - the group id to load.
	 * @return PDOStatement Returns the executed query statement.
	 */
	protected function getGroupRightsCursor($aGroupId) {
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'group_id' => $aGroupId,
		));
		$theSql->startWith('SELECT * FROM')->add($this->tnPermissions);
		$theSql->startWhereClause()->mustAddParam('group_id', 0, PDO::PARAM_INT)->endWhereClause();
		return $theSql->query();
	}
	
	/**
	 * Load the group rights and merge it into the passed in array;
	 * loaded "deny" rights will trump array param.
	 * @param int $aGroupId - the group id
	 * @param array $aRightsToMerge - the already defined rights.
	 */
	protected function loadAndMergeRights($aGroupId, &$aRightsToMerge, $bIsFirstSet) {
		$rs = $this->getGroupRightsCursor($aGroupId);
		if (!empty($rs)) {
			while (($theRow = $rs->fetch())!==false) {
				$thePermissionValue = ($theRow['value']==self::VALUE_Allow)
						? self::FORM_VALUE_Allow
						: (($bIsFirstSet) ? self::FORM_VALUE_Deny : self::FORM_VALUE_DoNotShow);
				$theCurrValue =& $aRightsToMerge[$theRow['namespace']][$theRow['permission']];
				if (empty($theCurrValue) || $theCurrValue==self::FORM_VALUE_Allow)
					$theCurrValue = $thePermissionValue;
			}//while
		}//if
	}
	
	/**
	 * Load up all rights assigned to this group as well as parent groups.
	 * @param int $aGroupId - the group to find assigned rights.
	 * @return array Returns the assigned rights for a given group.
	 */
	public function getAssignedRights($aGroupId) {
		$theGroupId = $aGroupId+0;
		//check rights for group passed in, and then all its parents
		$theMergeList = array($theGroupId => -1);
		/* @var $dbAuth Auth */
		$dbAuth = $this->getProp('Auth');
		$theGroupList = Arrays::array_column_as_key($dbAuth->getGroupList(),'group_id');
		//$this->debugLog('grouplist='.$this->debugStr($theGroupList));
		while ($theGroupId>=0 && !empty($theGroupList[$theGroupId]) && !empty($theGroupList[$theGroupId]['parent_group_id'])) {
			$theGroupId = $theGroupList[$theGroupId]['parent_group_id'];
			//to prevent infinite loops
			if (empty($theMergeList[$theGroupId]))
				$theMergeList[$theGroupId] = 1;
			else
				$theGroupId = null;
			//$this->debugLog('merge='.$this->debugStr($theMergeList));
		}//while

		$theAssignedRights = array();
		foreach ($theMergeList as $theMergeGroupId => $bIsFirstIfNegative) {
			$this->loadAndMergeRights($theMergeGroupId, $theAssignedRights, $bIsFirstIfNegative<0);
		}//foreach
		//$this->debugLog('rights='.$this->debugStr($theAssignedRights));
		return $theAssignedRights;
	}
	
	/**
	 * Like getAssignedRights(int), but returns only the rights that are allowed
	 * for the group, as a Boolean "true"; any right that is in 'disallow' or
	 * 'deny' state is omitted from the result set.
	 * And no, I have no idea why this ended up being so much more complicated
	 * than getAssignedRights(int). Clearly I'm missing something. ><
	 * @param integer $aGroupID a group ID
	 */
	public function getGrantedRights( $aGroupID=null )
	{
		if( ! $this->isConnected() )
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;
		if( empty( $aGroupID ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'group_id' ) ;

		$theGroupID = intval($aGroupID) ;

		if( $theGroupID == BitsGroups::TITAN_GROUP_ID )
			return $this->getTitanRights() ;

		$dbGroups = $this->getProp( 'AuthGroups' ) ;
		$theGroups = Arrays::array_column_as_key( $dbGroups->getAllGroups(true), 'group_id' ) ;
		if( ! array_key_exists( $aGroupID, $theGroups ) )
			throw RightsException::toss( $this, 'GROUP_NOT_FOUND', $aGroupID ) ;

		$thePerms = array() ;
		$theNextGroupID = $theGroupID ;
		$theProcessed = array() ;

		while( $theNextGroupID >= 0 && ! in_array( $theNextGroupID, $theProcessed ) )
		{ // Build up a map of explicit true/false settings.
			//$this->debugLog( __METHOD__ . ' DEBUG processed: ' . $this->debugStr($theProcessed) ) ;
			$theGroup = $theGroups[$theNextGroupID] ;
			$thePerms = $this->loadAndMergeGrantedRights(
					$thePerms, $theNextGroupID ) ;
			$theProcessed[] = $theNextGroupID ;          // hedge against cycles
			$theParentID = $theGroup['parent_group_id'] ;
			if( $theParentID === null )
				$theNextGroupID = -1 ;
			else if( $theNextGroupID == $theParentID )
				$theNextGroupID = -1 ;
			else if( ! array_key_exists( $theParentID, $theGroups ) )
				$theNextGroupID = -1 ;
			else
				$theNextGroupID = $theParentID ;
		}

		// Now go back and remove everything that's explicitly false.
		foreach( $thePerms as $theSpace => &$theSpacePerms )
		{
			foreach( $theSpacePerms as $thePerm => &$theVal )
			{
				if( ! $theVal )
					unset( $theSpacePerms[$thePerm] ) ;
			}
			if( count($theSpacePerms) == 0  )
				unset( $thePerms[$theSpace] ) ;
		}

		return $thePerms ;
	}

	/**
	 * Consumed by getGrantedRights(int) to build up all permissions granted to
	 * a group, based on its own definitions and those of its ancestors.
	 * @param array $aPerms an existing array of permissions, with which the
	 *  permissions for the specified group will be merged
	 * @param string $aGroupID a group ID to be merged into the result set
	 * @param boolean $bIsFirst indicates whether this is the first group being
	 *  processed in a loop that is ascending a chain of ancestors
	 * @return a copy of the permissions table that was passed in; this function
	 *  should be called such that the return value is assigned back into the
	 *  referenced array if it is being called iteratively on a hierarchy
	 */
	protected function loadAndMergeGrantedRights( array &$aPerms, $aGroupID=null )
	{
		$thePerms = $aPerms ;
		$thePermRows = null ;

		try { $thePermRows = $this->getGroupRightsCursor($aGroupID) ; }
		catch( PDOException $pdox )
		{
			throw DbException( $pdox, __METHOD__
					. ' failed for group ID [' . $aGroupID . '].' ) ;
		}

		if( ! empty( $thePermRows ) )
		{
			$thePermRow = null ;
			while( ( $thePermRow = $thePermRows->fetch() ) != false )
			{
				$theNS = $thePermRow['namespace'] ;
				$thePerm = $thePermRow['permission'] ;
				$bValue = false ;

				if( isset( $thePerms[$theNS][$thePerm] ) && ! $thePerms[$theNS][$thePerm] )
					continue ; // Don't grant a previously denied permission.

				if( $thePermRow['value'] == self::VALUE_Allow )
				{ // Grant a permission.
					if( ! array_key_exists( $theNS, $thePerms ) )
						$thePerms[$theNS] = array() ;

					$thePerms[$theNS][$thePerm] = true ;
				}
				else if( $thePermRow['value'] == self::VALUE_Deny )
				{ // Deny a permission.
					if( ! array_key_exists( $theNS, $thePerms ) )
						$thePerms[$theNS] = array() ;

					$thePerms[$theNS][$thePerm] = false ;
				}
			}
		}

		return $thePerms ;
	}

	/**
	 * Consumed by getGrantedRights() to build up a giant tree of all the site's
	 * permissions, all marked as true.
	 */
	protected function getTitanRights()
	{
		$theTitanPerms = array() ;
		$theSpaces = $this->getRes( 'Permissions/namespace' ) ;
		foreach( $theSpaces as $theSpace => $theNSInfo )
		{
			if( $theSpace == 'monitor_surveys' ) continue ;      // don't bother
			$theTitanPerms[$theSpace] = array() ;
			$thePerms = $this->getRes( 'Permissions/' . $theSpace ) ;
			foreach( $thePerms as $thePerm => $thePermInfo )
				$theTitanPerms[$theSpace][$thePerm] = true ;
		}
		return $theTitanPerms ;
	}

	/**
	 * Remove existing permissions for a particular group.
	 * @param integer $aGroupId - the group ID.
	 */
	public function removeGroupPermissions($aGroupId)
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array('group_id' => $aGroupId));
		$theSql->startWith('DELETE FROM')->add($this->tnPermissions);
		$theSql->startWhereClause()->mustAddParam('group_id')->endWhereClause();
		$theSql->execDML();
	}

	/**
	 * Modify the saved permissions for a particular group.
	 * @param Scene $aScene
	 */
	public function modifyGroupRights($aScene) {
		//remove existing permissions
		$this->removeGroupPermissions($aScene->group_id);
		
		//add new permissions
		$theRightsList = array();
		$theRightGroups = $aScene->getPermissionRes('namespace');
		foreach ($theRightGroups as $ns => $nsInfo) {
			foreach ($aScene->getPermissionRes($ns) as $theRight => $theRightInfo) {
				$varName = $ns.'__'.$theRight;
				$theAssignment = $aScene->$varName;
				//Strings::debugLog($varName.'='.$theAssignment);
				if ($theAssignment==self::FORM_VALUE_Allow) {
					array_push($theRightsList, array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$aScene->group_id, 'value'=>self::VALUE_Allow) );
				} else if ($theAssignment==self::FORM_VALUE_Deny) {
					array_push($theRightsList, array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$aScene->group_id, 'value'=>self::VALUE_Deny) );
				}
			}//end foreach
		}//end foreach
		if (!empty($theRightsList)) {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aScene);
			$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
			$theSql->add('(namespace, permission, group_id, value) VALUES (:ns, :perm, :group_id, :value)');
			$theSql->execMultiDML($theRightsList);
		}
	}

	/**
	 * Returns a raw SELECT * from the permission/group mapping table.
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *  "unregistered" and "titan" groups that are defined by default when the
	 *  system is installed
	 * @throws DbException if a problem occurs during DB query execution
	 * @return array a table of rows from the DB
	 */
	public function getPermissionMap( $bIncludeSystemGroups=false )
	{
		if( ! $this->isConnected() )
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;

		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT group_id, namespace AS ns, ' )
			->add( ' permission, value FROM ' . $this->tnPermissions )
			;
		if( ! $bIncludeSystemGroups )
		{
			$theSql->startWhereClause()
				->setParamOperator( '<>' )
			 	->addFieldAndParam( 'group_id', 'unreg_group_id',
			 			BitsGroups::UNREG_GROUP_ID )
			 	->setParamPrefix( ' AND ' )
				->addFieldAndParam( 'group_id', 'titan_group_id',
						BitsGroups::TITAN_GROUP_ID )
				->endWhereClause()
				;
		}
		$theSql->add( ' ORDER BY group_id, namespace, permission' ) ;
		try { return $theSql->query()->fetchAll() ; }
		catch( PDOException $pdox )
		{ throw new DbException( $pdox, __METHOD__ . ' failed.' ) ; }
	}

	/**
	 * Copies permissions for one group to another group.
	 * Consumed by the createGroup() function in the AuthGroups model.
	 * @param integer $aSourceGroupID the source of the permissions
	 * @param integer $aTargetGroupID the target for the permissions
	 * @return array indication of the result
	 * @throws DbException if a problem occurs during DB query execution
	 * @throws RightsException if either source or target is not specified, or
	 *  not found, or is the "titan" group
	 */
	public function copyPermissions( $aSourceGroupID=null, $aTargetGroupID=null )
	{
		if( ! $this->isConnected() )
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;

		if( ! isset( $aSourceGroupID ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT',
					'source_group_id' ) ;

		if( $aSourceGroupID == BitsGroups::TITAN_GROUP_ID )
			throw RightsException::toss( $this, 'CANNOT_COPY_FROM_TITAN' ) ;

		$dbGroups = $this->getProp( 'AuthGroups' ) ;

		if( ! $dbGroups->groupExists( $aSourceGroupID ) )
			throw RightsException::toss( $this, 'GROUP_NOT_FOUND',
					strval($aSourceGroupID) ) ;

		if( ! isset( $aTargetGroupID ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT',
					'target_group_id' ) ;

		if( $aTargetGroupID == BitsGroups::TITAN_GROUP_ID )
			throw RightsException::toss( $this, 'CANNOT_COPY_TO_TITAN' ) ;

		if( ! $dbGroups->groupExists( $aTargetGroupID ) )
			throw RightsException::toss( $this, 'GROUP_NOT_FOUND',
					strval($aTargetGroupID) ) ;

		$theSql = SqlBuilder::withModel($this)
			->startWith( 'DELETE FROM ' . $this->tnPermissions )
			->startWhereClause()
			->mustAddParam( 'group_id', $aTargetGroupID )
			->endWhereClause()
			;
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{
			throw new DbException( $pdox, __METHOD__
					. ' failed to delete old permissions for target group ['
					. $aTargetGroupID . '].'
					);
		}

		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnPermissions )
			->startWhereClause()
			->mustAddParam( 'group_id', $aSourceGroupID )
			->endWhereClause()
			;
		$theSourcePerms = null ;
		try { $theSourcePerms = $theSql->query()->fetchAll() ; }
		catch( PDOException $pdox )
		{
			throw new DbException( $pdox, __METHOD__
					. ' failed to fetch permissions for source group ['
					. $aSourceGroupID . '].'
					);
		}

		$theCount = 0 ;

		if( ! empty( $theSourcePerms ) )
		{
			$theSql = SqlBuilder::withModel($this)
				->startWith( 'INSERT INTO ' . $this->tnPermissions )
				->add( ' VALUES ' )
				;
			foreach( $theSourcePerms as $thePerm )
			{
				if( $theCount > 0 )
					$theSql->add( ', ' ) ;

				$theSql->add( '(' )
					->add( '\'' . $thePerm['namespace']  . '\', ' )
					->add( '\'' . $thePerm['permission'] . '\', ' )
					->add(        $aTargetGroupID        .   ', ' )
					->add( '\'' . $thePerm['value']      . '\' )' )
					;

				$theCount += 1 ;
			}
			try { $theSql->execDML() ; }
			catch( PDOException $pdox )
			{
				throw new DbException( $pdox, __METHOD__
						. ' failed to insert [' . $theCount
						. '] permissions into target group ['
						. $aTargetGroupID . '].'
						);
			}
		}

		return array(
				'source_group_id' => $aSourceGroupID,
				'target_group_id' => $aTargetGroupID,
				'count' => $theCount
			);
	}

	/**
	 * Sets one of the ternary flags for a given group/namespace/permission
	 * triple. The values are '+' for "always allow", null for "inherit", and
	 * '-' for "always deny".
	 * @param integer $aGroupID the ID of the group whose permissions will be
	 *  modified
	 * @param string $aNamespace the namespace of the permission
	 * @param string $aPerm the name of the permission
	 * @param string $aValue the value: one of '+', '-', or null
	 * @return array a dictionary of namespace, permission, group ID, and value
	 *  for the updated permission, in the order that those columns appear in
	 *  the database
	 * @throws DbException if something goes wrong in the DB
	 * @throws BrokenLeg if one of the parameters is missing
	 * @throws RightsException if the group ID is that of the "titan" group
	 */
	public function setPermission( $aGroupID=null, $aNamespace=null, $aPerm=null, $aValue=null )
	{
		if( ! $this->isConnected() )
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;
		if( empty( $aGroupID ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'group_id' ) ;
		if( empty( $aNamespace ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'namespace' ) ;
		if( empty( $aPerm ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'permission' ) ;

		if( $aGroupID == BitsGroups::TITAN_GROUP_ID )
			throw RightsException::toss( $this, 'CANNOT_MODIFY_TITAN' ) ;
		$dbGroups = $this->getProp( 'AuthGroups' ) ;
		if( ! $dbGroups->groupExists( $aGroupID ) )
			throw RightsException::toss( $this, 'GROUP_NOT_FOUND',
					strval($aGroupID) ) ;

		$theSql = SqlBuilder::withModel($this)
			->startWith( 'DELETE FROM ' . $this->tnPermissions )
			->startWhereClause()
			->mustAddParam( 'group_id', $aGroupID )
			->setParamPrefix( ' AND ' )
			->mustAddParam( 'namespace', $aNamespace )
			->mustAddParam( 'permission', $aPerm )
			->endWhereClause()
			;
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{
			throw new DbException( $pdox, __METHOD__
				. ' failed to delete old permission. ' ) ;
		}

		$theDBValue = null ;

		if( ! empty( $aValue ) && $aValue != self::FORM_VALUE_Disallow )
		{ // Store an explicit value in the database.
			switch($aValue)
			{
				case self::VALUE_Allow:
				case self::FORM_VALUE_Allow:
					$theDBValue = self::VALUE_Allow ;
					break ;
				case self::VALUE_Deny:
				case self::FORM_VALUE_Deny:
					$theDBValue = self::VALUE_Deny ;
					break ;
				default: ;
			}
			if( ! empty( $theDBValue ) )
			{ // We successfully figured out which value to store!
				$theSql = SqlBuilder::withModel($this)
					->startWith( 'INSERT INTO ' . $this->tnPermissions )
					->add( ' VALUES ( ' )
					->add( '\'' . $aNamespace . '\', ' )
					->add( '\'' . $aPerm      . '\', ' )
					->add(        $aGroupID   .   ', ' )
					->add( '\'' . $theDBValue . '\' )' )
					;
				try { $theSql->execDML() ; }
				catch( PDOException $pdox )
				{
					throw new DbException( $pdox, __METHOD__
							. ' failed to insert new permission value.' ) ;
				}
			}
		}

		return array(
				'namespace' => $aNamespace,
				'permission' => $aPerm,
				'group_id' => $aGroupID,
				'value' => $theDBValue
			);
	}

	/**
	 * Convert an old namespace to a new one.
	 */
	public function migrateNamespace($aOldNamespace, $aNewNamespace)
	{
		if (!$this->isConnected())
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'namespace_old' => $aOldNamespace,
				'namespace_new' => $aNewNamespace,
		));
		$theSql->startWith('UPDATE')->add($this->tnPermissions);
		$theSql->add('SET')->mustAddFieldAndParam('namespace', 'namespace_new');
		$theSql->startWhereClause();
		$theSql->mustAddFieldAndParam('namespace', 'namespace_old');
		$theSql->endWhereClause();
		try {
			$theSql->execDML();
		}
		catch(PDOException $pdox) {
			throw new DbException($pdox, __METHOD__ . ' failed to convert permissions from ' .
					$aOldNamespace . ' to ' . $aNewNamespace
			);
		}
		
		//after changing permissions, affect the cache too
		$this->_permCache = array();
		$this->isPermissionAllowed($aNewNamespace, '');
	}

}//end class

}//end namespace
