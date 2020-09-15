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

class Arrays {

	private function __construct() {} //do not instantiate

	/**
	 * Circularly shifts an array
	 *
	 * Shifts to right for $steps > 0. Shifts to left for $steps < 0. Keys are
	 * preserved.
	 *
	 * @param array $aArray - array to shift
	 * @param int $aSteps - # of steps to shift array
	 * @return array Resulting array
	 */
	static public function array_shift_circular(array $anArray, $aSteps = 1) {
		if (!is_int($aSteps)) {
			throw new \InvalidArgumentException('steps has to be an (int)');
		}
		$len = count($anArray);
		if ($len === 0 || $aSteps === 0) {
			return $anArray;
		}
		$theBreakIdx = ($aSteps % $len) * -1;
		return array_merge(array_slice($anArray, $theBreakIdx), array_slice($anArray, 0, $theBreakIdx));
	}

	/**
	 * Prepends all args after the first to the first arg (which is an array).
	 * @param array $aArray - array to prepend all other args
	 * @return array Resulting array.
	 */
	static public function array_prepend(array &$anArray) {
		return array_merge(array_slice(func_get_args(),1),$anArray);
	}
	
	/**
	 * Appends all args after the first to the first arg (which is an array).
	 * @param array $aArray - array to append all other args
	 * @return array Resulting array.
	 */
	static public function array_append(array &$anArray) {
		return array_merge($anArray,array_slice(func_get_args(),1));
	}
	
	/**
	 * Given a two dimensional array, return the singular array of a
	 * single specified column.
	 * @param array $anArray - array of arrays.
	 * @param int|string $aKey - index of column to retrieve.
	 * @return array Returns a one dimensional array of just $anArray[$subArray[$aKey]] values.
	 * @link http://www.php.net//manual/en/function.array-column.php array_column()
	 */
	static public function array_column($anArray, $aKey) {
		if (function_exists('array_column')) {
			return array_column($anArray, $aKey);
		} else {
			return array_map(function($e) use (&$aKey) {return $e[$aKey];}, $anArray);
		}
	}
	
	/**
	 * Given a two dimensional array, return that same array with keys redefined
	 * as the values from $anArray[$aKey].
	 * @param array $anArray - array of arrays.
	 * @param string $aKey - index of column to make as keys
	 * @return array Returns an array re-indexed using $aKey column.
	 */
	static public function array_column_as_key($anArray, $aKey) {
		return array_combine(self::array_column($anArray,$aKey), $anArray);
	}
	
	/**
	 * Compute the diff between two arrays by generating two arrays:
	 * values array: a list of elements as they appear in the diff.
	 * diff array: contains numbers. 0: unchanged, -1: removed, 1: added.
	 * @param array $aV1 - array version 1
	 * @param array $aV2 - array version 2
	 * @param string $aDelimiter - the glue used to put arrays back together again.
	 * @return array('values','diff','delimiter') Return the values and mask arrays.
	 */
	static public function computeDiff($aV1, $aV2, $aDelimiter='') {
		$theValues = array();
		$theDiff = array();
		$dm = array();
		$n1 = count($aV1);
		$n2 = count($aV2);
		for ($j = -1; $j < $n2; $j++)
			$dm[-1][$j] = 0;
		for ($i = -1; $i < $n1; $i++)
			$dm[$i][-1] = 0;
		for ($i = 0; $i < $n1; $i++) {
			for ($j = 0; $j < $n2; $j++) {
				if ($aV1[$i] == $aV2[$j]) {
					$ad = $dm[$i - 1][$j - 1];
					$dm[$i][$j] = $ad + 1;
				} else {
					$a1 = $dm[$i - 1][$j];
					$a2 = $dm[$i][$j - 1];
					$dm[$i][$j] = max($a1, $a2);
				}
			}
		}
		$i = $n1 - 1;
		$j = $n2 - 1;
		while (($i > -1) || ($j > -1)) {
			if ($j > -1) {
				if ($dm[$i][$j - 1] == $dm[$i][$j]) {
					$theValues[] = $aV2[$j];
					$theDiff[] = 1;
					$j--;
					continue;
				}
			}
			if ($i > -1) {
				if ($dm[$i - 1][$j] == $dm[$i][$j]) {
					$theValues[] = $aV1[$i];
					$theDiff[] = -1;
					$i--;
					continue;
				}
			}
			$theValues[] = $aV1[$i];
			$theDiff[] = 0;
			$i--;
			$j--;
		}
		return array(
				'values' => array_reverse($theValues),
				'diff' => array_reverse($theDiff),
				'delimiter' => $aDelimiter,
		);
	}

	/**
	 * Convert a given two dimensional array into a csv.
	 * @param array $aInput - array to convert, returns FALSE if this is empty.
	 * @param array $aHeaderRow - (optional) single dimension array of header
	 *   values or TRUE to grab 1st row keys.
	 * @param mixed $aStream - (optional) output directed to a stream,
	 *   else a string is returned.
	 * @param string $aDelimiter - (optional) defaults to ",", but it can be
	 *   <span style="font-family:monospace;">TAB</span> or whatever.
	 * @param callable[] $aCallbacks - (optional) callback functions keyed by
	 *   column names for alternate output; Callbacks are of the form
	 *   <span style="font-family:monospace;">myCallback($col, $row)</span>.
	 * @return string - Returns a string if no output stream is given,
	 *   else <span style="font-family:monospace;">!empty($aInput)</span>.
	 * @see StackOverflow.com http://stackoverflow.com/a/21858025
	 * @see OutputToCSV.
	 */
	static public function array_to_csv_string(array &$aInput, $aHeaderRow=null,
			$aStream=null, $aDelimiter=',', $aCallbacks)
	{
		if (empty($aInput))
			return false;
		// using concatenation since it is faster than fputcsv, and file size is smaller
		$csv = '';
		if (!empty($aHeaderRow)) {
			if (is_array($aHeaderRow))
				$csv .= '"'.implode("\"{$aDelimiter}\"", $aHeaderRow).'"'.PHP_EOL;
			else
				$csv .= '"'.implode("\"{$aDelimiter}\"", array_keys($aInput[0])).'"'.PHP_EOL;
			if ($aStream) {
				$aStream.put($csv);
				$csv = '';
			}
		}
		foreach ($aInput as $theRow) {
			foreach ($theRow as $theColName => $theColValue) {
				if (!empty($aCallbacks[$theColName])) {
					$theColValue = $aCallbacks[$theColName]($theColValue, $theRow);
				}
				$csv .= '"'.$theColValue.'"'.$aDelimiter;
			}
		    $csv .= PHP_EOL;
		    if ($aStream) {
				$aStream.put($csv);
				$csv = '';
			}
		}
		if ($aStream) {
			return true;
		} else {
			return $csv;
		}
	}
	
	/**
	 * Convert an indexed array of 'key="value"' pairs of values into
	 * an associative array where key => value.
	 * @param array $aPairs - numerically indexed array.
	 * @param string $aDelimiter - (optional) key=value delimiter (defaults to '=').
	 * @param string $aEnclosure - (optional) value may be enclosed (defaults to '"'),
	 * set as NULL to keep the encloser intact.
	 */
	static public function cnvKeyValuePairsToAssociativeArray($aPairs, $aDelimiter='=', $aEnclosure='"') {
		$theResult = array();
		if (!empty($aPairs)) {
			foreach ($aPairs as $theKeyValPair) {
				list($theKey, $theValue) = Strings::strToKeyValue($theKeyValPair);
				$theResult[$theKey] = Strings::stripEnclosure($theValue, $aEnclosure);
			}
		}
		return $theResult;
	}
	
	/**
	 * Parsing a CSV file into a two-dimensional array seems as simple as
	 * splitting a string by lines and commas, but this only works if tricks
	 * are performed to ensure that you do NOT split on lines and commas that
	 * are inside of double quotes.
	 * @param string $aCsvString - the csv string to parse.
	 * @param string $aDelimiter - (optional) delimiter string, defaults to ','.
	 * @param string $aEnclosure - (optional) enclosure string, defaults to '"'.
	 * @return array Returns a two-dimensional array split on LF and $aDelimiter.
	 * @see http://php.net/manual/en/function.str-getcsv.php#113220
	 */
	static public function parse_csv_to_array($aCsvString, $aDelimiter=',', $aEnclosure='"') {
		//Strings::debugLog(__METHOD__.' str='.Strings::debugStr($aCsvString));
		if (empty($aCsvString))
			return array();
		
		//anything inside the quotes that might be used to split the string into lines and fields later,
		//  needs to be quoted. The only character we can guarantee as safe to use, because it will never
		//  appear in the unquoted text, is a CR. So we're going to use CR as a marker to make
		//  escape sequences for CR, LF, Quotes, and Commas.
		$theTokens = array(
				'to_replace' => array("\r","\n",$aEnclosure,$aDelimiter),
				'replace_with' => array("\rR", "\rN", "\rE", "\rD"),
				'delimiter' => $aDelimiter,
				'enclosure' => $aEnclosure,
		);

		//match all the non-quoted text and one series of quoted text (or the end of the string)
		//each group of matches will be parsed with the callback, with $matches[1] containing all the non-quoted text,
		//and $matches[3] containing everything inside the quotes
		$theCsvRegex = '/([^'.$aEnclosure.']*)('.$aEnclosure.'(('.$aEnclosure.''.$aEnclosure.'|[^'.$aEnclosure.'])*)'.$aEnclosure.'|$)/s';
		//Strings::debugLog(__METHOD__.' regex='.Strings::debugStr($theCsvRegex));

		$theCsvToParse = preg_replace_callback($theCsvRegex, function($aMatches) use ($theTokens) {
				if (!empty($aMatches) && !empty($aMatches[0])) {
					//Strings::debugLog(__METHOD__.' matches='.Strings::debugStr($aMatches));
					//The unquoted text is where commas and newlines are allowed, and where the splits will happen
					//  We're going to remove all CRs from the unquoted text, by normalizing all line endings to just LF
					//  This ensures us that the only place CR is used, is inside quoted text as escape sequences.
					$theResult = preg_replace('/\r\n?/', "\n", $aMatches[1]);
					if (!empty($aMatches[3]))
						$theResult .= str_replace($theTokens['to_replace'], $theTokens['replace_with'], $aMatches[3]);
					return $theResult;
				}
		}, $aCsvString);
				
		//remove the very last newline to prevent a 0-field array for the last line
		$theCsvToParse = preg_replace('/\n$/', '', $theCsvToParse);
	
		//split on LF and parse each line with a callback
		return array_map(function($aCsvLine) use ($theTokens) {
				return array_map(function($aCsvField) use ($theTokens) {
						//restore any "csv-special" characters that are part of the data
						return str_replace($theTokens['replace_with'], $theTokens['to_replace'], $aCsvField);
				}, explode($theTokens['delimiter'], $aCsvLine));
		}, explode("\n", $theCsvToParse));
	}
	
	static public function removeValue(&$anArray, $aValue) {
		foreach ($anArray as $theKey => &$theValue) {
			if ($aValue===$theValue) {
				unset($anArray[$theKey]);
			}
		}
	}
	
	/**
	 * Convert a CSV string of params into a true associative array.
	 * e.g. '"param1"="value1","param2"="value2"\n"param3"="value3"' becomes
	 *     [ [param1 => value1, param2 => value2], [param3 => value3] ]
	 * @param string $aCsvString - the string to parse
	 * @return array Returns an array of associative arrays of key => values.
	 */
	static public function parseCsvParamsStringToArray($aCsvString) {
		$theResult = array();
		//safely convert the string into array of key=value strings
		$theParamsStrList = self::parse_csv_to_array($aCsvString);
		if (!empty($theParamsStrList)) {
			foreach ($theParamsStrList as $theParamsStr) {
				//convert the key=value strs into a true associative array
				$theResult[] = self::cnvKeyValuePairsToAssociativeArray($theParamsStr);
			}
		}
		return $theResult;
	}
	
	/**
	 * Retrieve the publicly accessible properties of an object as an array.
	 * @param object $aObj - the object to read.
	 * @return string[] Returns the string array of public property names.
	 */
	static public function getPublicPropertiesOfObject( $aObj )
	{
		return get_object_vars($aObj);
	}
	
	/**
	 * Retrieve the publicly accessible properties of a class as an array.
	 * @param class|string $aClass - the class to read.
	 * @return string[] Returns the string array of public property names.
	 */
	static public function getPublicPropertiesOfClass( $aClass )
	{
		return array_keys(get_class_vars($aClass));
	}
	
	
}//end class

}//end namespace
