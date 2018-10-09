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

/**
 * Output to ICS is complex enough to warrent its own class.  With so many options
 * available to modify the output to a ICS file/stream, making each option a
 * chainable method makes creating one that much easier.
 * @author baracudda
 */
class OutputToICS {
	/**
	 * Direct the output to the following stream. If this is not
	 * defined, a string is created containing the entire output.
	 * The var is a resource (stream).
	 */
	protected $mOutputStream = null;
	/**
	 * The array of CalendarEntrys to use for output data.
	 * @var ICalendarEntry[]
	 */
	protected $mCalendarEntries = null;
	/**
	 * The Product ID to use.
	 * @var string
	 */
	protected $mProductID = '-//blackmoonit.com//Calendar Events//EN';
	//protected $mOutputType = 'outlook'; //'apple' //may not be needed as yet
	/**
	 * The ics output variable. It will be reset whenever it is pushed
	 * out to a stream, else will contain the entire output.
	 * @var string
	 */
	protected $ics = '';
	
	/**
	 * Factory method for those that like to use them.
	 * @return \com\blackmoonit\OutputToICS
	 */
	static public function newInstance() {
		return new OutputToICS();
	}
	
	/**
	 * Data used to export to ICS.
	 * @param array|ICalendarEntry $aCalEntry - a single or an array of
	 *   calendar entry data.
	 * @return \com\blackmoonit\OutputToICS
	 */
	public function setCalendarEntries($aCalEntry) {
		if (!is_array($aCalEntry))
			$this->mCalendarEntries = array($aCalEntry);
		else
			$this->mCalendarEntries = $aCalEntry;
		return $this;
	}

	/**
	 * If output to a stream is desired, set the output stream
	 * to use with this method. Streams are not a class, so no
	 * type hints are possible, yet.
	 * @param mixed $aOutputStream - the output stream to use.
	 * @return \com\blackmoonit\OutputToICS
	 */
	public function setOutputStream($aOutputStream) {
		$this->mOutputStream = $aOutputStream;
		return $this;
	}
	
	/**
	 * @return mixed Returns the output stream in use.
	 */
	public function getOutputStream() {
		return $this->mOutputStream;
	}
	
	/**
	 * Set the Product ID to use.
	 * @param string $aProductID - the product ID to use.
	 * @return \com\blackmoonit\OutputToICS
	 */
	public function setProductId($aProductID) {
		if (!empty($aProductID))
			$this->mProductID = $aProductID;
		return $this;
	}
	
	protected function pushToOutput($s) {
		//if stream is defined, output to stream and reset ics (large data friendly, that way)
		if (!empty($this->mOutputStream)) {
			fputs($this->mOutputStream, $s, strlen($s));
		} else {
			$this->ics += $s;
		}
	}
	
	protected function exportEvent(ICalendarEntry $aCalEntry) {
		$this->pushToOutput("BEGIN:VEVENT\n");
		$this->pushToOutput("DTSTAMP:".date('Ymd\THis\Z', time())."\n");
		$theStr = $aCalEntry->getSummary();
		if (!empty($theStr))
	    	$this->pushToOutput("SUMMARY:{$theStr}\n");
		$theStr = $aCalEntry->getDescription();
		if (!empty($theStr))
	    	$this->pushToOutput("DESCRIPTION:{$theStr}\n");
		$theStr = $aCalEntry->getURL();
		if (!empty($theStr))
	    	$this->pushToOutput("URL:{$theStr}\n");
		$theStr = $aCalEntry->getLocation();
		if (!empty($theStr))
	    	$this->pushToOutput("LOCATION:{$theStr}\n");
		$theStrName = $aCalEntry->getOrganizerName();
		$theStrEmail = $aCalEntry->getOrganizerEmail();
		if (!empty($theStrName) || !empty($theStrEmail)) {
	    	$this->pushToOutput("ORGANIZER;");
	    	if (!empty($theStrName))
	    		$this->pushToOutput("CN:{$theStrName}");
	    	if (!empty($theStrEmail))
		    	$this->pushToOutput(":MAILTO:{$theStrEmail}\n");
		}
		$theStr = $aCalEntry->getEventId();
		if (!empty($theStr))
			$this->pushToOutput("UID:{$theStr}\n");
		$this->exportEventTimes($aCalEntry);
		$this->pushToOutput("END:VEVENT\n");
	}
	
	function exportEventTimes(ICalendarEntry $aCalEntry) {
    	$this->pushToOutput("DTSTART:".$aCalEntry->getStartDateAsDateTime()->format('Ymd\THis\Z')."\n");
    	$this->pushToOutput("DTEND:".$aCalEntry->getEndDateAsDateTime()->format('Ymd\THis\Z')."\n");
    	//Apple may need a slightly diff: "DTSTART;VALUE=DATE-TIME:" and same for DtEnd
	}
	
	/**
	 * Workhorse method that actually generates the ICS. It either outputs to the
	 * defined output stream or caches the entire ICS into an object property.
	 */
	protected function generateICSfromCalEntries() {
		$this->ics = '';
		if (empty($this->mCalendarEntries))
			return;
		$this->pushToOutput("BEGIN:VCALENDAR\n");
		$this->pushToOutput("PRODID:".$this->mProductID."\n");
		$this->pushToOutput("VERSION:2.0\n");
		$this->pushToOutput("CALSCALE:GREGORIAN\n");
		foreach ($this->mCalendarEntries as $theCalEntry) {
			//Strings::debugLog($theCalEntry);
			$this->pushToOutput($this->exportEvent($theCalEntry));
		}
		$this->pushToOutput("END:VCALENDAR\n");
	}
	
	/**
	 * Convert the input into a ICS output using the defined options/settings.
	 * @param CalendarEntry $aCalEntry - (optional if already been set) the
	 *   calendar entry data.
	 * @return \com\blackmoonit\OutputToICS|string - Returns a string if no
	 *   output stream is defined, else $this.
	 */
	public function generateICS($aCalEntry=null) {
		if (!empty($aCalEntry))
			$this->setCalendarEntries($aCalEntry);
		$this->generateICSfromCalEntries();
		if (!empty($this->mOutputStream)) {
			return $this;
		} else {
			return $this->ics;
		}
	}
	
	/**
	 * Save the ICS output to a file. If generateICS() has not been
	 * called yet, this method will setOutputStream() and then call
	 * generateICS() to write the output directly to the file. Use
	 * the latter mechanism for large outputs to avoid running out
	 * of memory PHP is allowed to use.
	 * @param string $aFilePath - a destination filepath, ensure folders
	 * exists before calling this method.
	 * @return boolean Returns TRUE if file was successfully saved.
	 */
	public function generateOutputToFile($aFilePath) {
		$bSuccess = false;
		$theFileHandle = fopen($aFilePath, 'c');
		if (flock($theFileHandle, LOCK_EX)) {
			ftruncate($theFileHandle, 0);
			if (!empty($this->ics)) {
				FileUtils::fstream_write($theFileHandle, $this->ics);
			} else {
				$this->setOutputStream($theFileHandle);
				$this->generateICS();
			}
			fflush($theFileHandle);
			flock($theFileHandle, LOCK_UN);
			$bSuccess = true;
		} else {
			$this->errorLog(__METHOD__.' lock fail: '.$aFilePath);
		}
		fclose($theFileHandle);
		return $bSuccess;
	}

}//end class

}//end namespace