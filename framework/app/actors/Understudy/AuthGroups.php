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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\BrokenLeg ;
use BitsTheater\costumes\APIResponse;
use BitsTheater\costumes\AuthGroup;
use BitsTheater\costumes\RightsMatrixProcessor;
use BitsTheater\models\Auth;
use BitsTheater\models\AuthGroups as MyModel;
use BitsTheater\outtakes\RightsException ;
use BitsTheater\scenes\Rights as MyScene;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\DbException;
use Exception;
{//namespace begin

class AuthGroups extends BaseActor
{
	const DEFAULT_ACTION = 'groups';
	
	/**
	 * {@inheritDoc}
	 * @return MyScene Returns a newly created scene descendant.
	 * @see \BitsTheater\Actor::createMyScene()
	 */
	protected function createMyScene($anAction)
	{ return new MyScene($this, $anAction); }

	/** @return MyScene Returns my scene object. */
	public function getMyScene()
	{ return $this->scene; }

	/**
	 * @return MyModel Returns the database model reference.
	 */
	protected function getMyModel()
	{ return $this->getProp(MyModel::MODEL_NAME); }

	/**
	 * Page listing all roles of which an account can be a member.
	 * @return string|null Returns the redirect URL, if defined.
	 */
	public function groups()
	{
		if ( !$this->isAllowed('auth','modify') )
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		$dbAuthGroups = $this->getMyModel();
		$v->groups = Arrays::array_column_as_key(
				$dbAuthGroups->getRolesToDisplay($this->getMyScene())->fetchAll(), 'group_id'
		);
		$v->group_reg_codes = $dbAuthGroups->getGroupRegCodes();
	}
	
	/**
	 * Page is rendered for editing the permissions assigned to a group/role.
	 * For the API function that returns the permissions for a group, see
	 * ajajGetPermissionsFor().
	 * @param string $aGroupID - the ID of the group to be edited.
	 * @return string|null Returns the redirect URL, if defined.
	 */
	public function group( $aGroupID=null )
	{
		if ( empty($aGroupID) )
			return $this->getMyUrl('/rights');
		if ( !$this->isAllowed('auth','modify') )
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		try {
			$theProc = new RightsMatrixProcessor($this);
			$theProc->process(true);
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
		if ( empty($theProc->authgroups[$aGroupID]) ) {
			return $this->getMyUrl('/rights');
		}
		//view expects an array, so export from the rights processor, then
		//  convert to an array.
		$v->group = (array)$theProc->authgroups[$aGroupID]->exportData();
		$v->right_groups = $v->getPermissionRes('namespace');
		$v->assigned_rights = $theProc->getAssignedRightsForGroup($aGroupID);
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
	 * @return string|null Returns the redirect URL, if defined.
	 */
	public function modify()
	{
		$v = $this->getMyScene();
		$bPostKeyOk = ($this->director['post_key']===$v->post_key);
		//valid time >10sec, <30min
		$theMinTime = $this->director['post_key_ts'];
		$theNowTime = time();
		$theMaxTime = $theMinTime+(60*30);
		$bPostKeyOldEnough = ($theMinTime < $theNowTime) && ($theNowTime < $theMaxTime);
		unset($this->director['post_key']);
		unset($this->director['post_key_ts']);
		if ( !$this->isAllowed('auth','modify') || !$bPostKeyOk || !$bPostKeyOldEnough )
			return $this->getHomePage();
		if ( empty($v->group_id) )
			return $this->getMyUrl('/rights');
		$dbAuthGroups = $this->getMyModel();
		$dbAuthGroups->modifyGroupRights($v);
		return $v->redirect;
	}
	
	/**
	 * Do the actual database insert for creating a new group.
	 * Helper function in case descendants want to do something special.
	 * @param object $aDataObject
	 * @throws DbException if the query goes awry
	 */
	protected function insertGroupData( $aDataObject )
	{
		try
		{
			$dbAuthGroups = $this->getMyModel();
			$theParentGroupID = $aDataObject->parent_group_id;
			//check for "no parent", only allowed if Root.
			if ( empty($theParentGroupID) ) {
				$theOrgID = $dbAuthGroups->getDbConnInfo()->mOrgID;
				//if not Root org
				if ( !empty($theOrgID) && $theOrgID != Auth::ORG_ID_4_ROOT ) {
					//force non-Root org Role parent to be role 1.
					$theGroup1Row = $dbAuthGroups->getGroupByNum(1, 'group_id');
					if ( !empty($theGroup1Row) ) {
						$theParentGroupID = $theGroup1Row['group_id'];
					}
				}
			}
			return $dbAuthGroups->createGroup( $aDataObject->group_name,
					$theParentGroupID, $aDataObject->group_num,
					$aDataObject->reg_code, $aDataObject->source_group_id
			);
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * CSRF protected insert group endpoint which standardizes the response
	 * that is returned to the consumer, and also ensures cross-site script
	 * protection.
	 */
	public function ajajInsertGroup()
	{
		$this->viewToRender('results_as_json');
		$this->checkAllowed('auth', 'create');
		$this->setApiResults($this->insertGroupData($this->scene));
	}

	/**
	 * Do the actual database insert for creating a new group.
	 * Helper function in case descendants want to do something special.
	 * @param object $aDataObject
	 * @throws DbException if the query goes awry
	 */
	protected function modifyGroupData( $aDataObject )
	{
		$dbAuthGroups = $this->getProp( 'AuthGroups' ) ;
		try
		{
			$theData = $dbAuthGroups->modifyGroup($aDataObject);
			//support list of reg codes as well as a single code replacing entire list
			if ( empty($aDataObject->reg_code_list) && isset($aDataObject->reg_code) )
			{
				if ( !empty($aDataObject->reg_code) )
					$aDataObject->reg_code_list = array($aDataObject->reg_code);
				else
					$aDataObject->reg_code_list = array();
			}
			//reg_code_list==null means "no changes"; ==array() means "clear list".
			if ( isset($aDataObject->reg_code_list) )
			{
				//if we have a new list of registration codes, clear out the old list
				$dbAuthGroups->clearRegCodes($aDataObject->group_id);
				//and then add the new list
				$dbAuthGroups->addRegCodes($aDataObject->group_id, $aDataObject->reg_code_list);
			}
			
			return $theData;
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * CSRF protected modify group endpoint which standardizes the response
	 * that is returned to the consumer, and also ensures cross-site script
	 * protection.
	 * @param string $aGroupID for "update" operations, the ID of the group to
	 *  be updated
	 */
	public function ajajModifyGroup( $aGroupID=null )
	{
		$this->viewToRender('results_as_json');
		$this->checkAllowed( 'auth', 'modify' );
		$this->scene->group_id = trim($this->getRequestData( $aGroupID, 'group_id', true));
		$this->setApiResults($this->modifyGroupData($this->scene));
	}

	/**
	 * CSRF protected save/update endpoint which standardizes the response
	 * that is returned to the consumer, and also ensures cross-site script
	 * protection.
	 * @param string $aGroupID for "update" operations, the ID of the group to
	 *  be updated
	 */
	public function ajajSaveGroup( $aGroupID=null )
	{
		$theGroupID = trim($this->getRequestData( $aGroupID, 'group_id', false));
		if ( empty($theGroupID) )
		{ $this->ajajInsertGroup(); }
		else
		{ $this->ajajModifyGroup( $theGroupID ); }
		//see if we got a good response, then get the record from the db for
		//  an even better response with properly formatted data
		$theResults = $this->getApiResults();
		if ( !empty($theResults) ) {
			$theAuthGroup = AuthGroup::withModel($this->getProp('AuthGroups'));
			$theAuthGroup->setDataFrom($theResults->data);
			if ( !empty($theAuthGroup->group_id) ) {
				$theAuthGroup->setDataFrom(
						$theAuthGroup->getModel()->getGroup($theAuthGroup->group_id)
				);
				$theResults->data = $theAuthGroup->exportData();
			}
		}
	}

	/**
	 * Returns an APIResponse a matrix of user groups and their permissions.
	 */
	public function ajajGetMatrix()
	{
		$v = $this->getMyScene();
		$this->viewToRender('results_as_json');
		$this->checkAllowed('auth', 'modify');
		$bIncludeSystemGroups = filter_var($v->include_system_groups, FILTER_VALIDATE_BOOLEAN);
		try {
			$theProc = new RightsMatrixProcessor($this);
			$theProc->process($bIncludeSystemGroups);
			$this->setApiResults($theProc->exportData());
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * Sets the value of a permission for a given group ID.
	 * @param string $aGroupID the ID of the group whose permissions will be
	 *  modified
	 */
	public function ajajSetPermission( $aGroupID=null )
	{
		$v = $this->getMyScene();
		$this->viewToRender('results_as_json');
		$this->checkAllowed('auth', 'modify');
		$theGroupID = $this->getRequestData( $aGroupID, 'group_id', true);
		try {
			$dbAuthGroups = $this->getProp( 'AuthGroups' ) ;
			$theResults = $dbAuthGroups->setPermission( $theGroupID,
					$v->namespace, $v->permission, $v->value
			);
			if ( !empty($theResults) ) {
				$theResults['value'] = $v->value;
			}
			$this->setApiResults($theResults);
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
		$this->checkAllowed( 'auth', 'modify' ) ;
		$theGroupID = $this->getRequestData( $aGroupID, 'group_id', true);
		try
		{
			$dbAuthGroups = $this->getProp( 'AuthGroups' ) ;
			$theGroupRow = $dbAuthGroups->getGroup($theGroupID);
			if ( empty($theGroupRow) )
			{
				throw RightsException::toss( $this,
						RightsException::ACT_GROUP_NOT_FOUND, $theGroupID
				);
			}
			
			$dbPerms = $this->getProp( 'Permissions' ) ;
			$v->results = APIResponse::resultsWithData(
					$dbPerms->getGrantedRights($theGroupID)
			);
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

}//end class

}//end namespace
