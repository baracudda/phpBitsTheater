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
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\venue\IWillCall;
use BitsTheater\costumes\WornByModel;
use BitsTheater\models\Auth as AuthDB;
use BitsTheater\Scene;
{//namespace begin

/**
 * Class used to help manage authentication via some mechanism.
 * @since BitsTheater [NEXT]
 */
abstract class ATicketForVenue extends BaseCostume
implements IWillCall
{
	use WornByModel;
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['model']);
		return $vars;
	}
	
	/**
	 * Construct and return our new object.
	 * @param AuthDB $aAuthDB - the auth model.
	 * @return $this Returns $this for chaining.
	 */
	static public function withAuthDB( $aAuthDB )
	{
		$theCalledClass = get_called_class();
		return $theCalledClass::withModel($aAuthDB);
	}
	
	/** @return AuthDB Returns the Auth model in use. */
	protected function getMyModel()
	{ return $this->getModel(); }
	
	/**
	 * The method called to actually perform authentication.
	 * @param Scene $aScene - variable container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	abstract public function checkForTicket(Scene $aScene);
	
	/**
	 * If we successfully authorize, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	abstract public function onTicketAccepted(Scene $aScene, AccountInfoCache $aAcctInfo);
	
	/**
	 * If we try to authorize and are rejected, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account which rejected auth.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketRejected(Scene $aScene, AccountInfoCache $aAcctInfo)
	{
		//if login failed, move closer to lockout
		$dbAuth = $this->getMyModel();
		$dbAuth->updateFailureLockout($dbAuth, $aScene);
		return $this;
	}
	
}//end class

}//end namespace
