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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\scenes\Rights as MyScene; /* @var $v MyScene */
use BitsTheater\models\Permissions; /* @var $dbRights Permissions */
use BitsTheater\models\Auth; /* @var $dbAuth Auth */
use BitsTheater\models\AuthGroups as AuthGroups; /* @var $dbAuthGroups AuthGroups */
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use BitsTheater\costumes\APIResponse;
use BitsTheater\BrokenLeg ;
use BitsTheater\costumes\RightsMatrixProcessor;
use BitsTheater\outtakes\RightsException ;
{//namespace begin

class BitsGroups extends BaseActor {
	const DEFAULT_ACTION = 'groups';
	
	public function groups() {
		if (!$this->director->isAllowed('auth','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		$dbAuth = $this->getProp('Auth');
		$v->groups = Arrays::array_column_as_key($dbAuth->getGroupList(),'group_id');
		$this->director->returnProp($dbAuth);

		//TODO need a better UI for dealing with multiple group reg codes
		$dbAuthGroups = $this->getProp('AuthGroups');
		$v->group_reg_codes = $dbAuthGroups->getGroupRegCodes();
	}
	
	/**
	 * Page is rendered for editing the permissions assigned to a particular group.
	 * For the API function that returns the permissions for a group, see
	 * ajajGetPermissionsFor(int).
	 * @param integer $aGroupId the ID of the group to be edited
	 */
	public function group($aGroupId) {
		if (!$this->director->isAllowed('auth','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		if (is_null($aGroupId) || $aGroupId==1)
			return $this->getMyUrl('/rights');
		if ($aGroupId==0) {
			$v->group = null;
		} else {
			$dbAuth = $this->getProp('Auth');
			$v->groups = Arrays::array_column_as_key($dbAuth->getGroupList(),'group_id');
			$v->group = $v->groups[$aGroupId];
		}
		$dbRights = $this->getProp('Permissions');
		$v->right_groups = $v->getPermissionRes('namespace');
		$v->assigned_rights = $dbRights->getAssignedRights($aGroupId);
		//$v->addUserMsg($this->debugStr($v->assigned_rights)); //DEBUG-ONLY
		$v->redirect = $this->getMyUrl('/rights');
		$v->next_action = $this->getMyUrl('/rights/modify');
	}
	
	public function modify() {
		$v =& $this->scene;
		if (!$this->isAllowed('auth','modify'))
			return $this->getHomePage();
		if (is_null($v->group_id) || $v->group_id==1)
			return $this->getMyUrl('/rights');
		//do update of DB
		//print('<pre>');var_dump($v);print('</pre>');
		$dbRights = $this->getProp('Permissions');
		$dbRights->modifyGroupRights($v);
		$this->returnProp($dbRights);
		return $v->redirect;
	}
	
	public function ajaxUpdateGroup() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//do not render anything
		$this->renderThisView = '_blank';
		if (isset($v->group_id) && $this->isAllowed('auth','modify') && $v->group_id>=0 && $v->group_id!=1) {
			$dbAuthGroups = $this->getProp('AuthGroups');
			$dbAuthGroups->modifyGroup($v);
		} else if ( (!isset($v->group_id) || $v->group_id<0) && $this->isAllowed('auth','create')) {
			$dbAuthGroups = $this->getProp('AuthGroups');
			$dbAuthGroups->createGroup($v->group_name, $v->group_parent, $v->group_reg_code);
		}
	}
	
	/**
	 * CSRF protected variant of ajaxUpdateGroup(), which standardizes the response
	 * that is returned to the consumer, and also ensures cross-site script
	 * protection.
	 * @param integer $aGroupID for "update" operations, the ID of the group to
	 *  be updated
	 */
	public function ajajSaveGroup( $aGroupID=null )
	{
		$v =& $this->scene ;
		$this->viewToRender('results_as_json') ;

		try
		{
			$dbGroups = $this->getProp( 'AuthGroups' ) ;

			// The model function expects the group ID to be part of the scene,
			// but our Pulse 3.0 REST API spec states that entity IDs should be
			// URL parameters. This short blurb checks the URL parameters and
			// copies the group ID into the corresponding scene variable, so
			// that we can continue to use the existing model code. The Pulse
			// 2.x UI still passes the group ID as a POST variable, so it will
			// pass through this statement but still be caught by the next one.
			if( isset( $aGroupID ) )
				$v->group_id = $aGroupID ;

			if( isset( $v->group_id ) )
			{ // Update an existing group.
				if( ! $this->isAllowed( 'auth', 'modify' ) )
					throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
				if( $v->group_id == AuthGroups::TITAN_GROUP_ID )
					throw RightsException::toss( $this, 'CANNOT_MODIFY_TITAN' );

				$v->results = APIResponse::resultsWithData(
					$dbGroups->modifyGroup( $v ) ) ;
			}
			else
			{ // Create a new group.
				if( ! $this->isAllowed( 'auth', 'create' ) )
					throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
				if( $v->group_id == AuthGroups::TITAN_GROUP_ID )
					throw RightsException::toss( $this, 'CANNOT_MODIFY_TITAN' );

				$v->results = APIResponse::resultsWithData(
					$dbGroups->createGroup( $v->group_name, $v->parent_group_id,
						$v->reg_code, $v->source_group_id ) ) ;
			}
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * RESTful function to retrieve a "matrix" of user groups and their
	 * permissions. The assembly of this matrix could get relatively expensive,
	 * as there are several DB queries executed to merge the data together. This
	 * method should not be used repeatedly to "refresh" the page.
	 */
	protected function getMatrix()
	{
		$v =& $this->scene ;

		$bIncludeSystemGroups = false ;
		if( isset( $v->include_system_groups ) && $v->include_system_groups == 'true' )
			$bIncludeSystemGroups = true ;

		$this->viewToRender('results_as_json') ;
		if( ! $this->isAllowed( 'auth', 'modify' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
		try
		{
			$theProc = RightsMatrixProcessor::withActor($this) ;
			$v->results = APIResponse::
				resultsWithData( $theProc->process($bIncludeSystemGroups) ) ;
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * Alias for getMatrix() which will be granted to UI-backing functions.
	 * @return \BitsTheater\costumes\APIResponse a matrix of user groups and
	 *  their permissions
	 */
	public function ajajGetMatrix()
	{ return $this->getMatrix() ; }

	/**
	 * Sets the value of a permission for a given group ID.
	 * @param string $aGroupID the ID of the group whose permissions will be
	 *  modified
	 */
	public function ajajSetPermission( $aGroupID=null )
	{
		$v =& $this->scene ;
		$this->viewToRender('results_as_json') ;
		if( ! $this->isAllowed( 'auth', 'modify' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;

		try
		{
			$dbPerms = $this->getProp( 'Permissions' ) ;
			$v->results = APIResponse::resultsWithData(
					$dbPerms->setPermission( $aGroupID, $v->namespace,
							$v->permission, $v->value ) ) ;
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * API function to get the permissions for a specified group.
	 * For the actor function that supports the Joka 2.x UI page,
	 * see group(int).
	 * @param integer $aGroupID the group ID
	 */
	public function ajajGetPermissionsFor( $aGroupID=null )
	{
		$v =& $this->scene ;
		$this->viewToRender('results_as_json') ;
		if( ! $this->isAllowed( 'auth', 'modify' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;

		if( empty( $aGroupID ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'group_id' ) ;

		try
		{
			$dbGroups = $this->getProp( 'AuthGroups' ) ;
			if( ! $dbGroups->groupExists($aGroupID) )
			{
				throw RightsException::toss( $this,
						'GROUP_NOT_FOUND', $aGroupID ) ;
			}

			$dbPerms = $this->getProp( 'Permissions' ) ;
			$v->results = APIResponse::resultsWithData(
				$dbPerms->getGrantedRights($aGroupID) ) ;
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

}//end class

}//end namespace

