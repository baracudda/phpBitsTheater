<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
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

class FileUtils {

	private function __construct() {} //do not instantiate

	/**
	 * Similar to file_put_contents, but forces all parts of the folder path to exist first.
	 * @param string $aDestFile - path and filename of destination.
	 * @param string $aFileContents - contents to be saved in $aDestFile.
	 * @return Returns false on failure, else num bytes stored.
	 */
	static public function file_force_contents($aDestFile, $aData, $aDataSize, $mode=0755, $flags=0) {
		$theFolders = dirname($aDestFile);
		if (!is_dir($theFolders)) {
			mkdir($theFolders, $mode, true);
		}
		try {
			return (file_put_contents($aDestFile, $aData, $flags)==$aDataSize);
		} catch (\Exception $e) {
			return false;
		}
	}
		
	/**
	 * Copy the contents of a file into another using template replacements, if defined.
	 * @param string $aSrcFilePath - template source.
	 * @param string $aDestFilePath - template destination.
	 * @param array $aReplacements - (optional) replacement name=>value inside the template.
	 * @throws Exception on failure.
	 */
	static public function copyFileContents($aSrcFilePath, $aDestFilePath, $aReplacements=array()) {
		$theSrcContents = file_get_contents($aSrcFilePath);
		if (!empty($theSrcContents)) {
			if (!empty($aReplacements)) {
				$theTokens = array();
				$theValues = array();
				foreach ($aReplacements as $theReplacementName => $theReplacementValue) {
					$theTokens[] = '%'.$theReplacementName.'%';
					$theValues[] = $theReplacementValue;
				}
				$theSrcContents = str_replace($theTokens, $theValues, $theSrcContents);
			}
			
			return self::file_force_contents($aDestFilePath, $theSrcContents, strlen($theSrcContents), 0755, LOCK_EX);
		}
		return false;
	}
	
	/**
	 * Writing to a network stream may end before the whole string is written.
	 * Return value of fwrite() may be checked. Windows quirk handled as well.
	 * @param file_stream $aFileStream - the file stream instance.
	 * @param string $aText - the string data to write out.
	 * @param number $aRetryCount - number of attempts before giving up.
	 * @return number - Returns the # of bytes written out.
	 */
	static public function fstream_write($aFileStream, $aText, $aRetryCount=3) {
		$ts = microtime(true);
		$num_queued = strlen($aText); //returns num bytes, which is what we want
		$num_wrote = 0;
		$num_retries = $aRetryCount + 0; // in case NULL is passed in
		$isWindows = (strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN');
		while (($num_queued > $num_wrote) && ($num_retries > 0)) {
			// handle Windows quirk since we are already going through all the trouble of while loop
			$theText = (!$isWindows) ? substr($aText, $num_wrote) : substr($aText, $num_wrote, 8100);
			// only care about warnings if on last retry
			$fwResult = ($num_retries > 1)
			? @fwrite($aFileStream, $theText, strlen($theText))
			: fwrite($aFileStream, $theText, strlen($theText));
			if (!empty($fwResult)) {
				$num_wrote += $fwResult;
			}
			else { //cover the case of FALSE and 0 being returned
				//0 result may mean the socket/connection was severed, prevent infinite loop
				$num_retries -= 1;
				if ($num_retries > 0) {
					sleep(1); //give the target time to recover
				}
				else {
					$tl = microtime(true);
					Strings::debugLog(__METHOD__.' wrote: '.$num_wrote.
					' duration: '.number_format($tl-$ts).' text: '.$aText);
				}
			}
		}
		return $num_wrote;
	}
	
	
}//end class

}//end namespace
