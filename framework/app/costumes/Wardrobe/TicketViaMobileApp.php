<?php
namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\venue\TicketViaAuthHeaderBroadway as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\Scene;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Class used to help manage logging in via POST variable mimicing the
 * HTTP "Broadway" Authorization format and IMEI information.
 * @since BitsTheater v4.1.0
 */
class TicketViaMobileApp extends BaseCostume
{
	/** @var string The token filter used to map IMEI to an account. */
	protected $device_token_filter = null;
	/** @var string The IMEI token shelf life, after which they are removed. */
	protected $device_token_shelf_life = '3 MONTH'; //set to empty|null for infinite life
	
	/**
	 * Get the auth header data we need to process. Sometimes it is not in
	 * the actual HTTP headers.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return string Returns the header data to parse.
	 */
	protected function getAuthHeaderData( Scene $aScene )
	{
		$theAuthHdrData = Strings::getHttpHeaderValue('Authorization');
		if ( empty($theAuthHdrData) && !empty($aScene->auth_header_data) ) {
			$theAuthHdrData = $aScene->auth_header_data;
		}
		return $theAuthHdrData;
	}
	
	/**
	 * Once we have the auth header data, parse it.
	 * @param string $aAuthHeaderData - the auth header data to parse.
	 */
	protected function parseAuthHeader( $aAuthHeaderData )
	{
		parent::parseAuthHeader($aAuthHeaderData);
		if ( !empty($this->device_id) ) {
			$dbAuth = $this->getMyModel();
			$this->device_token_filter =
					$dbAuth::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':' .
					$this->device_id . ':%' ;
		}
	}
	
	/**
	 * The Scene may contain token information which maps to an account.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function checkForAuthMapping(Scene $aScene)
	{
		$theResult = null;
		$dbAuth = $this->getMyModel();
		if ( !empty($this->device_token_shelf_life) ) {
			//remove any stale device tokens
			$dbAuth->removeStaleTokens($this->device_token_filter,
					$this->device_token_shelf_life
			);
		}
		//check to see if we have mapped a token to an account
		$theTokenRows = $dbAuth->getAuthTokens(null, null,
				$this->device_token_filter, true
		);
		//$this->logStuff(__METHOD__, ' rows=', $theTokenRows);//DEBUG
		if ( !empty($theTokenRows) ) {
			//just use the first one found
			$this->auth_id = $theTokenRows[0]['auth_id'];
			$theAuthRow = $dbAuth->getAuthByAuthId($this->auth_id);
			if ( !empty($theAuthRow) ) {
				$theResult = $dbAuth->createAccountInfoObj($theAuthRow);
			}
		}
		return $theResult;
	}
	
	/**
	 * The Scene may contain authorization information.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function checkForAuthRequest(Scene $aScene)
	{
		$theResult = null;
		$theUserInput = $this->getUserInput($aScene);
		if ( empty($theUserInput) && !empty($aScene->user) )
		{ $theUserInput = $aScene->user; }
		if ( empty($theUserInput) && !empty($aScene->email) )
		{ $theUserInput = $aScene->email; }
		$theAuthInput = $this->getAuthInput($aScene);
		if ( !empty($theUserInput) && !empty($theAuthInput) ) {
			$dbAuth = $this->getMyModel();
			$theAuthRow = $dbAuth->getAuthByName($theUserInput);
			if ( empty($theAuthRow) ) {
				$theAuthRow = $dbAuth->getAuthByEmail($theUserInput);
			}
			//see if we can successfully log in now that we know what auth record
			if ( !empty($theAuthRow) ) {
				$theResult = $dbAuth->createAccountInfoObj($theAuthRow);
				//check pwinput against 1-way encrypted one
				if ( !Strings::hasher($theAuthInput, $theAuthRow['pwhash']) ) {
					//auth fail!
					$theResult->is_active = false;
				}
			}
		}
		return $theResult;
	}
	
	/**
	 * A mobile app is trying to automagically log someone in based on a
	 * previously generated user token and their device fingerprints.
	 * If they mostly match, log them in and generate the proper tokens.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	public function checkForAutoRequest(Scene $aScene)
	{
		$theResult = null;
		if ( !empty($aScene->auth_id) && !empty($aScene->user_token) ) {
			$dbAuth = $this->getMyModel();
			$theAuthRow = $dbAuth->getAuthByAuthId($aScene->auth_id);
		}
		if ( !empty($theAuthRow) ) {
			$theResult = $dbAuth->createAccountInfoObj($theAuthRow);
			//they must have a mobile auth row already
			$theAuthMobileRows = $dbAuth->getAuthMobilesByAuthId(
					$theResult->auth_id
			);
			if ( !empty($theAuthMobileRows) ) {
				//see if fingerprints match any of the existing
				//  records and return that row if so
				foreach ($theAuthMobileRows as $theAuthMobileRow) {
					//fingerprint and account_token needs to match for auto-login
					if ( $theAuthMobileRow['account_token'] == $aScene->user_token &&
							Strings::hasher($this->fingerprints,
									$theAuthMobileRow['fingerprint_hash']) )
					{
						//$this->debugLog(__METHOD__.' \o/'); //DEBUG
						$this->mMobileRow = $theAuthMobileRow;
						break;
					}
					//else $this->debugLog(__METHOD__.' :cry:'); //DEBUG
				}
				if ( empty($this->mMobileRow) ) {
					$theResult->is_active = false;
				}
			}
			else {
				$theResult->is_active = false;
			}
		}
		return $theResult;
	}
	
	/**
	 * Check to see if this venue should process the ticket.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return boolean Returns TRUE if this venue should process the ticket.
	 */
	protected function isTicketForThisVenue( Scene $aScene )
	{
		return !empty($this->ticket_name) ;
	}

	/**
	 * The Scene may contain authorization information.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function processTicket(Scene $aScene)
	{
		$theResult = $this->checkForAuthMapping($aScene);
		if ( empty($theResult) ) {
			$theResult = $this->checkForAuthRequest($aScene);
		}
		if ( empty($theResult) ) {
			$theResult = $this->checkForAutoRequest($aScene);
		}
		if ( !empty($theResult) ) {
			$this->determineMobileRow($theResult);
			if ( empty($this->mMobileRow) ) {
				//this is the first time they logged in via
				//  this mobile device, record it.
				$dbAuth = $this->getMyModel();
				$this->mMobileRow = $dbAuth->registerMobileFingerprints(
						$theResult, $this->fingerprints
				);
			}
			return $theResult;
		}
	}
	
	/**
	 * If we successfully authorize, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketAccepted(Scene $aScene, AccountInfoCache $aAcctInfo)
	{
		parent::onTicketAccepted($aScene, $aAcctInfo);
		//Once a pairing of device to auth account succeeds, then what?
		//  Default behavior is to delete the token for enhanced security.
		//  Remove any lingering device tokens unless one was JUST created
		if ( !empty($this->device_token_shelf_life)
				&& !empty($this->device_token_filter)
		) {
			$dbAuth = $this->getMyModel();
			$dbAuth->removeStaleTokens($this->device_token_filter, '1 SECOND');
		}
		return $this;
	}
	
}//end class

}//end namespace
