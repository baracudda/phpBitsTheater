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
use BitsTheater\models\AuthGroups as AuthGroupsDB;
{//namespace begin

/**
 * Class used to help manage logging in via the install.pw file.
 * @since BitsTheater v4.1.0
 */
class TicketViaInstallPw extends BaseCostume
{
	/** @var string The install passphrase filename (no path). */
	const INSTALL_PW_FILENAME = 'install.pw';
	/** @var string The install passphrase filepath w/o filename. */
	const INSTALL_PW_FILEPATH = BITS_ROOT . DIRECTORY_SEPARATOR;
	/** @var string The passphrase input to check against. */
	protected $pw_input;
	
	/**
	 * Take the defined pieces and load up the install passphrase.
	 * @return string Returns the install passphrase.
	 */
	static protected function getInstallPw()
	{
		$thePwFilePath = static::INSTALL_PW_FILEPATH . static::INSTALL_PW_FILENAME;
		if ( file_exists($thePwFilePath) ) {
			return trim(file_get_contents($thePwFilePath));
		}
		//The default pw is the folder path since outsiders should not know it.
		return BITS_ROOT;
	}
	
	/**
	 * During website installation, the database is not yet accessible, so we
	 * need a way to check for the install passphrase that does not involve
	 * creation of an Auth Model.  This static method is that means.
	 * @param string $aInput - the passphrase input to check.
	 * @return boolean Returns TRUE if the input matches the definition.
	 */
	static public function checkInstallPwVsInput( $aInput )
	{
		$theInstallPw = static::getInstallPw();
		$theInputPw = trim($aInput);
		return ( $theInstallPw == $theInputPw );
	}
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		if ( !empty($aScene->installpw) ) {
			$this->pw_input = $aScene->installpw;
		}
		else if ( !$aScene->bExplicitAuthRequired &&
				!empty($this->getDirector()[static::INSTALL_PW_FILENAME]) ) {
			$this->pw_input = $this->getDirector()[static::INSTALL_PW_FILENAME];
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
		//not typically used for auth, descendents may override if needed.
		return false;
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
		if ( static::checkInstallPwVsInput($this->pw_input) )
		{
			$dbAuth = $this->getMyModel();
			$theAcctName = !empty($aScene->{$dbAuth::KEY_userinfo})
				? $aScene->{$dbAuth::KEY_userinfo} : 'SITE_ADMIN' ;
			return $dbAuth->createAccountInfoObj(array(
					//set fake account info so we can install/admin-stuff!
					'auth_id' => 'ZOMG-n33dz-2-install/admin!',
					'account_id' => -1,
					'account_name' => $theAcctName,
					'groups' => array(),
					'is_active' => true,
			));
		}
	}
	
	/**
	 * If we successfully authorize, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketAccepted( Scene $aScene, AccountInfoCache $aAcctInfo )
	{
		parent::onTicketAccepted($aScene, $aAcctInfo);
		if ( !$aScene->bExplicitAuthRequired ) {
			//save in short term session storage
			$this->getDirector()[static::INSTALL_PW_FILENAME] = $this->pw_input;
		}
		/* @var $dbAuthGroups AuthGroupsDB */
		$dbAuthGroups = $this->getProp(AuthGroupsDB::MODEL_NAME);
		$aAcctInfo->rights = $dbAuthGroups->getAllAccessPass();
		$this->getDirector()->setMyAccountInfo($aAcctInfo);
		return $this;
	}
	
	/**
	 * Log the current user out and wipe the slate clean.
	 * Each venue may cache specific items which this should clear out.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function ripTicket( AccountInfoCache $aAcctInfo )
	{
		//clear short term session storage
		unset($this->getDirector()[static::INSTALL_PW_FILENAME]);
		return parent::ripTicket($aAcctInfo);
	}
	
}//end class

}//end namespace
