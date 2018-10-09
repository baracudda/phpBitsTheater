<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ABitsCostume as BaseCostume ;
use BitsTheater\Scene;
use com\blackmoonit\FileUtils;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Managed media file wrapper which could be static website managed files
 * or user uploaded managed files.
 * @since BitsTheater v4.1.0
 */
abstract class ManagedMediaFile extends BaseCostume
{
	/** @var string The path to the actual file contents. */
	protected $mManagedMediaFilePath = null;
	/**
	 * The filename of the media (no path info).
	 * This is what the file will be called when it is downloaded. It is not
	 * necessarily called this while stored by the server.
	 * @var string
	 */
	public $filename = null;
	/**
	 * The MIME type of the file: "category/type". e.g. "image/jpeg"
	 * @var string
	 */
	public $mime_type = null;
	/**
	 * Not stored by our metadata table, this is a "nice-to-have" value
	 * when downloading the file so clients can produce a progress bar.
	 * It is also set when we upload a file and represents how many bytes
	 * were uploaded.
	 * @var number
	 */
	public $mFileSize;
	
	/**
	 * @var string Response Content-Disposition header.
	 * W3C defaults to 'inline'. Setting this value to 'attachment' will
	 * indicate the file should be downloaded.
	 */
	public $mContentDisposition = null;
	/** @var boolean The media file is compressed. */
	public $bManagedMediaFileIsCompressed = false;
	/** @var boolean Compress the response data sent to client. */
	public $bCompressResponse = false;
	/** @var string[] The patterns/tokens to replace. */
	public $mReplacementPatternList = null;
	/** @var string[] The values used as replacements. */
	public $mReplacementValueList = null;
	/** @var boolean Determines Regex or Simple replacements. */
	public $bUseRegexReplacements = false;
	
	/**
	 * This might return a physical file path (relative to some root) or
	 * possibly an Amazon S3 key to use or the like. Useful in features that
	 * need to archive these files with some kind of standard path format.
	 * @return string Return the relative path to the media content.
	 */
	abstract public function getManagedMediaPath();
	
	/**
	 * Return the file path to be used.
	 * @return string Returns the full file path of the media.
	 */
	abstract public function getFile();
	
	/**
	 * Get the media as a stream.
	 * @return resource Returns the stream for use.
	 */
	abstract public function getMediaStream();
	
	/**
	 * Take a combined key=>value array and set the Pattern/Values lists.
	 * @param string[] $aReplacements - an associative array of pattern=>value.
	 * @return $this Returns $this for chaining.
	 */
	public function setReplacementListsFromKeysAndValues( $aReplacements )
	{
		if ( !empty($aReplacements) ) {
			$this->mReplacementPatternList = array();
			$this->mReplacementValueList = array();
			foreach ($aReplacements as $thePattern => $theValue) {
				$this->mReplacementPatternList[] = $thePattern;
				$this->mReplacementValueList[] = $theValue;
			}
		}
		return $this;
	}
	
	/**
	 * See if we can guess what the MIME type is for this file.
	 * @return string
	 */
	public function determineMimeType()
	{
		if ( Strings::endsWith($this->mManagedMediaFilePath, '.apk') )
		{ return 'application/vnd.android.package-archive'; }
		else
		{ return 'application/octet-stream'; }
	}
	
	/**
	 * Headers to send to the client.
	 * @param string $aDisposition - (optional) inline or attachment, defaults to attachment.
	 */
	protected function headersToReturn()
	{
		//if no headers are sent, send some
		if ( !headers_sent() )
		{
			header('Expires: 0');
			
			if ( empty($this->mime_type) )
			{ $this->mime_type = $this->determineMimeType(); }
			header('Content-Type: ' . $this->mime_type);
			
			if ( empty($this->mContentDisposition) )
			{$this->mContentDisposition = 'inline'; }
			$theHdr = 'Content-Disposition: ' . $this->mContentDisposition;
			if ( empty($this->filename) )
			{ $this->filename = basename($this->mManagedMediaFilePath); }
			$theHdr .= ';filename="' . $this->filename . '"';
			header($theHdr);
			
			if ( $this->bCompressResponse )
			{ header('Content-Encoding: gzip'); }
			
			if ( !empty($this->mFileSize) )
			{ header('Content-Length: ' . $this->mFileSize); }
		}
	}
	
	/**
	 * Render the file to our client.
	 * NOTE: throwing exceptions here not well handled, yet.
	 * @param Scene $v - the Scene being used for extra data parameters.
	 */
	public function printOutput( Scene $v )
	{
		$this->headersToReturn();
		readfile( $this->getFile() );
	}

	/**
	 * Decode the response if it was given to us as gzip encoding.
	 * @param string $aResponseContent - the response data.
	 * @return string Return the data uncompressed.
	 */
	protected function decodedSourceData( $aSourceContent )
	{
		if ( $this->bManagedMediaFileIsCompressed )
			return gzdecode( $aSourceContent );
		else
			return $aSourceContent;
	}
	
	/**
	 * Encode the response if it was given to us as gzip encoding.
	 * @param string $aResponseContent - the response data.
	 * @return string Return the data compressed.
	 */
	protected function encodedResponse( $aResponseContent )
	{
		if ( $this->bCompressResponse )
			return gzencode( $aResponseContent, 6 );
		else
			return $aResponseContent;
	}
	
	/**
	 * Given the patterns, replacements, and possibily zipped data, perform replacements.
	 * @param string $aData - the possibly gzip encoded haystack data.
	 * @return string Returns the possibly gzip encoded data back to the caller.
	 */
	protected function handleReplacements( $aData )
	{
		if ( empty($this->mReplacementPatternList) )
		{ return $aData; } //optimally, caller should check to avoid.
		$theData = $this->decodedSourceData( $aData );
		if ( $this->bUseRegexReplacements ) {
			$theNewData = preg_replace($this->mReplacementPatternList,
					$this->mReplacementValueList, $theData
			);
		}
		else {
			$theNewData = str_replace($this->mReplacementPatternList,
					$this->mReplacementValueList, $theData
			);
		}
		$theNewData = $this->handleAfterReplacements($theNewData);
		//$this->logStuff($theNewData); //DEBUG
		return $this->encodedResponse( $theNewData );
	}
	
	/**
	 * After content replacements have been made, is there any post-processing
	 * to perform? Do that here.
	 * @param string $aData - the post-processed data.
	 * @return string Returns the data back to the caller.
	 */
	protected function handleAfterReplacements( $aData )
	{
		if ( empty($this->mReplacementPatternList) )
		{ return $aData; } //optimally, caller should check to avoid.
		// Not yet implemented
		$theNewData = $aData;
		return $theNewData;
	}
	/**
	 * Copy the contents of a file into another using template replacements, if defined.
	 * @param string $aSrcFilePath - template source.
	 * @param string $aDestFilePath - template destination.
	 * @return int|boolean Returns false on failure, else num bytes stored.
	 * @throws \Exception on failure.
	 */
	public function copyFileContents($aSrcFilePath, $aDestFilePath) {
		$theSrcContents = file_get_contents($aSrcFilePath);
		if (!empty($theSrcContents)) {
			if ( !empty($this->mReplacementPatternList) )
			{ $theSrcContents = $this->handleReplacements($theSrcContents); }
			$this->mFileSize = strlen($theSrcContents);
			return FileUtils::file_force_contents($aDestFilePath,
					$theSrcContents, $this->mFileSize, 0755, LOCK_EX
			);
		}
		return false;
	}
	
}//end class

}//end namespace
