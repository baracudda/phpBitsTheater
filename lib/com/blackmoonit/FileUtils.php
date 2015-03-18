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
	static public function file_force_contents($aDestFile, $aFileContents, $mode=0755) {
		$theFolders = dirname($aDestFile);
		if (!is_dir($theFolders)) {
			mkdir($theFolders, $mode, true);
		}
		return file_put_contents($aDestFile, $aFileContents);
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
		if ($theSrcContents) {
			foreach ($aReplacements as $theReplacementName => $theReplacementValue) {
				$theSrcContents = str_replace('%'.$theReplacementName.'%', $theReplacementValue, $theSrcContents);
			}
			if (file_put_contents($aDestFilePath, $theSrcContents, LOCK_EX)===false) {
				return true;
			}
		}
		return false;
	}
	
	
}//end class

}//end namespace
