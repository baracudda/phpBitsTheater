<?php
namespace BitsTheater\actors;
use BitsTheater\Actor as BaseActor;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\FourOhFourExit;
{//namespace begin

class Api extends BaseActor
{
	
	/**
	 * API action is "special" in that it can load ANY other Actor as if it was a method.
	 * @see \BitsTheater\Actor::performAct()
	 */
	protected function performAct($aAction, $aQuery) {
		if (!method_exists($this, $aAction)) {
			$theActorClass = $this->getDirector()->getActorClass($aAction);
			if (class_exists($theActorClass)) {
				$theAction = Strings::getMethodName('ajaj_'.array_shift($aQuery));
				$theActor = new $theActorClass($this->getDirector(), $theAction);
				$this->viewToRender('results_as_json'); //in case of error, JSON is returned
				$theActor->viewToRender('results_as_json');
				//in order to ensure ajaj*/api* protection, forget admitted account info that got us this far
				$this->getDirector()->account_info = null;
				//NOTE: do NOT call setMyAccountInfo() to forget the admitted account
				//  as doing that will prevent a consumed cookie from being found again; so
				//  we really need to keep any session-cached information in case this
				//  subsequent usherAudienceToSeat() needs that information.
				//now usher them back in based on the new Actor/Action
				$theActor->usherGreetAudience($theAction);
				if ($theActor->usherAudienceToSeat($theAction)) {
					$theResult = call_user_func_array(array($theActor, $theAction), $aQuery);
					if (empty($theResult)) {
						$theActor->renderView($theActor->viewToRender());
						$this->viewToRender('_blank');
					}
				}
			} else
				throw new FourOhFourExit($this->getSiteUrl($aAction));
		} else {
			return parent::performAct($aAction, $aQuery);
		}
	}
	
}//end class

}//end namespace
