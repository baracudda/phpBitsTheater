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
use BitsTheater\costumes\Wardrobe\TicketViaRequest as BaseCostume;
use BitsTheater\Scene;
{//namespace begin

/**
 * Class used to help manage logging in via the URL.
 * @since BitsTheater v4.1.0
 */
class TicketViaURL extends BaseCostume
{
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		$dbAuth = $this->getMyModel();
		//PHP has some built in auth vars, check them and use if not empty
		if ( !empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW']) )
		{
			$this->ticket_name = $_SERVER['PHP_AUTH_USER'];
			$this->ticket_secret = $_SERVER['PHP_AUTH_PW'];
			unset($_SERVER['PHP_AUTH_PW']);
			//ensure user:pw via URL is not cached long term
			$this->bUpdateCookie = false;
		}
		return $this;
	}
	
}//end class

}//end namespace
