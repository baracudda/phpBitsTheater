<?php
namespace BitsTheater\costumes\venue;
use BitsTheater\costumes\Wardrobe\TicketViaHttpHeader as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\Scene;
{//namespace begin

/**
 * Class used to help manage logging in via HTTP "Authorization" Header.
 * @since BitsTheater [NEXT]
 */
class TicketViaHttpHeader extends BaseCostume
{
	/**
	 * If we successfully authorize, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketAccepted(Scene $aScene, AccountInfoCache $aAcctInfo)
	{
		if ( !empty($this->mMobileRow) && !empty($this->mMobileRow['mobile_id']) ) {
			//$this->debugLog(__METHOD__.' save to session the mobile_id='.$this->mMobileID);//DEBUG
			$dbAuth = $this->getMyModel();
			$this->getDirector()[$dbAuth::KEY_MobileInfo] = $this->mMobileRow['mobile_id'];
		}
		return parent::onTicketAccepted($aScene, $aAcctInfo);
	}
	
}//end class

}//end namespace
