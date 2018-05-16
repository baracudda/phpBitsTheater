<?php
namespace BitsTheater\actors;
use BitsTheater\Actor as BaseActor;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\FourOhFourExit;
{//namespace begin

class Api extends BaseActor
{
	
	/**
	 * Since the purpose of API class is to "redirect" the URL to the
	 * actual actor/method being referenced, construct and return the list
	 * of possible methods being called.
	 * @param string $aPossibleAction - the action requested.
	 * @return string[] Returns the list of possible matches to query.
	 */
	protected function getPossibleMethodList($aPossibleAction)
	{
		return array(
				Strings::getMethodName($aPossibleAction),
				Strings::getMethodName('ajaj_' . $aPossibleAction),
				Strings::getMethodName('api_' . $aPossibleAction),
				Strings::getMethodName('ajax_' . $aPossibleAction),
		);
	}
	

	/**
	 * API action is "special" in that it can load ANY other Actor as if it was a method.
	 * @see \BitsTheater\Actor::performAct()
	 */
	protected function performAct($aAction, $aQuery) {
		if (!method_exists($this, $aAction)) {
			$theActorClass = $this->getDirector()->getActorClass($aAction);
			if (class_exists($theActorClass)) {
				$theAction = null;
				$thePossibleMethodList = $this->getPossibleMethodList(array_shift($aQuery));
				foreach($thePossibleMethodList as $thePossibleMethod) try {
					$theMethod = new \ReflectionMethod($theActorClass, $thePossibleMethod);
					if ( $theMethod->isPublic() ) {
						$theAction = $thePossibleMethod;
						break;
					}
				}
				catch (\ReflectionException $rx)
				{ continue; }
				if ( empty($theAction) )
				{ throw new FourOhFourExit($this->getSiteUrl($aAction . '/' . $thePossibleAction)); }
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
