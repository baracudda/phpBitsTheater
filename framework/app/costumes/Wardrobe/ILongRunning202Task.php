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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\Scene;
{//begin namespace

/**
 * Sometimes the thing we want to do might take several minutes to complete.
 * A costume that implements these methods will work with the results_as_202 view.
 */
interface ILongRunning202Task
{
	/**
	 * Set up the minimal information necessary to create a long running
	 * task and use the Auth Token mechanism to keep track of it.
	 * @param Scene $v - the Scene object in use.
	 * @return $this Returns $this for chaining.
	 * @throws \Exception if the setTaskInfo() was not called prior to this.
	 */
	function createProcessTask( Scene $v );
	
	/**
	 * Once a long running task is finished regardless of exception or not,
	 * we may need to clean up some things like removing the task token.
	 * @param Scene $v - the Scene object in use.
	 * @return $this Returns $this for chaining.
	 * @throws \Exception if the setTaskInfo() was not called prior to this.
	 */
	function finishProcessTask( Scene $v );
		
	/**
	 * Get the object we should be encoding for a quick response.
	 * @param Scene $v - the Scene object in use.
	 * @return array|object Return the data to be encoded for response.
	 */
	function getResponse202( Scene $v );
	
	/**
	 * The view print method.
	 * @param Scene $v - the actor's scene in use.
	 * @param int $jsonEncodeOptions - the JSON encoding options.
	 */
	function printResponse202( Scene $v, $jsonEncodeOptions );

	/**
	 * Once the quick response has been returned, continue processing.
	 * @param Scene $v - the Scene object in use.
	 */
	function startProcessAfter202( Scene $v );
	
	/**
	 * UI polling requests updated task status information.
	 * @param Scene $v - the Scene object in use.
	 * @return $this Returns $this for chaining.
	 */
	function setResultsWithTaskInfo( Scene $v );

	/**
	 * Task is finally finished, return real data instead of task info.
	 * @param Scene $v - the Scene object in use.
	 * @return $this Returns $this for chaining.
	 */
	function setResultsWithCompletedTaskData( Scene $v );

}//end interface

}//end namespace
