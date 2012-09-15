<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace com\blackmoonit;
use \DateTime;
use \DateInterval;
{//begin namespace

/**
 * Generic calendar entry used to export in iCal format into various standard calendar apps/sites.
 * @author Ryan Fischbach
 */
class CalendarEntry extends AdamEve {
	const _SetupArgCount = 3; //number of args required to call the setup() method.
	public $name;
	public $dtStart;   //always in UTC
	public $duration;
	public $dtEnd;     //always in UTC (usually calculated from Start+Duration)
	public $details;
	public $linkUrl;
	public $location;
	public $organizer_name; //ORGANIZER;CN:Shadowwolf:MAILTO:twl234M42902303@twguild.org
	public $organizer_email;
	public $id;        //e.g. UID:%starttime%-%raidid%@twguild.org
	
	public function setup($aName, $aDtStart, $aDuration) {
		parent::setup();
		$this->name = $aName;
    	//start time
    	if (is_object($aDtStart) && $aDtStart instanceof DateTime) {
	    	$this->dtStart = $aDtStart;
    	} else {
    		$this->dtStart = new DateTime($aDtStart);
    	}
    	//duration
    	if (is_object($aDuration) && $aDuration instanceof DateInterval) {
			$this->duration = $aDuration;
		} else if (is_numeric($aDuration)) {
			$intPart = intval($aDuration);
			$fVal = floatval($aDuration);
			$minutes = round(($fVal-$intPart)*60);
			$this->duration = new DateInterval('PT'.$intPart.'H'.$minutes.'M');
		} else {
			$this->duration = new DateInterval('P1D');
		}
		//end time
		$this->dtEnd = new DateTime('@'.$this->dtStart->getTimestamp());
		$this->dtEnd->add($this->duration); 
	}
	
	public function setOrganizer($aOrganizerArray) {
		$this->organizer_name = $aOrganizerArray[0];
		$this->organizer_email = $aOrganizerArray[1];
	}
	
 	public function formatDtStart() {
		return $this->dtStart->format('Ymd\THis\Z');
	}	

	public function formatDtEnd() {
		return $this->dtEnd->format('Ymd\THis\Z');
	}	
	
	public function formatDuration() {
		return $this->duration->format('%H%I');
	}
	
}//end class
}//end namespace
