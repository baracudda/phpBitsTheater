<?php
/*
 * Copyright (C) 2020 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe ;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use com\blackmoonit\Strings;
use BitsTheater\BrokenLeg;
use BitsTheater\outtakes\FileIOException;
use BitsTheater\costumes\IDirected;
use com\blackmoonit\FileUtils;
use BitsTheater\Scene;
{//namespace begin

/**
 * Manage a config file either found in the MMR folder area
 * (via Docker volume mount, usually) or found via http(s) link.
 * Schemes supported: <ol>
 *   <li>local://</li>
 *   <li>mmr://</li>
 *   <li>http(s)://</li>
 * </ol>
 * @since BitsTheater [NEXT]
 */
class ManagedConfig extends BaseCostume
{
	/** @var string[] The schemes to try if not specified. */
	static public $SCHEMES_TO_TRY = array('local', 'mmr');
	/** @var string The URI of the managed file. */
	public $mConfigURI;
	/** @var boolean Auto-JSON-decode if TRUE (default TRUE). */
	public $bDecodeJSON = true;
	/** @var string[] The patterns/tokens to replace. */
	public $mReplacementPatternList = null;
	/** @var string[] The values used as replacements. */
	public $mReplacementValueList = null;
	/** @var boolean Determines Regex or Simple replacements. */
	public $bUseRegexReplacements = false;
	/** @var string The MIME type of the file. Default: 'application/octet-stream'. */
	public $mime_type = 'application/octet-stream';
	/** @var string The download content disposition. Default: 'inline'. */
	public $mContentDisposition = 'inline';
	/** @var string The filename to use for the downloaded content. */
	public $filename;
	/** @var int Size of the file contents. */
	public $mFileSize;
	
	/**
	 * Get the config URI representing the file to be used.
	 * @return string Returns the config URI to use.
	 */
	public function getConfigURI()
	{ return $this->mConfigURI; }
	
	/**
	 * Set the URI representing the file to be used.
	 * @param string $aConfigURI - the URI to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setConfigURI( $aConfigURI )
	{ $this->mConfigURI = $aConfigURI; return $this; }
	
	/**
	 * Should the content be JSON decoded before being returned as content?
	 * @return boolean Returns TRUE if content should be decoded first.
	 */
	public function isDecodeJSON()
	{ return $this->bDecodeJSON; }
	
	/**
	 * Sets whether content should automatically be JSON decoded before
	 * being returned.
	 * @param boolean $aVal - TRUE if we shoudl automatically JSON decode.
	 * @return $this Returns $this for chaining.
	 */
	public function setDecodeJSON( $aVal )
	{ $this->bDecodeJSON = $aVal; return $this; }
	
	/**
	 * Sets the MIME type for the content in case printOutput() is used.
	 * @param string $aMimeType - the MIME type string.
	 * @return $this Returns $this for chaining.
	 */
	public function setMimeType( $aMimeType )
	{ $this->mime_type = $aMimeType; return $this; }
	
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
	 * Create an instance for use.
	 * @param IDirected $aContext - the context to use.
	 * @return $this Returns a new object for use.
	 */
	static public function getInstance( IDirected $aContext, $aURI )
	{
		$theClass = get_called_class();
		return (new $theClass($aContext))->setConfigURI($aURI);
	}
	
	/**
	 * Given a config setting namespace/key and a default value, return the file
	 * contents, if found.
	 * @param IDirected $aContext - the context to use.
	 * @param string $aConfigSetting - the namespace/key string for the file URI.
	 * @param string $aDefault - the default URI to use if setting is empty.
	 * @return $this Returns a new object for use utlizing the config setting as URI.
	 */
	static public function withConfigSetting( IDirected $aContext, $aConfigSetting, $aDefault )
	{
		$theURI = $aContext->getConfigSetting($aConfigSetting);
		if ( empty($theURI) ) {
			$theURI = $aDefault;
		}
		return static::getInstance($aContext, $theURI);
	}
	
	/**
	 * Check for file content in MMR path on server.
	 * @param string $aURIpath - the processed file path to use.
	 * @return string Returns the contents of the file, if found.
	 * @throws FileIOException if there is an error reading in the file.
	 */
	protected function getContentViaMMR( $aURIpath )
	{
		//ensure no leading separator
		if ( Strings::beginsWith($aURIpath, '/') ) {
			$aURIpath = substr($aURIpath, 1);
		}
		$theFilePath = str_replace('/', DIRECTORY_SEPARATOR, $aURIpath);
		//get root MMR path, ensure trailing separator.
		$theMMRpath = $this->getConfigSetting('site/mmr');
		if ( !Strings::endsWith($theMMRpath, DIRECTORY_SEPARATOR) ) {
			$theMMRpath .= DIRECTORY_SEPARATOR;
		}
		//if file exists, return contents, else toss error.
		if ( file_exists($theMMRpath.$theFilePath) ) {
			if ( empty($this->filename) ) $this->filename = basename($theFilePath);
			$theFileContents = file_get_contents($theMMRpath.$theFilePath);
			if ( !empty($theFileContents) ) {
				return $theFileContents;
			}
			else {
				throw FileIOException::toss($this, FileIOException::ACT_COULD_NOT_READ_FILE, $theFilePath);
			}
		}
	}
	
	/**
	 * Check for file content as a curl request.
	 * @param string $aURIpath - the processed file path to use.
	 * @return string Returns the contents of the file, if found.
	 * @throws FileIOException if there is an error reading in the file.
	 */
	protected function getContentViaHttp( $aURIpath )
	{
		$theFileContents = file_get_contents($aURIpath);
		if ( !empty($theFileContents) ) {
			if ( empty($this->filename) ) $this->filename = basename($aURIpath);
			return $theFileContents;
		}
		else {
			throw FileIOException::toss($this, FileIOException::ACT_COULD_NOT_READ_FILE, $aURIpath);
		}
	}
	
	/**
	 * Given the scheme, processed URI path as well as original conifg URI defined,
	 * try to get the contents of the file and return it.
	 * @param string $aConfigScheme - the scheme, 'local, 'mmr', etc.
	 * @param string $aURIpath - the processed file path to use.
	 * @return string Returns the file content.
	 */
	protected function handleConfigScheme( $aConfigScheme, $aURIpath )
	{
		switch ( $aConfigScheme ) {
			case 'local': {
				if ( file_exists(BITS_CONFIG_DIR . DIRECTORY_SEPARATOR . $aURIpath) ) {
					if ( empty($this->filename) ) $this->filename = basename($aURIpath);
					return file_get_contents(BITS_CONFIG_DIR . DIRECTORY_SEPARATOR . $aURIpath);
				}
				break;
			}
			case 'mmr': {
				return $this->getContentViaMMR($aURIpath);
			}
			case 'http':
			case 'https': {
				return $this->getContentViaHttp($this->getConfigURI());
			}
		}//switch
	}
	
	/**
	 * Given the patterns, replacements, and possibily zipped data, perform replacements.
	 * @param string $aData - the possibly gzip encoded haystack data.
	 * @return string Returns the possibly gzip encoded data back to the caller.
	 */
	protected function handleReplacements( &$aData )
	{
		if ( empty($this->mReplacementPatternList) )
		{ return $aData; }
		if ( $this->bUseRegexReplacements ) {
			return preg_replace($this->mReplacementPatternList,
					$this->mReplacementValueList, $aData
			);
		}
		else {
			return str_replace($this->mReplacementPatternList,
					$this->mReplacementValueList, $aData
			);
		}
	}
	
	/**
	 * Once the file contents have been retrieved, see if we need to
	 * process them at all. e.g. JSON decode them and return said object.
	 * @param string $aFileContents - the non-empty file contents as string.
	 * @return string|object Returns the file contents as-is, or processed into
	 *   any number of results.
	 */
	protected function processFileContents( $aFileContents )
	{
		if ( $this->isDecodeJSON() ) {
			$theContentsAsUTF8 = mb_convert_encoding($aFileContents, 'UTF-8',
					mb_detect_encoding($aFileContents, 'UTF-8, ISO-8859-1', true)
			);
			return json_decode($this->handleReplacements($theContentsAsUTF8));
		}
		else {
			return $this->handleReplacements($aFileContents);
		}
	}
	
	/**
	 * Return the contents of a config file.
	 * @return object|string Returns the contents of the file either as
	 *   an object or a string depending on 2nd parameter.
	 * @throws BrokenLeg if anything goes wrong.
	 */
	public function getContents()
	{
		try {
			$theScheme = strstr($this->getConfigURI(), '://', true);
			if ( !empty($theScheme) ) {
				$theSchemesToTry = array($theScheme);
				$theConfigURIPath = Strings::strstr_after($this->getConfigURI(), '://');
			} else {
				$theSchemesToTry = static::$SCHEMES_TO_TRY;
				$theConfigURIPath = $this->getConfigURI();
			}
			$theFileContents = null;
			foreach ( $theSchemesToTry as $theConfigScheme ) {
				$theFileContents = $this->handleConfigScheme($theConfigScheme, $theConfigURIPath);
				if ( !empty($theFileContents) ) {
					return $this->processFileContents($theFileContents);
				}
			}//foreach
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException($this, $x) ; }
	}
	
	/**
	 * Save the contents as a local temp file and return its filename.
	 * @return string Return the local temp filename.
	 * @throws BrokenLeg if a problem occurs.
	 */
	public function getContentsAsTempFilename()
	{
		try {
			$theContents = $this->getContents();
			if ( !empty($theContents) ) {
				$theTempFilename = FileUtils::getTempFileName();
				file_put_contents($theTempFilename, $theContents);
				return $theTempFilename;
			}
			else {
				throw BrokenLeg::toss($this, BrokenLeg::ACT_FILE_NOT_FOUND)
					->addExtraMsgForUI('Nothing found at ' . $this->getConfigURI())
					;
			}
		}
		catch ( \Exception $x ) {
			throw BrokenLeg::tossException($this, $x);
		}
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
			header(Strings::createHttpHeader('expires', '0'));
			header(Strings::createHttpHeader('content-type', $this->mime_type));

			$theHdr = $this->mContentDisposition;
			if ( !empty($this->filename) ) {
				$theHdr .= ';filename="' . $this->filename . '"';
			}
			header(Strings::createHttpHeader('content-disposition', $theHdr));
			
			if ( !empty($this->mFileSize) )
			{ header(Strings::createHttpHeader('content-length', $this->mFileSize)); }
		}
	}
	
	/**
	 * Render the file to our client.
	 * NOTE: throwing exceptions here not well handled, yet.
	 * @param Scene $v - the Scene being used for extra data parameters.
	 */
	public function printOutput( Scene $v )
	{
		$theTmpFilename = $this->getContentsAsTempFilename();
		$this->mFileSize = filesize($theTmpFilename);
		$this->headersToReturn();
		readfile($theTmpFilename);
	}

}//end class

}//end namespace
