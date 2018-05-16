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
	 * Ensure parent folders exist given the destination file path.
	 * @param string $aDestFilePath - the destination file path.
	 * @param int $aMode - (optional) the mode assigned to the created folder;
	 * For more information on modes, read the details on the chmod page.
	 * @return boolean Return TRUE if folders exist.
	 */
	static public function ensureFoldersExist($aDestFilePath, $aMode=0755) {
		$theParentPath = dirname($aDestFilePath);
		if (!is_dir($theParentPath))
			mkdir($theParentPath, $aMode, true);
		return is_dir($theParentPath);
	}
	
	/**
	 * Similar to file_put_contents, but forces all parts of the folder path to exist first.
	 * @param string $aDestFile - path and filename of destination.
	 * @param string $aFileContents - contents to be saved in $aDestFile.
	 * @return int|boolean Returns false on failure, else num bytes stored.
	 */
	static public function file_force_contents($aDestFile, $aData, $aDataSize, $mode=0755, $flags=0) {
		try {
			self::ensureFoldersExist($aDestFile);
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
	 * @return int|boolean Returns false on failure, else num bytes stored.
	 * @throws \Exception on failure.
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
	 * @param resource $aFileStream - the file stream instance.
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
					Strings::errorLog(__METHOD__.' fwrite fail. wrote: '.$num_wrote.
							' duration: '.number_format($tl-$ts).' text: '.$aText);
				}
			}
		}
		return $num_wrote;
	}
	
	/**
	 * Delete a file.
	 * @param string $aFilePath - full path of file to delete.
	 * @return boolean Returns TRUE if file does not exist anymore.
	 */
	static public function deleteFile($aFilePath) {
		if (file_exists($aFilePath)) {
			return unlink($aFilePath);
		} else {
			return true;
		}
	}
	
	/**
	 * Appends a path segment onto an existing path which may or may not have
	 * a directory separator already.
	 * @param string $aExistingPath - the existing path string.
	 * @param string $aPathSegment - the path segment to append.
	 * @return string Returns the existing path with the segment appended.
	 */
	static public function appendPath($aExistingPath, $aPathSegment) {
		return rtrim($aExistingPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($aPathSegment, DIRECTORY_SEPARATOR);
	}
	
	/**
	 * Indicates whether a row returned by the standard PHP function
	 * <code>fgetcsv()</code> could be considered "empty". This includes the
	 * following four return values:
	 * <ul>
	 * <li><code>null</code></li>
	 * <li>an empty array/object</li>
	 * <li><code>false</code> (returned under certain error conditions)</li>
	 * <li>an array containing a single <code>null</code> element</li>
	 * </ul>
	 * Any of these four distinct return values might have special significance
	 * individually, but all indicate that the line is generally unusable/empty
	 * and should be skipped by any looping algorithm that is processing all
	 * lines of a CSV file.
	 * @param mixed $aInput the value returned by a call to
	 *  <code>fgetcsv()</code>
	 * @return boolean <code>true</code> if the return value can be considered
	 *  "empty" in terms of CSV input
	 * @link http://www.php.net/manual/en/function.fgetcsv.php
	 */
	static public function isEmptyCSV( $aInput )
	{
		return( $aInput == null || empty($aInput) || $aInput === false
			 || $aInput == array(null) ) ;
	}
	
	/**
	 * Get the size of a file, platform- and architecture-independant.
	 * This function supports 32bit and 64bit architectures and works with large files > 2 GB.
	 * The return value type depends on platform/architecture:
	 *   (float) when PHP_INT_SIZE < 8 or (int) otherwise
	 * @param resource $aFileStream - the file stream used to calculate the file size.
	 * @return number|boolean Returns the file size on success (float|int) or FALSE
	 *   on error (bool).
	 * @link http://php.net/manual/en/function.filesize.php#115792
	 */
	static public function getFileSizeOfStream( $aFileStream )
	{
		if ( !is_resource($aFileStream) )
		{ throw new \InvalidArgumentException( 'aFileStream is not a resource' ); }
		//save off the current pointer position so we can reset it later
		$theCurPos = ftell($aFileStream);
		$theResult = false;
		if ( PHP_INT_SIZE<8 )
		{ // 32bit
			if ( 0 === fseek($aFileStream, 0, SEEK_END) )
			{
				$theResult = 0.0;
				$theStep = 0x7FFFFFFF; //max signed 32bit INT
				while ( $theStep > 0 ) {
					if ( 0 === fseek($aFileStream, -$theStep, SEEK_CUR) )
					{ //success! add step's value to size
						$theResult += floatval($theStep);
					}
					else
					{ //failure! reduce step's size by half (shift will /2)
						$theStep >>= 1;
					}
				}
			}
		}
		else
		{ //64bit
			if ( 0 === fseek($aFileStream, 0, SEEK_END) )
				$theResult = ftell($aFileStream);
		}
		if ( $theCurPos != ftell($aFileStream) )
			fseek($aFileStream, 0, $theCurPos);
		return $theResult;
	}
	
	/**
	 * Calculate the file size of the given file safely whether using 32/64 bit system.
	 * @param string $aFilePath - the full path to the file.
	 * @return number|boolean - a numeric value if successful, FALSE if not.
	 * @see FileUtils::getFileSizeOfStream()
	 */
	static public function getFileSize( $aFilePath )
	{
		if ( empty($aFilePath) )
			return 0;
		$theFileStream = fopen( $aFilePath, 'r' );
		if ( !empty($theFileStream) )
		{
			$theFinalEnclosure = new FinallyBlock(function($aStream) {
				fclose( $aStream );
			}, $theFileStream);
			return self::getFileSizeOfStream( $theFileStream );
		}
	}
	
}//end class FileUtils

}//end namespace com\blackmoonit
