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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\AuthGroup;
use BitsTheater\models\AuthGroups as AuthGroupsDB;
{

/**
 * Supports the operation of creating a user group/permission "matrix" for
 * display purposes.
 */
class RightsMatrixProcessor extends BaseCostume
{
	//regular USE clause generates a "Name already in use" error by PHP which
	//  would require a namechange, just going to use the explicit name here.
	use \BitsTheater\costumes\WornForExportData;

	/** @var array The 2D array for namespace[permissionInfo[]] data. */
	public $rights = array();
	/** @var array The set of auth groups to process keyed by group_id. */
	public $authgroups = array();
	/**
	 * The set of authgroups that are not returned, but
	 * nevertheless influence the outcome of what is returned.
	 * @var AuthGroup[] keyed by group_id [ group_id => AuthGroup ].
	 */
	protected $mInfluentialButOffstageParents = array();
	/**
	 * The parents combined assigned rights.
	 * @var array A 3D [ group_id => [ namespace => [ permission => value ] ] ]
	 */
	protected $mParentRights = array();
	/** @var array A 3D [ group_id => [ namespace => [ permission => value ] ] ] */
	protected $mAssignedRights = array();
	
	/** @return AuthGroupsDB Returns the AuthGroups model in use. */
	protected function getMyModel()
	{ return $this->getProp(AuthGroupsDB::MODEL_NAME); }
	
	/**
	 * Convert the stored values to the form values and expected array structure.
	 * @param array $aRightsList - the list of rights to convert.
	 * @return array Return the converted array.
	 */
	protected function convertToFormValues( $aRightsList )
	{
		$theResults = array();
		foreach ((array)$aRightsList as $theRightInfo) {
			$theNS = $theRightInfo['namespace'];
			$thePermName = $theRightInfo['permission'];
			switch ($theRightInfo['value']) {
				case AuthGroupsDB::VALUE_Allow:
					$theValue = AuthGroupsDB::FORM_VALUE_Allow;
					break;
				case AuthGroupsDB::VALUE_Deny:
					$theValue = AuthGroupsDB::FORM_VALUE_Deny;
					break;
				default:
					$theValue = AuthGroupsDB::FORM_VALUE_Disallow;
			}//switch
			$theResults[$theNS][$thePermName] = $theValue;
		}
		return $theResults;
	}

	/**
	 * Once a group has been fetched from the db, get extra meta data for it.
	 * @param AuthGroup $aAuthGroup - the loaded auth group.
	 * @return $this Returns $this for chaining.
	 */
	protected function onFetchAuthGroup( AuthGroup $aAuthGroup )
	{
		$dbAuthGroups = $this->getMyModel();
		$aAuthGroup->reg_codes = $dbAuthGroups->getGroupRegCodes($aAuthGroup->group_id);
		//if group has a parent, load them and their assigned rights
		if ( !empty($aAuthGroup->parent_group_id) ) {
			$this->mInfluentialButOffstageParents[$aAuthGroup->group_id] =
					$dbAuthGroups->getAuthGroupsAndParents(
							$aAuthGroup->parent_group_id, array(
								'group_id', 'group_num', 'group_name', 'parent_group_id', 'org_id',
							)
					)
			;
			$thePermsMap = $dbAuthGroups->getAssignedPermissionMap(
					array_keys($this->mInfluentialButOffstageParents[$aAuthGroup->group_id])
			);
			if ( !empty($thePermsMap) ) {
				$this->mParentRights[$aAuthGroup->group_id] = $this->convertToFormValues(
						$thePermsMap->fetchAll()
				);
			}
		}
		$this->mAssignedRights[$aAuthGroup->group_id] = $this->convertToFormValues(
				$dbAuthGroups->getAssignedPermissionMap($aAuthGroup->group_id)->fetchAll()
		);
		return $this;
	}
	
	/**
	 * Gets all auth groups for the current org that should be modifiable.
	 * @param boolean $bIncludeSystemGroups - indicates whether to include the
	 *   "unregistered" group.
	 * @return $this Returns $this for chaining.
	 */
	protected function getAuthGroupData( $bIncludeSystemGroups=false )
	{
		$dbAuthGroups = $this->getMyModel();
		$theRowSet = $dbAuthGroups->getAuthGroupsForOrg($bIncludeSystemGroups);
		$theRowSet->setFetchMode(\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE,
					AuthGroup::class, array($dbAuthGroups)
		);
		while ( ($theRow = $theRowSet->fetch()) !== false ) {
			$this->onFetchAuthGroup($theRow);
			$this->authgroups[$theRow->group_id] = $theRow;
		}
		return $this;
	}
	
	/**
	 * Set the given permission status for the specified group.
	 * @param string $aNamespace - a permission namespace.
	 * @param string $aPermName - a permission name.
	 * @param string $aAuthGroupID - the auth group ID.
	 * @param string $aValue - the value to set.
	 * @return $this Returns $this for chaining.
	 */
	protected function setParentValueForGroupPermission( $aNamespace, $aPermName,
			$aAuthGroupID, $aValue )
	{
		if ( empty($aAuthGroupID) ) return; //trivial
		if ( !empty($this->mAssignedRights[$aAuthGroupID][$aNamespace])
				&& !empty($this->mAssignedRights[$aAuthGroupID][$aNamespace][$aPermName])
				)
		{
			//process the rights into what should be editable/shown to an admin
			$theCurrValue = $this->mAssignedRights[$aAuthGroupID][$aNamespace][$aPermName];
			switch ( $aValue ) {
				case AuthGroupsDB::FORM_VALUE_Allow;
					if ( $theCurrValue == AuthGroupsDB::FORM_VALUE_Disallow ) {
						$this->mAssignedRights[$aAuthGroupID][$aNamespace][$aPermName] =
								AuthGroupsDB::FORM_VALUE_ParentAllowed;
					}
					break;
				case AuthGroupsDB::FORM_VALUE_Deny:
					$this->mAssignedRights[$aAuthGroupID][$aNamespace][$aPermName] =
							AuthGroupsDB::FORM_VALUE_DoNotShow;
					break;
				default:
					//change nothing
					break;
			}//switch
		}
		else {
			switch ( $aValue ) {
				case AuthGroupsDB::FORM_VALUE_Allow;
					$this->mAssignedRights[$aAuthGroupID][$aNamespace][$aPermName] =
							AuthGroupsDB::FORM_VALUE_ParentAllowed;
					break;
				case AuthGroupsDB::FORM_VALUE_Deny:
					$this->mAssignedRights[$aAuthGroupID][$aNamespace][$aPermName] =
							AuthGroupsDB::FORM_VALUE_DoNotShow;
					break;
				default:
					$this->mAssignedRights[$aAuthGroupID][$aNamespace][$aPermName] = $aValue;
					break;
			}//switch
		}
		return $this;
	}

	/**
	 * Indicates the given permission status for the specified group.
	 * @param string $aNamespace - a permission namespace.
	 * @param string $aPermName - a permission name.
	 * @param AuthGroup $aAuthGroup - the auth group data.
	 * @return string Return the value of the assigned right or
	 *   FORM_VALUE_Disallow, if not.
	 */
	protected function getValueForGroupPermission( $aNamespace, $aPermName,
			AuthGroup $aAuthGroup )
	{
		$theValue = AuthGroupsDB::FORM_VALUE_Disallow;
		if ( !empty($this->mAssignedRights[$aAuthGroup->group_id])
				&& !empty($this->mAssignedRights[$aAuthGroup->group_id][$aNamespace])
				&& !empty($this->mAssignedRights[$aAuthGroup->group_id][$aNamespace][$aPermName])
				)
		{
			$theValue = $this->mAssignedRights[$aAuthGroup->group_id][$aNamespace][$aPermName];
		}
		return $theValue;
	}

	/**
	 * Constructs the "values" for a given permission.
	 * @param string $aNamespace - a permission namespace.
	 * @param string $aPermName - a permission name.
	 * @return object a dictionary of group ID => permission value
	 */
	protected function getValuesForPermission( $aNamespace, $aPermName )
	{
		$theValues = array();
		foreach ($this->authgroups as $theAuthGroup) {
			$theValue = $this->getValueForGroupPermission(
					$aNamespace, $aPermName, $theAuthGroup
			);
			$theValues[$theAuthGroup->group_id] = $theValue;
		}
		return $theValues;
	}

	/**
	 * Constructs the list of permissions for a given namespace.
	 * @param string $aNamespace - a permission namespace.
	 * @return array Returns {ns, key, label, desc, values[group_id=>value]}[]
	 */
	protected function getPermissionList( $aNamespace )
	{
		$thePermissionList = array() ;
		$thePermissions = $this->getRes('permissions', $aNamespace);
		foreach ($thePermissions as $thePermName => $thePermInfo)
		{
			/* @var $thePermInfo \BitsTheater\costumes\EnumResEntry */
			$thePermissionList[] = array(
					'ns' => $aNamespace,
					'key' => $thePermName,
					'label' => $thePermInfo->label,
					'desc' => $thePermInfo->desc,
					'values' => $this->getValuesForPermission(
							$aNamespace, $thePermName
					),
				);
		}
		return $thePermissionList ;
	}

	/**
	 * Constructs a matrix of user group and permission data.
	 * Exceptions from the DB will be thrown out uncaught; it is expected that
	 * the actor will catch them and convert them to BrokenLeg instances.
	 * This is the only public method of the class.
	 * @param boolean $bIncludeSystemGroups - indicates whether to include the
	 *   "unregistered" group.
	 * @return $this Returns $this for chaining.
	 */
	public function process( $bIncludeSystemGroups=false )
	{
		$this->getAuthGroupData($bIncludeSystemGroups);
		//parents need to influence our current set of rights
		foreach ($this->mParentRights as $theGroupID => $theRightInfo) {
			foreach ($theRightInfo as $theNamespace => $thePermInfo) {
				foreach ($thePermInfo as $thePermName => $theValue) {
					$this->setParentValueForGroupPermission(
							$theNamespace, $thePermName, $theGroupID, $theValue
					);
				}
			}
		}
		//now let us set up a matrix for a potential UI.
		$theNSList = $this->getRes('permissions/namespace');
		foreach ($theNSList as $theNS => $theNSInfo) {
			/* @var $theNSInfo \BitsTheater\costumes\EnumResEntry */
			$this->rights[] = array(
					'namespace' => $theNS,
					'label' => $theNSInfo->label,
					'desc' => $theNSInfo->desc,
					'permission_list' => $this->getPermissionList($theNS),
			);
		}
		//remove any rights that are completely FORM_VALUE_DoNotShow
		foreach ($this->rights as $theNamespaceIdx => $theNSInfo) {
			$thePermListDoNotShowCount = 0;
			foreach ($theNSInfo['permission_list'] as $thePermIdx => $thePermInfo) {
				$theValuesDoNotShowCount = 0;
				foreach ($thePermInfo['values'] as $thePermValue) {
					if ($thePermValue == AuthGroupsDB::FORM_VALUE_DoNotShow) {
						$theValuesDoNotShowCount += 1;
					}
				}//foreach
				if ( $theValuesDoNotShowCount == count($thePermInfo['values']) ) {
					unset($this->rights[$theNamespaceIdx]['permission_list'][$thePermIdx]);
					$thePermListDoNotShowCount += 1;
				}
			}//foreach
			if ( $thePermListDoNotShowCount == count($theNSInfo['permission_list']) ) {
				unset($this->rights[$theNamespaceIdx]);
			}
		}//foreach
		return $this;
	}

	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		//empty method override so it suppresses the trait's version which
		//  does not call parent that we need.
		//any additional processing left for response goes here
		//$o->mAssignedRights = $this->mAssignedRights; //DEBUG
		return $o;
	}
	
	/**
	 * Once processed, pick out a particular group's permissions.
	 * @param string $aGroupID - the group_id to retrieve FORM_VALUE_*.
	 * @return array A 2D [ namespace => [ permission => FORM_value ] ]
	 */
	public function getAssignedRightsForGroup( $aGroupID )
	{ return $this->mAssignedRights[$aGroupID]; }
	
	
} // end class

} // end namespace
