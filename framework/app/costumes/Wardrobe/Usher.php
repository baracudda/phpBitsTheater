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
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\WornByModel;
use BitsTheater\models\Auth as AuthDB;
use BitsTheater\Scene;
{//namespace begin

/**
 * Authorization class used to wrangle permissions after authentication.
 * @since BitsTheater v4.1.0
 */
class Usher extends BaseCostume
{ use WornByModel;

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
	 * @return $this Returns $this for chaining.
	 */
	static public function withContext( IDirected $aContext )
	{
		$theCalledClass = get_called_class();
		return (new $theCalledClass($aContext))
				->setModel($aContext->getProp(AuthDB::MODEL_NAME))
				;
	}
	
	/** @return AuthDB Returns the Auth model in use. */
	protected function getMyModel()
	{ return $this->getModel(); }
	
	/**
	 * Ensure the parameter is an actual AccountInfoCache class.
	 * @param array|object $aAcctInfo - the data to ensure is the class.
	 * @return AccountInfoCache Return the account info to use.
	 */
	public function createAccountInfoObj( $aAcctInfo )
	{
		if ( $aAcctInfo instanceof AccountInfoCache ) {
			return $aAcctInfo;
		}
		else if ( !empty($aAcctInfo) ) {
			return $this->getMyModel()->createAccountInfoObj($aAcctInfo);
		}
		else {
			return null;
		}
	}
	
	/**
	 * Returns the chat forum this site is mated with, if any.
	 * @return string URL of the forum, if any.
	 */
	public function getForumUrl() {
		if ($this->getMyModel()->isCallable('getForumUrl')) {
			return $this->getMyModel()->getForumUrl();
		}
	}
	
	/**
	 * Authentication check.
	 * @param Scene $aScene - the Scene object to pass in for authentication.
	 * @return boolean Returns TRUE if authorized.
	 */
	public function checkTicket( $aScene )
	{ return $this->getMyModel()->checkTicket($aScene); }
	
	/**
	 * Logout the current user.
	 */
	public function ripTicket()
	{ $this->getMyModel()->ripTicket(); }
	
	/**
	 * Check to see if the given account information is considered a guest.
	 * @param AccountInfoCache $aAcctInfo - (optional) the account info.
	 * @return boolean Returns FALSE if the account info matches an active
	 *   member account.
	 */
	public function isGuestAccount( AccountInfoCache $aAcctInfo=null )
	{ return $this->getMyModel()->isGuestAccount($aAcctInfo); }
	
	/**
	 * Determine if the current logged in user has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param AccountInfoCache $aAcctInfo - (optional) check this account
	 *   instead of current user.
	 * @return boolean Returns TRUE if allowed.
	 */
	public function isPermissionAllowed($aNamespace, $aPermission,
			AccountInfoCache $aAcctInfo=null)
	{
		$dbAuth = $this->getMyModel();
		if ( empty($aAcctInfo) ) {
			$theAcctInfo = $this->getDirector()->getMyAccountInfo();
			$bUpdateSessionCache = !isset($theAcctInfo->rights);
		}
		else {
			$theAcctInfo = $this->createAccountInfoObj($aAcctInfo);
			$bUpdateSessionCache = false;
		}
		$theResult = $dbAuth->isPermissionAllowed($aNamespace, $aPermission,
				$theAcctInfo
		);
		if( $bUpdateSessionCache
				&& isset($theAcctInfo->rights)
				&& $dbAuth->isAccountInSessionCache() )
		{
			$dbAuth->saveAccountToSessionCache($theAcctInfo);
		}
		return $theResult;
	}
	
}//end class

}//end namespace
