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

namespace com\blackmoonit;
{//begin namespace

interface ICalendarEntry {

	/**
	 * @return string Returns the summary text.
	 */
	public function getSummary();
	
	/**
	 * @return string Returns the description text.
	 */
	public function getDescription();
	
	/**
	 * @return string Returns the URL to show, if any.
	 */
	public function getURL();
	
	/**
	 * @return string Returns the location for the event.
	 */
	public function getLocation();
	
	/**
	 * @return string Returns the organizer name.
	 */
	public function getOrganizerName();

	/**
	 * @return string Returns the organizer email.
	 */
	public function getOrganizerEmail();
	
	/**
	 * @return string Returns the event's ID.
	 */
	public function getEventId();
	
	/**
	 * @return \DateTime Returns the start date as DateTime class.
	 */
	public function getStartDateAsDateTime();
	
	/**
	 * @return \DateTime Returns the end date as DateTime class.
	 */
	public function getEndDateAsDateTime();
	
	/**
	 * @return \DateInterval Returns the duration as DateInterval class.
	 */
	public function getEventDuration();

}//end interface

}//end namespace
