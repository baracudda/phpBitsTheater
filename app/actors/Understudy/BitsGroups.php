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
use BitsTheater\models\AuthGroups; /* @var $dbAuthGroups AuthGroups */
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use BitsTheater\costumes\APIResponse;
use BitsTheater\BrokenLeg ;
use BitsTheater\costumes\RightsMatrixProcessor;
use BitsTheater\outtakes\RightsException ;
use Exception;
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
		
		if (is_null($aGroupId) || $aGroupId==AuthGroups::TITAN_GROUP_ID)
			return $this->getMyUrl('/rights');
		if ($aGroupId==AuthGroups::UNREG_GROUP_ID) {
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
		//CSRF protection via form data: we need to use a secret hidden form value
		$this->director['post_key'] = Strings::createUUID().Strings::createUUID();
		$this->director['post_key_ts'] = time()+1; //you can only update your account 1 second after the page loads
		$v->post_key = $this->director['post_key'];
	}
	
	/**
	 * Since this method processes form submission, we need to use a secret
	 * hidden form value. The group() endpoint creates a token to use,
	 * typically tied to the user session, and in here, we validate that we
	 * receive the same value back in the form post. The attacker can't simply
	 * scrape our remote form as the target user through JavaScript, thanks to
	 * "same-domain request limits" in the XmlHttpRequest function.
	 */
	public function modify() {
		$v =& $this->scene;
		$bPostKeyOk = ($this->director['post_key']===$v->post_key);
		//valid time >10sec, <30min
		$theMinTime = $this->director['post_key_ts'];
		$theNowTime = time();
		$theMaxTime = $theMinTime+(60*30);
		$bPostKeyOldEnough = ($theMinTime < $theNowTime) && ($theNowTime < $theMaxTime);
		unset($this->director['post_key']); unset($this->director['post_key_ts']);
		if (!$this->isAllowed('auth','modify') || !$bPostKeyOk || !$bPostKeyOldEnough)
			return $this->getHomePage();
		if (is_null($v->group_id) || $v->group_id==AuthGroups::TITAN_GROUP_ID)
			return $this->getMyUrl('/rights');
		$dbRights = $this->getProp('Permissions');
		$dbRights->modifyGroupRights($v);
		$this->returnProp($dbRights);
		return $v->redirect;
	}
	
	/**
	 * CSRF protected save/update endpoint which standardizes the response
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

			//URL segment overrides POST/Form variable
			if( isset( $aGroupID ) )
				$v->group_id = $aGroupID ;

			if( isset( $v->group_id ) && $v->group_id >= AuthGroups::UNREG_GROUP_ID )
			{ // Update an existing group.
				if( ! $this->isAllowed( 'auth', 'modify' ) )
					throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
				if( $v->group_id == AuthGroups::TITAN_GROUP_ID )
					throw RightsException::toss( $this, 'CANNOT_MODIFY_TITAN' );

				$v->results = APIResponse::resultsWithData(
						$dbGroups->modifyGroup( $v )
				) ;
			}
			else
			{ // Create a new group.
				if( ! $this->isAllowed( 'auth', 'create' ) )
					throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
				if( $v->group_id == AuthGroups::TITAN_GROUP_ID )
					throw RightsException::toss( $this, 'CANNOT_MODIFY_TITAN' );

				$v->results = APIResponse::resultsWithData(
					$dbGroups->createGroup( $v->group_name, $v->group_parent,
						$v->group_reg_code, $v->source_group_id ) ) ;
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
	 * @param integer $aGroupID the group ID
	 * @see BitsGroups::group(int)
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

