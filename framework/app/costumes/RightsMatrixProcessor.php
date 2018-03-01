<?php

namespace BitsTheater\costumes ;
use BitsTheater\Model ;
use BitsTheater\costumes\ABitsCostume as BaseCostume ;
use BitsTheater\models\AuthGroups ; /* @var $dbGroups AuthGroups */
use BitsTheater\models\Permissions ; /* @var $dbPerms Permissions */
{

/**
 * Supports the operation of creating a user group/permission "matrix" for
 * display purposes. Consumed by the BitsGroups actor. The code was moved here
 * because it turned out to be enormous, and we don't want to pollute the actor
 * with this much processing logic. It was implemented as a costume, and not an
 * extension of the permissions model, because it needs some persistent instance
 * variables to make all the logic work out.
 */
class RightsMatrixProcessor
extends BaseCostume
{
	/** A dictionary that is built up by the class's algorithm. */
	protected $myMatrix ;
	/** A cache of all user group information, fetched from the DB . */
	protected $myGroups ;
	/** A cache of all explicit group/permission maps, fetched from the DB. */
	protected $myGroupPerms ;

	/**
	 * Standard way to resolve a numeric group ID into something that will
	 * always be processed as a string (instead of an integer array index).
	 * Used to make and match the array keys for $this->myGroupPerms.
	 * @param integer $aGroupID an integer group ID from the DB
	 * @return string a stringified version of that ID
	 */
	protected static function getStringifiedGroupID( $aGroupID )
	{ return 'group-' . strval( $aGroupID ) ; }

	/**
	 * Constructs a matrix of user group and permission data.
	 * Exceptions from the DB will be thrown out uncaught; it is expected that
	 * the actor will catch them and convert them to BrokenLeg instances.
	 * This is the only public method of the class.
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *  "unregistered" and "titan" groups that are defined by default when the
	 *  system is installed
	 * @return a dictionary of group and permission data
	 */
	public function process( $bIncludeSystemGroups=false )
	{
		$this->myMatrix = array() ;

		$this->cacheGroupData( $bIncludeSystemGroups ) ;
		$this->myMatrix['groups'] = $this->myGroups ;

		$this->myMatrix['namespaces'] = array() ;

		$theNamespaces = $this->getRes( 'permissions/namespace' ) ;

		foreach( $theNamespaces as $theNSName => $theNSInfo )
		{
			$this->myMatrix['namespaces'][] = array(
					'namespace' => $theNSName,
					'label' => $theNSInfo->label,
					'desc' => $theNSInfo->desc,
					'permission_list' =>
						$this->getPermissionListForNamespace( $theNSName )
				) ;
		}

		// Make sure what we return always looks like an object. (#2848)
		return ((object)($this->myMatrix)) ;
	}

	/**
	 * Caches data about user groups in this object, so that it can be reused by
	 * later algorithms. This is the only method in the costume that hits the
	 * database; it executes calls to get information about all permission
	 * groups, and then all mappings of groups to permissions. The rest of the
	 * class's algorithm is performed entirely within the class itself.
	 * Consumed by process().
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *  "unregistered" and "titan" groups that are defined by default when the
	 *  system is installed
	 * @return \BitsTheater\costumes\RightsMatrixProcessor this object
	 */
	protected function cacheGroupData( $bIncludeSystemGroups=false )
	{
		$dbGroups = $this->getProp( 'AuthGroups' ) ;
		$dbPerms = $this->getProp( 'Permissions' ) ;

		$this->myGroups = $dbGroups->getAllGroups( $bIncludeSystemGroups ) ;

		$this->myGroupPerms = array() ;
		foreach( $this->myGroups as $theGroup )
		{
			$theGroupID = static::getStringifiedGroupID($theGroup['group_id']) ;
			$this->myGroupPerms[$theGroupID] = array() ;
		}
		$thePermMap = $dbPerms->getPermissionMap( $bIncludeSystemGroups ) ;
		foreach( $thePermMap as $thePerm )
		{
			$thePermObj = (object)($thePerm) ;
			$theGroupID =
				static::getStringifiedGroupID( $thePermObj->group_id ) ;

			//if there are orphaned records in Permission Map table, skip them
			if ( !array_key_exists($theGroupID, $this->myGroupPerms) )
			{
				//instead of merely skipping the orphan, lets go ahead and delete it
				$dbPerms->removeGroupPermissions($thePermObj->group_id);
				continue;
			}
			
			$bEnabled = ( $thePermObj->value == Permissions::VALUE_Allow ? true : false ) ;

			if( ! array_key_exists( $thePermObj->ns, $this->myGroupPerms[$theGroupID] ) )
				$this->myGroupPerms[$theGroupID][$thePermObj->ns] = array() ;

			$this->myGroupPerms[$theGroupID][$thePermObj->ns][$thePermObj->permission] = $bEnabled ;
		}

		return $this ;
	}

	/**
	 * Constructs the list of permissions for a given namespace.
	 * Consumed by process().
	 * @param string $aNSName a permission namespace name
	 * @return array an array of permission information objects
	 */
	protected function getPermissionListForNamespace( $aNSName )
	{
		$thePermissionList = array() ;

		$thePermissions = $this->getRes( 'Permissions/' . $aNSName ) ;

		foreach( $thePermissions as $thePermName => $thePermInfo )
		{
			$thePermissionList[] = array(
					'ns' => $aNSName,
					'key' => $thePermName,
					'label' => $thePermInfo->label,
					'desc' => $thePermInfo->desc,
					'values' =>
						$this->getValuesForPermission( $aNSName, $thePermName )
				);
		}

		return $thePermissionList ;
	}

	/**
	 * Constructs the "values" for a given permission.
	 * The returned object contains properties in which the property key is the
	 * group's numeric ID and the property value is either true (allowed) or
	 * false (denied).
	 * Consumed by getPermissionListForNamespace().
	 * @param string $aNSName a permission namespace name
	 * @param string $aPermName a permission name
	 * @return object a dictionary of group ID => permission value
	 */
	protected function getValuesForPermission( $aNSName, $aPermName )
	{
		$theValues = new \stdClass() ;

		foreach( $this->myGroups as $theGroup )
		{
			$bSetting = $this->getValueForGroupPermission(
					$aNSName, $aPermName, (object)($theGroup) ) ;

			$theValueKey = strval($theGroup['group_id']) ;
			$theValues->$theValueKey = $bSetting ;
		}

		return $theValues ;
	}

	/**
	 * Indicates whether the given permission is allowed or denied for the
	 * specified group.
	 * Consumed by getValuesForPermission() but also calls itself recursively if
	 * the permissions DB has some hierarchical structure to it.
	 * @param string $aNSName a permission namespace name
	 * @param string $aPermName a permission name
	 * @param object $aGroup a group row data
	 * @param array $aParentList - (optional) a parent group ID list to prevent
	 *   infinite loops.
	 * @return boolean indicates whether the permission is allowed (true) or
	 *  denied (false)
	 */
	protected function getValueForGroupPermission( $aNSName, $aPermName, $aGroup,
			$aParentList=array() )
	{
		if( $aGroup === NULL ) return false ;

		$bSetting = false ;
		$theGroupID = self::getStringifiedGroupID($aGroup->group_id) ;

		if( $aGroup->group_id == AuthGroups::UNREG_GROUP_ID )
			$bSetting = false ;
		else if( $aGroup->group_id == AuthGroups::TITAN_GROUP_ID )
			$bSetting = true ;
		else if( $this->hasExplicitSettingFor($aNSName,$aPermName,$theGroupID) )
			$bSetting = $this->myGroupPerms[$theGroupID][$aNSName][$aPermName] ;
		else if( isset( $aGroup->parent_group_id ) && ! empty( $aGroup->parent_group_id ) &&
				! array_key_exists($aGroup->parent_group_id, $aParentList) )
		{
			$aParentList[$aGroup->parent_group_id] = true;
			$bSetting = $this->getValueForGroupPermission( $aNSName, $aPermName,
					$this->getGroupByID( $aGroup->parent_group_id ),
					$aParentList
			) ;
		}

		return $bSetting ;
	}

	/**
	 * Evaluates whether the group permissions cache holds an explicit setting
	 * for the given group and permission.
	 * This is just a single conditional expression, but it's factored out here
	 * because it's ugly enormous.
	 * Consumed by getValueForGroupPermission().
	 * @param string $aNSName a permission namespace name
	 * @param string $aPermName a permission name
	 * @param unknown $aGroupIDString a stringified group ID
	 * @return boolean whether there is an explicit setting for this group
	 */
	protected function hasExplicitSettingFor( $aNSName, $aPermName, $aGroupIDString )
	{
		return(
				array_key_exists( $aGroupIDString, $this->myGroupPerms )
			&&  array_key_exists( $aNSName,
					$this->myGroupPerms[$aGroupIDString] )
			&&  array_key_exists( $aPermName,
					$this->myGroupPerms[$aGroupIDString][$aNSName] )
			) ;
	}

	/**
	 * Linearly searches the group cache for the group with a matching ID.
	 * Consumed by getValueForGroupPermission().
	 * @param integer $aGroupID a group ID
	 * @return object|NULL an group info object, or NULL if not found
	 */
	protected function getGroupByID( $aGroupID )
	{
		foreach( $this->myGroups as $theGroup )
		{
			if( $theGroup['group_id'] == $aGroupID )
				return (object)($theGroup) ;
		}

		return NULL ;
	}
} // end class RightsMatrixProcessor

} // end namespace BitsTheater\costumes