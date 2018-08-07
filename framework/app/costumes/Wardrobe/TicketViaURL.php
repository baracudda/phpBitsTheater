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
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\Scene;
{//namespace begin

/**
 * Class used to help manage logging in via the URL.
 * @since BitsTheater [NEXT]
 */
class TicketViaURL extends BaseCostume
{
	
	/**
	 * The URL may contain authorization information.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	public function checkForTicket(Scene $aScene) {
		$dbAuth = $this->getMyModel();
		//PHP has some built in auth vars, check them and use if not empty
		if ( !empty($_SERVER['PHP_AUTH_USER']) )
		{
			$aScene->{$dbAuth::KEY_userinfo} = $_SERVER['PHP_AUTH_USER'];
			//ensure user:pw via URL is not cached long term
			$aScene->{$dbAuth::KEY_cookie} = false;
		}
		if ( !empty($_SERVER['PHP_AUTH_PW']) )
		{
			$aScene->{$dbAuth::KEY_pwinput} = $_SERVER['PHP_AUTH_PW'];
			unset($_SERVER['PHP_AUTH_PW']);
		}
		return parent::checkForTicket($aScene);
	}
	
}//end class

}//end namespace
