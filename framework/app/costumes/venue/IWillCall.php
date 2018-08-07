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

namespace BitsTheater\costumes\venue;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\Scene;
{//begin namespace

interface IWillCall {
	/**
	 * The method called to actually perform authentication.
	 * @param Scene $aScene - variable container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 */
	function checkForTicket(Scene $aScene);
	
	/**
	 * If we successfully authorize, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	function onTicketAccepted(Scene $aScene, AccountInfoCache $aAcctInfo);

	/**
	 * If we try to authorize and are rejected, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account which rejected auth.
	 * @return $this Returns $this for chaining.
	 */
	function onTicketRejected(Scene $aScene, AccountInfoCache $aAcctInfo);
	
}//end interface

}//end namespace
