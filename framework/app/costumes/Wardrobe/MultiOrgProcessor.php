<?php
/*
 * Copyright (C) 2020 Blackmoon Info Tech Services
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
use BitsTheater\costumes\AuthOrg;
use BitsTheater\costumes\WornByActor;
use BitsTheater\models\Auth as AuthModel;
use BitsTheater\models\AuthGroups as AuthGroupsModel;
use BitsTheater\Actor;
use BitsTheater\BrokenLeg;
{//namespace begin

/**
 * Class used to help manage some endpoints in multi-tenancy.
 * @since BitsTheater 4.5.0
 */
class MultiOrgProcessor extends BaseCostume
{ use WornByActor;

	/** @var string[] The permissions we need to operate. */
	public $myPermissions = array(
	);
	/** @var AuthOrg[] The org costumes by org ID key. */
	public $mAuthOrgs = array();
	/** @var string The auth ID for the logged in account. */
	public $myAuthID;
	
	/** @return AuthModel Returns the Auth model. */
	protected function getAuthModel()
	{ return $this->getProp(AuthModel::MODEL_NAME); }
	
	/** @return AuthGroupsModel Returns the AuthGroups model. */
	protected function getAuthGroupsModel()
	{ return $this->getProp(AuthGroupsModel::MODEL_NAME); }
	
	/**
	 * Binds the costume instance to an instance of an actor.
	 * @param Actor $aActor - the actor to bind.
	 * @return $this Returns $this for chaining.
	 */
	public function setActor( Actor $aActor )
	{
		$this->actor = $aActor;
		//optional, let descendant decide what's best to use
		//$aActor->viewToRender('results_as_json');
		$theAcctInfo = $aActor->getDirector()->getMyAccountInfo();
		if ( empty($theAcctInfo) ) {
			throw BrokenLeg::toss($aActor, BrokenLeg::ACT_NOT_AUTHENTICATED);
		}
		$this->myAuthID = $theAcctInfo->auth_id;
		return $this;
	}
	
	/**
	 * Set the permissions that should be checked while processing each org.
	 * @param string[] $aPermissionList
	 * @return $this Returns $this for chaining.
	 */
	public function setPermissionsToCheck( $aPermissionList )
	{
		if ( empty($this->mPermissions) ) {
			$this->mPermissions = $aPermissionList;
		}
		else {
			$this->mPermissions = array_merge($this->mPermissions, $aPermissionList);
		}
		return $this;
	}
	
	/**
	 * Load only those rights we need to check rather than all of them.
	 * @return array Returns the nest array of rights to check.
	 */
	protected function getPermissionsToCheck()
	{
		$theResults = array();
		foreach ( (array)$this->myPermissions as $theRightStr ) {
			list($theNamespace, $theRight) = explode('/', $theRightStr);
			$theResults[$theNamespace][$theRight] = false;
		}
		return $theResults;
	}
	
	/**
	 * Check to see if our limited set of rights are all allowed for current org.
	 * This is done as a speed optimization rather than loading all rights.
	 * @param AuthGroupsModel $dbAuthGroups - the auth groups model.
	 * @param array[string[]] $aRightsList - the rights to check (must ALL pass).
	 * @param string $aOrgID - the org ID to check.
	 * @return boolean Returns TRUE if all rights pass permission check.
	 */
	protected function isAllowedForOrg( AuthGroupsModel $dbAuthGroups,
			$aRightsList, $aOrgID )
	{
		//check to see if we are permitted to access the endpoint here
		if ( !empty($aRightsList) ) {
			$theAuthGroupList = $dbAuthGroups->getGroupIDListForAuthAndOrg(
					$this->myAuthID, $aOrgID
			);
			$theAssignedRightsList = $dbAuthGroups->getAssignedRights(
					$theAuthGroupList, $aRightsList, $aOrgID
			);
			foreach ( $aRightsList as $theNamespace => $thePermList ) {
				foreach ( $thePermList as $thePermmission => $thePermValue ) {
					if ( empty($theAssignedRightsList[$theNamespace]) ||
							empty($theAssignedRightsList[$theNamespace][$thePermmission]) )
					{ return false && !empty($thePermValue); } //2nd term useless here, but shuts up lint warning.
				}//foreach permission
			}//foreach right namespace
		}//if any rights to check per org
		return true;
	}
	
	/**
	 * Task to perform as we iterate over each org mapped to the account.
	 * If needed, you can access the current org ID by using the actor's
	 * Scene's "_current_org_id" property.
	 * @param callable $aCallback - the callback to execute of the form
	 *   <pre>function( ...$args )</pre>
	 * @return mixed Returns the results of the callback.
	 */
	protected function doForCurrentOrg( callable $aCallback, ...$args )
	{
		if ( !empty($args) ) {
			array_unshift($args, $this);
		}
		else {
			$args = array($this);
		}
		return call_user_func_array($aCallback, $args);
	}
	
	/**
	 * Descendants would override this method and return an array() of
	 * useful data to process, returning NULL skips the org.
	 * @return array|NULL Returns NULL to skip the org or an array of
	 *   data to process.
	 */
	protected function getProcessorArgsForCurrentOrg( callable $aGetOrgArgsCallback )
	{
		return ( !empty($aGetOrgArgsCallback) ) ? call_user_func_array($aGetOrgArgsCallback, array($this)) : null;
	}
	
	/**
	 * Construct the array of orgs we should process along with the parameters
	 * used for the processing in each org.
	 * @param callable $aGetOrgArgsCallback - the callback to get args for
	 *   processing on each org.
	 * @return array Returns array of mixed function arguments keyed by org ID.
	 */
	protected function getOrgsToProcess( callable $aGetOrgArgsCallback )
	{
		$theResults = array();
		$theRightsList = $this->getPermissionsToCheck();
		$numOrgs = 0;
		$numOrgsDenied = 0;
		$dbAuth = $this->getAuthModel();
		$dbAuthGroups = $this->getAuthGroupsModel();
		//always start at Root org
		$this->getDirector()->setPropDefaultOrg(null);
		$numOrgs += 1;
		$theOrgID = AuthModel::ORG_ID_4_ROOT;
		if ( $this->isAllowedForOrg($dbAuthGroups, $theRightsList, $theOrgID) ) {
			$theArgs = $this->getProcessorArgsForCurrentOrg($aGetOrgArgsCallback);
			if ( $theArgs !== null ) {
				$theResults[$theOrgID] = $theArgs;
				$this->mAuthOrgs[$theOrgID] = null;
			}
			//skipped orgs are included in our total, but not our denied total.
		}
		else {
			$numOrgsDenied += 1;
		}
		//what orgs does our account belong to?
		if ( $this->isAllowedForOrg($dbAuthGroups, ['auth_orgs' => ['transcend' => false]], AuthModel::ORG_ID_4_ROOT) ) {
			//all orgs allowed, getting cursor to all orgs
			$rs = $dbAuth->getOrgsCursor();
		}
		else {
			$rs = $dbAuth->getOrgsForAuthCursor($this->myAuthID);
		}
		foreach ($rs as $theOrgRow) {
			$numOrgs += 1;
			$theOrgID = $theOrgRow['org_id'];
			if ( $this->isAllowedForOrg($dbAuthGroups, $theRightsList, $theOrgID) ) {
				$theArgs = $this->getProcessorArgsForCurrentOrg($aGetOrgArgsCallback);
				if ( $theArgs !== null ) {
					$theResults[$theOrgID] = $theArgs;
					$this->mAuthOrgs[$theOrgID] = AuthOrg::getInstance($this, $theOrgRow);
				}
				//skipped orgs are included in our total, but not our denied total.
			}
			else {
				$numOrgsDenied += 1;
			}
		}//foreach org
		$this->returnProp($dbAuthGroups);
		$this->returnProp($dbAuth);
		if ( $numOrgsDenied == $numOrgs ) {
			throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN);
		}
		return $theResults;
	}

	/**
	 * The endpoint guts to run on each org as appropriate.
	 * @param callable $aProcessorCallback - the processor to execute on each org.
	 * @param callable $aGetOrgArgsCallback - (optional) the callback to get args for
	 *   processing on each org.
	 * @return array Returns the results of each process call.
	 */
	public function runForEachOrg( callable $aProcessorCallback, callable $aGetOrgArgsCallback=null )
	{
		$theOrgListWithParams = $this->getOrgsToProcess($aGetOrgArgsCallback);
		$dbAuth = $this->getAuthModel();
		$theResults = array();
		foreach ($theOrgListWithParams as $theOrgID => $theOrgArgData) {
			$this->getDirector()->setPropDefaultOrg($theOrgID);
			$this->getActor()->getMyScene()->_current_org_id = $theOrgID;
			try {
				if ( is_array($theOrgArgData) ) {
					$r = $this->doForCurrentOrg($aProcessorCallback, ...$theOrgArgData);
				}
				else {
					$r = $this->doForCurrentOrg($aProcessorCallback, $theOrgArgData);
				}
				if ( !empty($r) ) {
					//merge array results
					if ( is_array($r) ) {
						$theResults = array_merge($theResults, $r);
					}
					//otherwise append results to array
					else {
						$theResults[] = $r;
					}
				}
			}
			catch ( \Exception $x ) {
				$blx = BrokenLeg::tossException($this, $x);
				$this->logErrors(__METHOD__, ' ', $blx->getExtendedErrMsg());
				throw $blx;
			}
			finally {
				$this->getDirector()->getPropsMaster()->closeConnection($theOrgID);
			}
		}
		$this->returnProp($dbAuth);
		return $theResults;
	}
	
}//end class

}//end namespace
