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
 * @author baracudda
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
	protected $mReplaceEnclosureLeft = '""';
	/**
	 * If data is enclosed, we cannot have the enclosure string
	 * within the data itself. Auto-replace with this string.
	 * @var string
	 */
	protected $mReplaceEnclosureRight = '""';
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
	 * Should a header row be generated? Default is FALSE.
	 * @var boolean
	 */
	protected $bGenerateHeader = false;
	/**
	 * Generate a csv output header row based on input column keys.
	 * @var boolean
	 */
	protected $bGenerateHeaderFromInput = false;
	/**
	 * Generate a csv output header row based on this set of values.
	 * @var string[]
	 */
	protected $mHeaderRow = null;
	/**
	 * Direct the csv output to the following stream. If this is not
	 * defined, a string is created containing the entire output.
	 * @var resource
	 */
	protected $mOutputStream = null;
	/**
	 * Callback functions keyed by column names so that alternate output
	 * is easily modified for a given column. Great for date formats.
	 * Callbacks are of the form myCallback($col, $row).
	 * @var callable[]
	 */
	protected $mCallbacks = array();
	/**
	 * The array to use for output data.
	 * @var array
	 */
	protected $mInputArray = null;
	/**
	 * The input as object that can ->fetch() output data.
	 * @var object
	 */
	protected $mInputPDOStatement = null;
	/**
	 * The csv output variable. It will be reset whenever it is pushed
	 * out to a stream, else will contain the entire output.
	 * @var string
	 */
	protected $csv = '';
	/**
	 * The csv output should start with a Byte Order Mark to signify
	 * non-ASCII content. Only applicable when using generateCSVfromInput().
	 * @var boolean
	 */
	public $bUseBOM = false;
	/**
	 * The list of named columns that require an `=` prepended to the value
	 * in order for Excel to treat is as General Text rather than a number.
	 * @var string[]
	 */
	protected $mColNamesToPrependEqual = null;

	/**
	 * Factory method for those that like to use them.
	 * @return $this Returns $this for chaining.
	 */
	static public function newInstance() {
		return new OutputToCSV();
	}
	
	/**
	 * Data can be enclosed, typically with double quotes (")
	 * when using comma (,) as a delimiter, which is the default.
	 * If only $aEnclosure is defined, then both the left and
	 * right enclosures will be the same.
	 *
	 * @param string $aEnclosure - the left enclosure (or both if only param).
	 * @param string $aEnclosureRight - (optional) sets the right enclosure string.
	 * @return $this Returns $this for chaining.
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
	 * @return $this Returns $this for chaining.
	 */
	public function setEnclosureReplacement($aEnclosureReplacement, $aEnclosureReplacementRight=null) {
		$this->mReplaceEnclosureLeft = (!is_null($aEnclosureReplacement)) ? $aEnclosureReplacement : '';
		$this->mReplaceEnclosureRight = (is_null($aEnclosureReplacementRight)) ? $this->mReplaceEnclosureLeft : $aEnclosureReplacementRight;
		return $this;
	}

	/**
	 * Sets the delimiter between values to use. Default is comma (,).
	 * @param string $aDelimiter - the string to use between values.
	 * @return $this Returns $this for chaining.
	 */
	public function setDelimiter($aDelimiter) {
		$this->mDelimiter = $aDelimiter;
		return $this;
	}
	
	/**
	 * Set column headers names that should have `=` prepended to the value
	 * so Excel will treat them as-is rather than try to convert.
	 * @param string[] $aColNamesToPrependEqual - list of column names.
	 * @return $this Returns $this for chaining.
	 */
	public function setColNamesToPrependEqual( $aColNamesToPrependEqual )
	{
		$this->mColNamesToPrependEqual = $aColNamesToPrependEqual;
		return $this;
	}
	
	/**
	 * Data used to export to CSV.
	 * @param array|object $aInput - input data.
	 * @return $this Returns $this for chaining.
	 */
	public function setInput($aInput) {
		if (is_array($aInput)) {
			$this->mInputArray = $aInput;
		} else if (is_object($aInput) && method_exists($aInput, 'fetch')) {
			$this->mInputPDOStatement = $aInput;
		} else {
			//TODO input as stream too!
		}
		return $this;
	}

	/**
	 * If output to a stream is desired, set the output stream
	 * to use with this method. Streams are not a class, so no
	 * type hints are possible, yet.
	 * @param mixed $aOutputStream - the output stream to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setOutputStream($aOutputStream) {
		$this->mOutputStream = $aOutputStream;
		return $this;
	}
	
	/**
	 * @return resource Returns the output stream in use.
	 */
	public function getOutputStream() {
		return $this->mOutputStream;
	}
	
	/**
	 * Setting for generating a header row (default FALSE).
	 * @param boolean $bUseInputForHeaderRow - (optional) default is TRUE
	 * since most that would call this method want to change it from its
	 * default value.
	 * @return $this Returns $this for chaining.
	 */
	public function setGenerateHeaderRow($bGenerateHeaderRow=true) {
		$this->bGenerateHeader = $bGenerateHeaderRow;
		return $this;
	}
	
	/**
	 * Header row data to use in case you want something different
	 * from the table column names.
	 * @param array $aHeaderRow - associative array where the array
	 * keys are the header names.
	 * @return $this Returns $this for chaining.
	 */
	public function setHeaderRowData($aHeaderRow) {
		$this->mHeaderRow = $aHeaderRow;
		return $this;
	}
	
	/**
	 * Setting for using input as header row (default FALSE). Setting
	 * this to TRUE will also setGenerateHeaderRow() to TRUE as well.
	 * @param boolean $bUseInputForHeaderRow - (optional) default is TRUE
	 * since most that would call this method want to change it from its
	 * default value.
	 * @return $this Returns $this for chaining.
	 */
	public function useInputForHeaderRow($bUseInputForHeaderRow=true) {
		$this->bGenerateHeaderFromInput = $bUseInputForHeaderRow;
		if ($bUseInputForHeaderRow && !$this->bGenerateHeader)
			$this->bGenerateHeader = true;
		return $this;
	}

	/**
	 * Some fields need special handling performed on export.
	 * @param string $aColName - the column name on when callback is invoked.
	 * @param callable $aCallbackFunction - callback function to get value to
	 * export. Callback signature: ( $col_value, $row_data[] ) returns string.
	 * @return $this Returns $this for chaining.
	 */
	public function setCallback($aColName, $aCallbackFunction) {
		$this->mCallbacks[$aColName] = $aCallbackFunction;
		return $this;
	}

	/**
	 * Retrieve the row indicated by the param or just get the next
	 * available row if indexing is not possible.
	 * @param number $aIdx - (optional) mainly used for array index retrieval.
	 * @return array|object|null Returns the data to output as CSV.
	 */
	protected function getInputRow($aIdx=0) {
		if (!empty($this->mInputArray)) {
			if (!empty($this->mInputArray[$aIdx]))
				return $this->mInputArray[$aIdx];
		} else if (!empty($this->mInputPDOStatement)) {
			$theRow = $this->mInputPDOStatement->fetch();
			if ( is_object($theRow) ) {
				if ( method_exists($theRow, 'exportData') ) {
					return $theRow->exportData();
				}
				else {
					return $theRow;
				}
			}
			else {
				return $theRow;
			}
		}
		return null;
	}

	/**
	 * Generate just the header CSV line based on param or prior set header row data.
	 * @param string[] $aHeaderValues - (optional) header values to output; will use mHeaderRow
	 * if nothing was passed in.
	 * @return string Returns the CSV header line to output.
	 */
	protected function generateHeaderRow($aHeaderValues=null) {
		$theHeaderValues = (!empty($aHeaderValues)) ? $aHeaderValues : $this->mHeaderRow;
		$theOutput = array();
		foreach ($theHeaderValues as $theName) {
			$theName = str_replace($this->mEnclosureLeft,$this->mReplaceEnclosureLeft,$theName);
			$theName = str_replace($this->mEnclosureRight,$this->mReplaceEnclosureRight,$theName);
			$theOutput[] = $theName;
		}
		$theSeparator = $this->mEnclosureRight.$this->mValueDelimiter.$this->mEnclosureLeft;
		return $this->mEnclosureLeft.implode($theSeparator, $theOutput).$this->mEnclosureRight.$this->mLineDelimiter;
	}
	
	protected function isColumnPrependEqual( $aColName, $aColValue )
	{
		if ( Strings::isInStr($aColValue, ',') ) return false; //do not need if contains a comma.
		if ( !empty($this->mColNamesToPrependEqual) ) {
			if ( in_array($aColName, $this->mColNamesToPrependEqual) ) {
				return true;
			}
		}
		return ( Strings::beginsWith($aColValue, '+') || Strings::beginsWith($aColValue, '0') );
	}
	
	/**
	 * Workhorse method that actually generates the CSV and either outputs each line to the
	 * defined output stream or caches the entire CSV into an object property.
	 */
	protected function generateCSVfromInput() {
		// using concatenation since it is faster than fputcsv, and file size is smaller
		$this->csv = ( $this->bUseBOM ) ? Strings::createBOM() : '';

		$theIdx = 0;
		$theRow = $this->getInputRow($theIdx);
		if (!empty($theRow) && $this->bGenerateHeader) {
			if ($this->bGenerateHeaderFromInput || empty($this->mHeaderRow)) {
				$theHeaderData = array();
				foreach ($theRow as $theHeaderValue => $someRowData)
				{ $theHeaderData[] = $theHeaderValue; }
				$this->setHeaderRowData($theHeaderData);
			}
			$this->csv .= $this->generateHeaderRow();
			if ($this->mOutputStream) {
				fputs($this->mOutputStream, $this->csv, strlen($this->csv));
				$this->csv = '';
			}
		}
		while (!empty($theRow)) {
			//row data may be just 1 value column with no actual column name
			if (is_string($theRow))
				$theRow = array($theRow);
			//generate output row
			foreach ($theRow as $theColName => $theColValue) {
				if (!empty($this->mCallbacks[$theColName])) {
					$theColValue = $this->mCallbacks[$theColName]($theColValue, $theRow);
				}
				if ( !empty($theColValue) ) {
					$theColValue = str_replace($this->mEnclosureLeft, $this->mReplaceEnclosureLeft, $theColValue);
					if ($this->mEnclosureLeft != $this->mEnclosureRight)
						$theColValue = str_replace($this->mEnclosureRight, $this->mReplaceEnclosureRight, $theColValue);
					// Carriage Return and/or New Line converted to the literal '\n'
					$theColValue = str_replace(array("\r\n", "\n", "\r"), '\n', $theColValue);
					//to prevent Excel from converting value to formula, prepend with '=' before enclosure.
					if ( $this->isColumnPrependEqual($theColName, $theColValue) ) {
						$this->csv .= '=';
					}
					$this->csv .= $this->mEnclosureLeft.$theColValue.$this->mEnclosureRight;
				}
				$this->csv .= $this->mValueDelimiter;
			}
			$theDelimSize = strlen($this->mValueDelimiter);
			$this->csv = substr_replace($this->csv, $this->mLineDelimiter, -$theDelimSize, $theDelimSize);
			//if stream is defined, output to stream and reset csv (large data friendly, that way)
			if ($this->mOutputStream) {
				fputs($this->mOutputStream, $this->csv, strlen($this->csv));
				$this->csv = '';
			}
			//get the next row to output
			$theIdx += 1;
			$theRow = $this->getInputRow($theIdx);
		}
	}
	
	/**
	 * Convert the input into a CSV output using the defined options/settings.
	 * @param $aInput - (optional if already been set) array/stream to convert.
	 * @return $this|string - Returns a string if no output stream is defined, else $this.
	 * @see StackOverflow.com http://stackoverflow.com/a/21858025
	 */
	public function generateCSV($aInput=null) {
		if (!empty($aInput))
			$this->setInput($aInput);
		$this->generateCSVfromInput();
		if ($this->mOutputStream) {
			return $this;
		} else {
			return $this->csv;
		}
	}
	
	/**
	 * Save the CSV output to a file. If generateCSV() has not been
	 * called yet, this method will setOutputStream() and then call
	 * generateCSV() to write the output directly to the file. Use
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
			if (!empty($this->csv)) {
				FileUtils::fstream_write($theFileHandle, $this->csv);
			} else {
				$this->setOutputStream($theFileHandle);
				$this->generateCSV();
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
	
	/**
	 * Downloading a generated CSV might require a "best guess" as to what
	 * the client expects for each line ending. If Windows, use \r\n, else \n.
	 * @param boolean $bCheckUserAgent - should we use the client's User Agent
	 *     string to determine the line ending? default is false.
	 */
	public function determineClientLineEnding($bCheckUserAgent=false)
	{
		//if TRUE, we want the mLineDelimiter to be based on the Client, not the Server.
		if ($bCheckUserAgent) {
			if (preg_match('~Windows |Win(?:NT|98|95|32|16)~', $_SERVER['HTTP_USER_AGENT'])) {
				//$platform = 'MS Windows';
				$this->mLineDelimiter = "\r\n";
			} else {
				$this->mLineDelimiter = "\n";
			}
		}
	}

}//end class

}//end namespace
