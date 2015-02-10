<?php
/*
 * Copyright (C) 2013 Blackmoon Info Tech Services
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
 * Output to CSV is complex enough to warrent its own class.  With so many options
 * available to modify the output to a CSV file/stream, making each option a
 * chainable method makes creating one that much easier.
 * @author Ryan Fischbach
 */
class OutputToCSV {
	/**
	 * Data can be enclosed, typically with double quotes (")
	 * when using comma (,) as a delimiter.
	 * @var string
	 */
	protected $mEnclosureLeft = '"';
	/**
	 * Data can be enclosed, typically with double quotes (")
	 * when using comma (,) as a delimiter.
	 * @var string
	 */
	protected $mEnclosureRight = '"';
	/**
	 * If data is enclosed, we cannot have the enclosure string
	 * within the data itself. Auto-replace with this string.
	 * @var string
	 */
	protected $mReplaceEnclosureLeft = '&quot';
	/**
	 * If data is enclosed, we cannot have the enclosure string
	 * within the data itself. Auto-replace with this string.
	 * @var string
	 */
	protected $mReplaceEnclosureRight = '&quot';
	/**
	 * The value delimiter is the string used between values in a row.
	 * The value delimiter defaults to a comma, but it can be any string.
	 * @var string
	 */
	protected $mValueDelimiter = ',';
	/**
	 * The line delimiter is the string used between rows.
	 * The line delimiter defaults to PHP_EOL, but it can be any string.
	 * @var string
	 */
	protected $mLineDelimiter = PHP_EOL;
	/**
	 * Generate a csv output header row based on input column keys.
	 * @var boolean
	 */
	protected $bGenerateHeaderFromInput = false;
	/**
	 * Generate a csv output header row based on this array's values.
	 * @var array
	 */
	protected $mHeaderRow = null;
	/**
	 * Direct the csv output to the following stream. If this is not
	 * defined, a string is created containing the entire output.
	 * @var /Stream?
	 */
	protected $mOutputStream = null;
	/**
	 * Callback functions keyed by column names so that alternate output
	 * is easily modified for a given column. Great for date formats.
	 * Callbacks are of the form myCallback($col, $row).
	 */
	protected $mCallbacks = array();
	/**
	 * The array to use for output data.
	 * @var array
	 */
	protected $mInputArray = null;
	/**
	 * The csv output variable. It will be reset whenever it is pushed
	 * out to a stream, else will contain the entire output.
	 * @var string
	 */
	protected $csv = '';
	
	/**
	 * Create a new instance of OutputToCSV.
	 */
	public function OutputToCSV() {
	}
	
	/**
	 * Data can be enclosed, typically with double quotes (")
	 * when using comma (,) as a delimiter, which is the default.
	 * If only $aEnclosure is defined, then both the left and
	 * right enclosures will be the same.
	 *
	 * @param string $aEnclosure - the left enclosure (or both if only param).
	 * @param string $aEnclosureRight - (optional) sets the right enclosure string.
	 * @return \com\blackmoonit\OutputToCSV Returns $this for chaining.
	 */
	public function setEnclosure($aEnclosure, $aEnclosureRight=null) {
		$this->mEnclosureLeft = (!is_null($aEnclosure)) ? $aEnclosure : '';
		$this->mEnclosureRight = (is_null($aEnclosureRight)) ? $this->mEnclosureLeft : $aEnclosureRight;
		return $this;
	}

	/**
	 * If data is enclosed, we cannot have the enclosure string
	 * within the data itself. Auto-replace such values with something else.
	 * If only $aEnclosureReplacement is defined, then both the left and
	 * right enclosures will be the same.
	 *
	 * @param string $aEnclosureReplacement - the left enclosure replacement (or both if only param).
	 * @param string $aEnclosureReplacementRight - (optional) sets the right enclosure replacement string.
	 * @return \com\blackmoonit\OutputToCSV Returns $this for chaining.
	 */
	public function setEnclosureReplacement($aEnclosureReplacement, $aEnclosureReplacementRight=null) {
		$this->mReplaceEnclosureLeft = (!is_null($aEnclosureReplacement)) ? $aEnclosureReplacement : '';
		$this->mReplaceEnclosureRight = (is_null($aEnclosureReplacementRight)) ? $this->mReplaceEnclosureLeft : $aEnclosureReplacementRight;
		return $this;
	}

	/**
	 * Sets the delimiter between values to use. Default is comma (,).
	 * @param string $aDelimiter - the string to use between values.
	 * @return \com\blackmoonit\OutputToCSV Returns $this for chaining.
	 */
	public function setDelimiter($aDelimiter) {
		$this->mDelimiter = $aDelimiter;
		return $this;
	}
	
	public function setInputArray(array &$aInput) {
		$this->mInputArray = $aInput;
		return $this;
	}

	public function setInput($aInput) {
		if (is_array($aInput))
			$this->mInputArray = $aInput;
		else { //idk yet
			//TODO input as stream too!
		}
		return $this;
	}

	public function setOutputStream($aOutputStream) {
		$this->mOutputStream = $aOutputStream;
		return $this;
	}
	
	public function setHeaderRowData($aHeaderRow) {
		$this->mHeaderRow = $aHeaderRow;
		return $this;
	}
	
	public function useInputForHeaderRow($bUseInputForHeaderRow=true) {
		$this->bGenerateHeaderFromInput = $bUseInputForHeaderRow;
		return $this;
	}
	
	public function setCallback($aColName, $aCallbackFunction) {
		$this->mCallbacks[$aColName] = $aCallbackFunction;
		return $this;
	}
	
	/**
	 * Convert the input into a CSV output using the defined options/settings.
	 * @param array $aInput - (optional if already been set) array/stream to convert.
	 * @return string - Returns a string if no output stream is defined, else !empty($this->mInput).
	 * @see StackOverflow.com http://stackoverflow.com/a/21858025
	 */
	public function generateCSV($aInput=null) {
		if (!empty($aInput))
			$this->setInput($aInput);
		if (empty($this->mInputArray))
			return false;
		// using concatenation since it is faster than fputcsv, and file size is smaller
		$this->csv = '';
		
		//TODO when input is a stream, and bGenerateHeaderFromInput is used, need to do header/first line special
		if (!empty($this->mHeaderRow) || $this->bGenerateHeaderFromInput) {
			$theSeparator = $this->mEnclosureLeft.$this->mValueDelimiter.$this->mEnclosureRight;
			$theHeaderValues = (!empty($this->mHeaderRow)) ? $this->mHeaderRow : array_keys($this->mInputArray[0]);
			foreach ($theHeaderValues as &$theName) {
				$theName = str_replace($this->mEnclosureLeft,$this->mReplaceEnclosureLeft,$theName);
				$theName = str_replace($this->mEnclosureRight,$this->mReplaceEnclosureRight,$theName);
			}
			$this->csv .= $this->mEnclosureLeft.implode($theSeparator, $theHeaderValues).$this->mEnclosureRight.$this->mLineDelimiter;
			if ($this->mOutputStream) {
				$this->mOutputStream.put($this->csv);
				$this->csv = '';
			}
		}
		//data output
		foreach ($this->mInputArray as $theRow) {
			foreach ($theRow as $theColName => $theColValue) {
				if (!empty($this->mCallbacks[$theColName])) {
					$theColValue = $this->mCallbacks[$theColName]($theColValue, $theRow);
				}
				$theColValue = str_replace($this->mEnclosureLeft,$this->mReplaceEnclosureLeft,$theColValue);
				$theColValue = str_replace($this->mEnclosureRight,$this->mReplaceEnclosureRight,$theColValue);
				// Carriage Return and/or New Line converted to the literal '\n'
				$theColValue = str_replace(array("\r\n", "\n", "\r"),'\n',$theColValue);
				$this->csv .= $this->mEnclosureLeft.$theColValue.$this->mEnclosureRight.$this->mValueDelimiter;
			}
			$this->csv .= $this->mLineDelimiter;
			if ($this->mOutputStream) {
				$this->mOutputStream.put($this->csv);
				$this->csv = '';
			}
		}
		if ($this->mOutputStream) {
			return true;
		} else {
			return $this->csv;
		}
	}

}//end class

}//end namespace