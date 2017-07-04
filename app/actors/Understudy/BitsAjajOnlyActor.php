<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\BrokenLeg;
use com\blackmoonit\exceptions\FourOhFourExit;
use com\blackmoonit\Strings;
{//namespace begin

class BitsAjajOnlyActor extends BaseActor {
	
	public function usherGreetAudience($aAction) {
		$this->usherGreetWithAntiCsrf($aAction);
	}
	
	public function usherAudienceToSeat($aAction) {
		$theResult = parent::usherAudienceToSeat($aAction);
		if ($theResult && !$this->usherCheckCsrfProtection($aAction))
			throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN);
		return $theResult;
	}
	
	protected function performAct($aAction, $aQuery) {
		if (!method_exists($this, $aAction)) {
			$theActorClass = $this->getDirector()->getActorClass($aAction);
			if (class_exists($theActorClass)) {
				$theAction = Strings::getMethodName('ajaj_'.array_shift($aQuery));
				$theActor = new $theActorClass($this->getDirector(), $theAction);
				$this->viewToRender('results_as_json'); //in case of error, JSON is returned
				$theActor->viewToRender('results_as_json');
				call_user_func_array(array($theActor, $theAction), $aQuery);
				$theActor->renderView($theActor->viewToRender());
				$this->viewToRender('_blank');
			} else
				throw new FourOhFourExit($this->getSiteUrl($aAction));
		} else {
			parent::performAct($aAction, $aQuery);
		}
	}
		
}//end class

}//end namespace
