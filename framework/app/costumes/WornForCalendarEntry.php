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

namespace BitsTheater\costumes ;
{//begin namespace

/**
 * A set of methods helpful for CalendarEntry classes.
 */
trait WornForCalendarEntry
{
	const FORMAT_FOR_DATETIME = 'Ymd\THis\Z';
	const FORMAT_FOR_DURATION = '%H%I';
	
	/**
	 * @param number $aTimestamp - a Unix timestamp.
	 * @return string Returns the timestamp converted into vCal format.
	 */
	public function timestampToCal($aTimestamp)
	{
		return date(self::FORMAT_FOR_DATETIME, $aTimestamp);
	}
	
	/**
	 * Event data that needs to be encoded as a URL link needs some
	 * special preparations before it can be safely used.
	 * @param string $aStr - the data to encode for a URL link.
	 * @return string Returns the encoded string.
	 */
	public function formatUrlData($aStr)
	{
		return urlencode(utf8_encode($aStr));
	}
	
	/**
	 Google online calendar link:
	 href=&
	 text=Haunted+Hayride&
	 dates=20111028T190000/20111028T230000&
	 location=Sleepy Hollow Village Hall, 28 Beekman Avenue, Tarrytown, NY, 10591&
	 details=http%3A%2F%2Fwww.americantowns.com%2Fny%2Ftarrytown%2Fevents%2Fhaunted-hayride%0A%0AFrom+AmericanTowns.com+event+calendar+for+Tarrytown%2C+NY&
	 trp=false&
	 sprop=website:http://www.americantowns.com/ny/tarrytown/events/haunted-hayride
	 &sprop;=name:Haunted+Hayride"
	 @return string Returns the formatted URL link.
	 */
	public function createUrlForGoogleOnlineCalendar()
	{
		if (!empty($this->getStartDateAsDateTime()) && !empty($this->getEndDateAsDateTime())) {
			$s = "http://www.google.com/calendar/event?action=TEMPLATE";
			$s .= "&text=".$this->formatUrlData($this->getSummary());
			$s .= "&dates=".$this->getStartDateAsDateTime()->format(self::FORMAT_FOR_DATETIME);
			$s .= "/".$this->getEndDateAsDateTime()->format(self::FORMAT_FOR_DATETIME);
			$s .= "&location=".$this->formatUrlData($this->getLocation());
			$s .= "&details=".$this->formatUrlData($this->getDescription()).'+%0a'.$this->formatUrlData($this->getURL());
			$s .= "&trp=false";
			$s .= "&sprop=website:".$this->formatUrlData($this->getURL());
			$s .= "&sprop;=name:".$this->formatUrlData($this->getSummary());
			return $s;
		}
	}
	
	/**
	 Yahoo online calendar link:
	 href="http://calendar.yahoo.com/?v=60
	 &TITLE=Haunted+Hayride
	 &DESC=http%3A%2F%2Fwww.americantowns.com%2Fny%2Ftarrytown%2Fevents%2Fhaunted-hayride%0A%0AFrom+AmericanTowns.com+event+calendar+for+Tarrytown%2C+NY
	 &ST=20111028T190000
	 &DUR=0400
	 &in_loc=Sleepy Hollow Village Hall, 28 Beekman Avenue, Tarrytown, NY, 10591
	 &URL=http://www.americantowns.com/ny/tarrytown/events/haunted-hayride"
	 @return string Returns the formatted URL link.
	 */
	public function createLinkForYahooOnlineCalendar()
	{
		if (!empty($this->getStartDateAsDateTime()) && !empty($this->getEventDuration())) {
			$s = "http://calendar.yahoo.com/?v=60";
			$s .= "&TITLE=".$this->formatUrlData($this->getSummary());
			$s .= "&DESC=".$this->formatUrlData($this->getDescription());
			$s .= "&ST=".$this->getStartDateAsDateTime()->format(self::FORMAT_FOR_DATETIME);
			$s .= "&DUR=".$ical->getEventDuration()->format(self::FORMAT_FOR_DURATION);
			$s .= "&in_loc=".$this->formatUrlData($this->getLocation());
			$s .= "&URL=".$this->formatUrlData($this->getURL());
			return $s;
		}
	}
	
}//end class

}//end namespace
	