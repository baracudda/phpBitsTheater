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
use BitsTheater\costumes\Wardrobe\TicketViaInstallPw as BaseCostume;
use BitsTheater\Scene;
use BitsTheater\models\AuthGroups as AuthGroupsDB;
{//namespace begin

/**
 * Class used to help manage logging in via the install.pw file.
 * @since BitsTheater [NEXT]
 */
class TicketViaAuthMigration extends BaseCostume
{
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		//prevent parent class from saving input in session cache.
		$aScene->bExplicitAuthRequired = true;
		$dbAuth = $this->getMyModel();
		if ( !empty($aScene->{$dbAuth::KEY_pwinput}) ) {
			$this->pw_input = $aScene->{$dbAuth::KEY_pwinput};
		}
		return $this;
	}
	
	/**
	 * Check to see if this venue should process the ticket.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return boolean Returns TRUE if this venue should process the ticket.
	 */
	protected function isTicketForThisVenue( Scene $aScene )
	{
		//$this->logStuff(__METHOD__, ' this=', $this); //DEBUG
		if ( $this->getDirector()->canConnectDb() ) {
			/* @var $dbAuthGroups AuthGroupsDB */
			$dbAuthGroups = $this->getProp(AuthGroupsDB::MODEL_NAME);
			if ( $dbAuthGroups->isEmpty() )
			{
				//we may be in a state before migration took place
				/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
				$dbOldAuthGroups = $this->getProp(
						'\BitsTheater\models\PropCloset\BitsGroups'
				);
				//when return to caller, ensure we free up the old model object
				try {
					return ( $dbOldAuthGroups->exists() && !$dbOldAuthGroups->isEmpty() );
				}
				finally {
					$this->returnProp($dbOldAuthGroups);
				}
			}
		}
		return false;
	}

}//end class

}//end namespace
