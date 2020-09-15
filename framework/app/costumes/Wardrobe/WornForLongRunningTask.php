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
use BitsTheater\costumes\APIResponse as APIResponseInUse;
use BitsTheater\costumes\DbConnInfo as DbConnInfoInUse;
use BitsTheater\costumes\PropsMaster as PropsMasterInUse;
use BitsTheater\models\Auth as AuthModel;
use BitsTheater\Scene;
use com\blackmoonit\Strings;
{//begin namespace

trait WornForLongRunningTask
{
	/** @var AuthModel The task info model outside of the transaction. */
	protected $mTaskDbModel;
	/** @var string The ID used for minimal task info. */
	protected $mTaskID;
	/** @var string The token string used for minimal task info. */
	protected $mTaskToken;
	/** @var int The amount of work remaining. */
	protected $mTaskAmtRemaining = 0;
	/** @var string The org_id that initiated the task. */
	protected $mTaskOrgID;


	/** @return AuthModel */
	protected function getTaskModel()
	{
		if ( empty($this->mTaskDbModel) ) {
			//in order to update our task completion number outside of a possible DB
			//  transaction, we need a new connection to the Auth model.
			$this->mTaskDbModel = $this->getDirector()->getModel('Auth')->connectTo(
					new DbConnInfoInUse(PropsMasterInUse::DB_CONN_NAME_FOR_AUTH)
			);
		}
		return $this->mTaskDbModel;
	}
	
	/**
	 * Set internal properties to ensure common task ID/token usage.
	 * @param string $aTaskID - the task ID to use for polling its status.
	 * @param string $aTaskToken2Use - the task token to use.
	 * @param string $aOrgID - (OPTIONAL) org_id of initiating org, default is current.
	 * @return $this Returns $this for chaining.
	 */
	protected function setTaskInfo( $aTaskID, $aTaskToken2Use, $aOrgID=null )
	{
		$this->mTaskID = $aTaskID;
		$this->mTaskToken = AuthModel::TOKEN_PREFIX_LONG_PROCESS_INPROGRESS;
		$this->mTaskToken .= ':' . $aTaskToken2Use;
		$this->mTaskOrgID = ( !empty($aOrgID) ) ? $aOrgID
			: $this->getTaskModel()->getDirector()->getPropsMaster()->getDefaultOrgID();
		return $this;
	}
	
	/**
	 * Set up the minimal information necessary to create a long running
	 * task and use the Auth Token mechanism to keep track of it.
	 * @return $this Returns $this for chaining.
	 * @throws \Exception if the setTaskInfo() was not called prior to this.
	 */
	public function createTaskToken()
	{
		if ( empty($this->mTaskID) || empty($this->mTaskToken) ) throw new \Exception("No task defined.");
		$this->getTaskModel()->insertTaskToken($this->mTaskID, $this->mTaskToken);
		return $this;
	}
	
	/**
	 * Once a long running task is finished regardless of exception or not,
	 * we may need to clean up some things like removing the task token.
	 * @return $this Returns $this for chaining.
	 * @throws \Exception if the setTaskInfo() was not called prior to this.
	 */
	public function removeTaskToken()
	{
		if ( empty($this->mTaskID) || empty($this->mTaskToken) ) throw new \Exception("No task defined.");
		$this->getTaskModel()->removeTaskToken($this->mTaskID, $this->mTaskToken);
		return $this;
	}
	
	/**
	 * Set our remaining counter.
	 * @param number $aAmount - set the value to this amount.
	 */
	protected function setTaskRemaining( $aAmount )
	{
		$this->mTaskAmtRemaining = $aAmount;
		$this->getTaskModel()->updateTask($this->mTaskID, $this->mTaskToken, $this->mTaskAmtRemaining);
	}
	
	/**
	 * Update our remaining counter.
	 * @param number $aDecAmount - remove this many, defaults to 1.
	 */
	protected function updateTaskRemaining( $aDecAmount=1 )
	{
		$this->mTaskAmtRemaining -= $aDecAmount;
		$this->getTaskModel()->updateTask($this->mTaskID, $this->mTaskToken, $this->mTaskAmtRemaining);
	}
	
	/**
	 * Is endpoint being called to check on a started task or start one?
	 * @param string $aValToCheck - the value to test for emptiness (typically $v->task_token).
	 * @return boolean Returns TRUE if caller is checking a running task.
	 */
	public function isCheckTaskRemaining( $aValToCheck )
	{
		if ( empty($this->mTaskID) || empty($this->mTaskToken) ) throw new \Exception("No task defined.");
		if ( !empty($aValToCheck) ) {
			return true;
		}
		else {
			//maybe someone else started the task or browser refreshed.
			$dbTask = $this->getTaskModel();
			$theTaskRow = $dbTask->getAuthTokenRow($this->mTaskID, $this->mTaskToken);
			if ( !empty($theTaskRow) ) {
				$this->mTaskAmtRemaining = $theTaskRow['account_id']+0;
			}
			return !empty($theTaskRow);
		}
	}
	
	/**
	 * Get the object we should be encoding for a quick response.
	 * @return array|object Return the data to be encoded for response.
	 */
	protected function getResponse202ForTask()
	{
		if ( empty($this->mTaskAmtRemaining) ) {
			$dbTask = $this->getTaskModel();
			$this->mTaskAmtRemaining = $dbTask->getTaskAmountRemaining($this->mTaskID, $this->mTaskToken);
		}
		return array(
				'task_org_id' => $this->mTaskOrgID,
				'task_token' => $this->mTaskToken,
				'task_remaining' => !empty($this->mTaskAmtRemaining) ? intval($this->mTaskAmtRemaining) : 0,
				'delay_in_seconds' => !empty($this->mTaskAmtRemaining) ? intval($this->mTaskAmtRemaining) : 0,
		);
	}
	
	/**
	 * Default view print method.
	 * @param Scene $v - the actor's scene in use.
	 * @param int $jsonEncodeOptions - the JSON encoding options.
	 */
	public function printResponse202( Scene $v, $jsonEncodeOptions )
	{
        //standard APIResponse with data returned via getResponse202() method
        $theResponse = APIResponseInUse::resultsWithData($this->getResponse202($v));
		header(Strings::createHttpHeader(APIResponseInUse::HEADER_JOKA_ORG_ID, $this->mTaskOrgID));
		header(Strings::createHttpHeader('Content-Type', 'application/json; charset=utf-8'));
        print(json_encode($theResponse, $jsonEncodeOptions));
	}
	
	/**
	 * The long running task may still be ongoing. If so, return request data,
	 * otherwise get the real response data.
	 * @param Scene $v - the Scene object to set $v->results.
	 * @return $this Returns $this for chaining.
	 */
	public function setResultsWithTaskInfo( Scene $v )
	{
		if ( !empty($v->getActor()) ) {
			$v->getActor()->setView('results_as_json');
		}
		$theResponse = $this->getResponse202($v);
		if ( $this->mTaskAmtRemaining !== false ) {
			//task is still ongoing, just return back the request data
			$v->results = APIResponseInUse::resultsWithData($theResponse, 202);
		}
		else {
			$this->setResultsWithCompletedTaskData($v);
		}
		return $this;
	}
	
}//end trait

}//end namespace
