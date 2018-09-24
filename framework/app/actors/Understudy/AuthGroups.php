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
use BitsTheater\outtakes\RightsException ;
use com\blackmoonit\exceptions\DbException;
use Exception;
{//namespace begin

class AuthGroups extends BaseActor
{
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
			$dbAuthGroups = $this->getProp( 'AuthGroups' ) ;
			return $dbAuthGroups->createGroup( $aDataObject->group_name,
					$aDataObject->parent_group_id, $aDataObject->group_num,
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
