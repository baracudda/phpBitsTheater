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
use BitsTheater\costumes\Wardrobe\ATicketForVenue as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\Scene;
{//namespace begin

/**
 * Class used to help manage logging in via the PHP Session.
 * @since BitsTheater v4.1.0
 */
class TicketViaSession extends BaseCostume
{
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		return $this;
	}
	
	/**
	 * Check to see if this venue should process the ticket.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return boolean Returns TRUE if this venue should process the ticket.
	 */
	protected function isTicketForThisVenue( Scene $aScene )
	{
		$dbAuth = $this->getMyModel();
		return !$aScene->bExplicitAuthRequired &&
				$dbAuth->isAccountInSessionCache() ;
	}

	/**
	 * The method used to perform authentication for this particular venue.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function processTicket( Scene $aScene )
	{
		$dbAuth = $this->getMyModel();
		return $dbAuth->loadAccountFromSessionCache();
	}
	
	/**
	 * Log the current user out and wipe the slate clean.
	 * Each venue may cache specific items which this should clear out.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function ripTicket( AccountInfoCache $aAcctInfo )
	{
		$dbAuth = $this->getMyModel();
		$dbAuth->saveAccountToSessionCache(null);
		return parent::ripTicket($aAcctInfo);
	}
	
}//end class

}//end namespace
