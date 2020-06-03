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
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\IDirected;
use BitsTheater\BrokenLeg;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * cURL functions are pretty basic, use this class to help build cURL requests.
 */
class CurlRequestBuilder extends BaseCostume
{
	/** @var resource The actual cURL request, once created. */
	public $mRequest = null;
	/** @var string The URL to poke. */
	public $mURL = null;
	/** @var string The HTTP request method to use. */
	public $mRequestMethod = 'GET';
	/** @var string[] The headers to be sent. */
	public $mHeaders = array();
	/** @var mixed The POST data to sent. */
	public $mData = null;
	/** @var string The User Agent to send along with the request. */
	public $mUserAgent = null;
	/**
	 * @var string The response Encoding option.
	 * NOTE: gzip will auto-inflate the response.
	 */
	public $mAcceptEncoding = null;
	/** @var boolean A response is expected from the request. */
	public $bExpectResponse = true;
	/** @var number A timeout to use, in seconds. */
	public $mTimeout = 45;
	/** @var boolean Follow redirects (up to a max) if server says to do so. */
	public $bFollowLocationRedirects = true;
	/** @var boolean Flag to let us know if an open request is not closed yet. */
	public $bRequestNotClosed = false;
	/** @var object The typical useful cURL information after execution. */
	public $mCurlInfo = null;
	/** @var number The HTTP Response code returned to us. */
	public $mHttpCode = 0;
	/** @var number The output buffer size, in bytes. 0 means no buffer. */
	public $mBufferSize = 0;
	/** @var callable The callback function to use instead of returning a resource. */
	public $mOutputRoutine = null;
	/** @var boolean If TRUE, then response headers will be part of the execute() response. */
	public $bIncludeHeadersInResponse = false;
	/** @var callable The callback function to use when a response header is received. */
	public $mOutputHeadersRoutine = null;
	/** @var string[] The raw response headers received array. */
	public $mResponseHeaders = array();
	
	/**
	 * Close our open request, if we still have one open.
	 */
	public function __destruct()
	{
		$this->closeRequest();
		if ( is_callable('parent::__destruct()') )
		{ parent::__destruct(); }
	}
	
	/**
	 * Initialize the new object with a URL and context to use.
	 * @param IDirected $aContext - the context to use.
	 * @param string $aBaseURL - the base URL that must exist.
	 * @param string $aAction - (OPTIONAL) the extra URL path.
	 * @return $this Returns the created object.
	 * @throws BrokenLeg::ACT_SERVICE_UNAVAILABLE if $aURL is empty.
	 */
	static public function withURL( IDirected $aContext, $aBaseURL, $aAction=null )
	{
		//$aContext->getDirector()->logStuff(__METHOD__, ' curl=', $aBaseURL);//debug
		$theClassName = get_called_class();
		$o = new $theClassName($aContext->getDirector());
		$o->setURL(trim($aBaseURL));
		$aAction = ltrim(trim($aAction), '/');
		if ( !empty($aAction) )
		{ $o->setURL(rtrim($o->mURL, '/') .'/'. $aAction); }
		return $o;
	}
	
	/**
	 * Sets the URL we will be sending a request to.
	 * @param string $aURL - the URL to send a request to.
	 * @return $this Returns $this for chaining.
	 * @throws BrokenLeg::ACT_SERVICE_UNAVAILABLE if $aURL is empty.
	 */
	public function setURL( $aURL )
	{
		if ( empty($aURL) )
			throw BrokenLeg::toss( $this, BrokenLeg::ACT_SERVICE_UNAVAILABLE ) ;
		$this->mURL = $aURL;
		return $this;
	}
	
	/**
	 * Sets the Request Method to use.
	 * @param string $aReqMethod - the type of request.
	 * @return $this Returns $this for chaining.
	 */
	public function setRequestMethod( $aReqMethod )
	{
		if ( !empty($aReqMethod) )
		{ $this->mRequestMethod = $aReqMethod; }
		return $this;
	}
	
	/**
	 * Set the data to use for the request.
	 * @param mixed $aData - the data to use for the request.
	 * @param string $aReqMethod - (OPTIONAL) the type of request, default is 'POST'.
	 * @return $this Returns $this for chaining.
	 */
	public function setData( $aData, $aReqMethod='POST' )
	{
		if ( isset($aData) ) {
			$this->mData = $aData;
			$this->setRequestMethod($aReqMethod);
		}
		return $this;
	}
	
	/**
	 * Encode and set the data to use for the request as JSON, adding the
	 * appropriate headers.
	 * @param mixed $aData - the data to use for the request.
	 * @param string $aReqMethod - (OPTIONAL) the type of request, default is 'POST'.
	 * @return $this Returns $this for chaining.
	 */
	public function encodeDataAsJSON( $aData, $aReqMethod='POST' )
	{
		if ( isset($aData) ) {
			$theEncodedData = json_encode($aData);
			$this->setData($theEncodedData, $aReqMethod)
				->addHeaderNameAndValue('Content-Type', 'application/json')
				->addHeaderNameAndValue('Content-Length', strlen($theEncodedData))
				;
		}
		return $this;
	}
	
	/**
	 * Get list of headers currently defined to be sent with the request.
	 * @return string[] Return the array of headers.
	 */
	public function getHeaders()
	{ return $this->mHeaders; }
	
	/**
	 * Add a header to our list of request headers to send.
	 * @param string $aHeader - header to send.
	 * @return $this Returns $this for chaining.
	 */
	public function addHeader( $aHeader )
	{
		if ( !empty($aHeader) )
		{ $this->mHeaders[] = $aHeader; }
		return $this;
	}
	
	/**
	 * Add a properly formatted headername and value to our list of request
	 * headers to send.
	 * @param string $aHeaderName - header name to send.
	 * @param string $aHeaderValue - the value of the header after the name.
	 * @return $this Returns $this for chaining.
	 */
	public function addHeaderNameAndValue( $aHeaderName, $aHeaderValue )
	{
		$theHeaderName = Strings::normalizeHttpHeaderName($aHeaderName);
		$theHeaderValue = trim($aHeaderValue);
		if ( !empty($theHeaderName) )
		{ $this->mHeaders[] = $theHeaderName . ': ' . $theHeaderValue; }
		return $this;
	}
	
	/**
	 * Add a list of headers to our list of request headers to send.
	 * @param string[] $aListOfHeaders - headers to send.
	 * @return $this Returns $this for chaining.
	 */
	public function addHeaders( $aListOfHeaders )
	{
		if ( !empty($aListOfHeaders) )
		{ $this->mHeaders = array_merge($this->mHeaders, $aListOfHeaders); }
		return $this;
	}
	
	/**
	 * Set the list of headers to be sent with the request.
	 * @param string[] $aListOfHeaders - the array of headers to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setHeaders( $aListOfHeaders )
	{
		$this->mHeaders = $aListOfHeaders;
		return $this;
	}
	
	/**
	 * Get the timeout for the request.
	 * @return number Returns the timeout value for the request.
	 */
	public function getTimeout()
	{ return $this->mTimeout; }
	
	/**
	 * Set the timeout for the request.
	 * @param number $aTimeout - the timeout value for the request.
	 * @return $this Returns $this for chaining.
	 */
	public function setTimeout( $aTimeout )
	{
		$this->mTimeout = $aTimeout;
		return $this;
	}
	
	/**
	 * Set the User Agent for the request.
	 * @param string $aUserAgent - the User Agent string to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setUserAgent( $aUserAgent )
	{
		if ( !empty($aUserAgent) )
		{ $this->mUserAgent = $aUserAgent; }
		return $this;
	}
	
	/**
	 * Set the User Agent for the request.
	 * @param string $aUserAgent - the User Agent string to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setAcceptEncoding( $aEncodings )
	{
		if ( !empty($aEncodings) )
		{ $this->mAcceptEncoding = $aEncodings; }
		return $this;
	}
	
	/**
	 * Set the buffer size for the response.
	 * @param number $aBufferSize - the byte size of the output buffer to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setBufferSize( $aBufferSize )
	{
		$this->mBufferSize = $aBufferSize;
		return $this;
	}
	
	/**
	 * Return large content in chunks to the client browser so we avoid hitting
	 * the PHP response limitations.
	 * @param resource $aRequest - the cURL resource object;
	 *   equivalent to $this->mRequest.
	 * @param string $aContentChunk - the chunk of data to send to output.
	 * @return number Return the size of the data sent to output.
	 */
	public function outputContentHandler( $aRequest, $aContentChunk )
	{
		/* DEBUG
		$this->logStuff(__METHOD__
				,' for: .../', basename($this->mURL)
				,' size=' . strlen($aContentChunk)
				//,' stuff=', substr($aContentChunk,0,100)
		);
		*/
		print($aContentChunk);
		return strlen($aContentChunk);
	}

	/**
	 * Set the output routine to use rather than return a resource.
	 * @param callable $aCallback - the callback routine to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setOutputRoutine( $aCallback )
	{
		$this->mOutputRoutine = $aCallback;
		return $this;
	}
	
	/**
	 * Set the output routine to either the passed in callback or our built-in
	 * outputContent() method if not supplied rather than return a resource.
	 * @param callable $aCallback - (OPTIONAL) the callback routine to use.
	 * @return $this Returns $this for chaining.
	 */
	public function useBufferedOutput( $aCallback=null )
	{
		$this->setBufferSize(655360*10); //6400k buffer
		if ( !empty($aCallback) ) {
			$this->setOutputRoutine($aCallback);
		}
		else {
			$this->setOutputRoutine(array($this, 'outputContentHandler'));
		}
		return $this;
	}
	
	/**
	 * If set to TRUE, headers will be included in the execute() response.
	 * Defaults to FALSE.
	 * @param boolean $aValue - if TRUE, execute() will include response headers.
	 * @return $this Returns $this for chaining.
	 */
	public function setIncludeHeadersInResponse( $aValue )
	{
		$this->bIncludeHeadersInResponse = $aValue;
		return $this;
	}
	
	/**
	 * Set the output header routine to use when we receive a response header.
	 * @param callable $aCallback - the callback routine to use.
	 * @return $this Returns $this for chaining.
	 */
	public function setOutputHeaderRoutine( $aCallback )
	{
		$this->mOutputHeadersRoutine = $aCallback;
		return $this;
	}
	
	/**
	 * When we receive a header from the response, record it and possibly
	 * call a header routine callback.
	 * @param resource $aReq - the cURL resource.
	 * @param string $aHeaderLine - the header we received.
	 * @return number We MUST return the number of bytes "written" (header size).
	 */
	public function onResponseHeader( $aReq, $aHeaderLine )
	{
		$theHeader = trim($aHeaderLine);
		if ( !empty($theHeader) ) {
			$this->mResponseHeaders[] = $theHeader;
			if ( !empty($this->mOutputHeadersRoutine) )
			{
				call_user_func_array($this->mOutputHeadersRoutine,
						array($aReq, $theHeader)
				);
			}
		}
		return strlen($aHeaderLine);
	}
	
	/**
	 * Create the cURL request and set common options.
	 * @return $this Returns $this for chaining.
	 */
	public function initRequest()
	{
		$this->mRequest = curl_init($this->mURL);
		if ( $this->bExpectResponse )
		{ curl_setopt($this->mRequest, CURLOPT_RETURNTRANSFER, true); }
		if ( !empty($this->mHeaders) )
		{ curl_setopt($this->mRequest, CURLOPT_HTTPHEADER, $this->mHeaders); }
		if ( isset($this->mData) )
		{ curl_setopt($this->mRequest, CURLOPT_POSTFIELDS, $this->mData); }
		if ( !empty($this->mRequestMethod) )
		{ curl_setopt($this->mRequest, CURLOPT_CUSTOMREQUEST, $this->mRequestMethod); }
		curl_setopt($this->mRequest, CURLOPT_TIMEOUT, $this->mTimeout);
		curl_setopt($this->mRequest, CURLOPT_FOLLOWLOCATION, $this->bFollowLocationRedirects);
		if ( !empty($this->mUserAgent) )
		{ curl_setopt($this->mRequest, CURLOPT_USERAGENT, $this->mUserAgent); }
		if ( !empty($this->mAcceptEncoding) )
		{
			curl_setopt($this->mRequest, CURLOPT_ENCODING, $this->mAcceptEncoding);
			//have not figured out how to handle "chuncked" transfer-encoding, yet
			curl_setopt($this->mRequest, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		}
		if ( $this->mBufferSize>0 )
		{ curl_setopt($this->mRequest, CURLOPT_BUFFERSIZE, $this->mBufferSize); }
		if ( !empty($this->mOutputRoutine) )
		{ curl_setopt($this->mRequest, CURLOPT_WRITEFUNCTION, $this->mOutputRoutine); }
		curl_setopt($this->mRequest, CURLOPT_HEADER, $this->bIncludeHeadersInResponse);
		curl_setopt($this->mRequest, CURLOPT_HEADERFUNCTION, array($this, 'onResponseHeader'));
		return $this;
	}
	
	/**
	 * Create the cURL request and return it.
	 * @return resource Returns the created cURL request.
	 */
	public function createRequest()
	{
		return $this->initRequest()->mRequest;
	}
	
	/**
	 * Set a cURL option for our request.
	 * @param int $aOptConst - the CURLOPT_* constant.
	 * @param mixed $aOptValue - the value of the option.
	 * @return $this Returns $this for chaining.
	 */
	public function setOption( $aOptConst, $aOptValue )
	{
		curl_setopt($this->mRequest, $aOptConst, $aOptValue);
		return $this;
	}
	
	/**
	 * Create the cURL request and return it.
	 * @return resource Returns the executed cURL request.
	 */
	public function execute()
	{
		if ( empty($this->mRequest) )
		{ $this->initRequest(); }
		$this->bRequestNotClosed = true;
		$theResponse = curl_exec($this->mRequest);
		$this->mCurlInfo = $this->getInfo();
		if ( !empty($this->mCurlInfo) ) {
			$this->mHttpCode = $this->mCurlInfo->http_code;
		}
		return $theResponse;
	}
	
	/**
	 * Get the HTTP Response Code returned after execution.
	 * @return number The HTTP response code returned.
	 */
	public function getHttpCode()
	{ return $this->mHttpCode; }
	
	/**
	 * Get the HTTP Response Code returned after execution.
	 * @return number The HTTP response code returned.
	 */
	public function getResponseCode()
	{ return $this->getHttpCode(); }
	
	/**
	 * Determine if HTTP Code is in the 200 range.
	 * @return boolean Returns TRUE if HTTP Code was in 200-299 range.
	 */
	public function isResponseSuccessful()
	{ return ($this->mHttpCode>=200 && $this->mHttpCode<300); }
	
	/**
	 * Get information regarding our request.
	 * @param number $opt - the options for info requested.
	 * @return object Returns our cURL information.
	 * @see http://php.net/manual/en/function.curl-getinfo.php
	 */
	public function getInfo($opt=null)
	{
		if ( !empty($this->mRequest) && $this->bRequestNotClosed ) {
			if ( isset($opt) )
			{ return curl_getinfo($this->mRequest, $opt); }
			else
			{ return (object)curl_getinfo($this->mRequest); }
		}
		else {
			return false;
		}
	}
	
	/**
	 * Close our request if it is still open.
	 * @return $this Return $this for chaining.
	 */
	public function closeRequest()
	{
		if ( !empty($this->mRequest) && $this->bRequestNotClosed ) {
			curl_close($this->mRequest);
			$this->bRequestNotClosed = false;
			$this->mRequest = null;
		}
		return $this;
	}
	
	/**
	 * Log helper method.
	 * @param mixed $aResponseData - (OPTIONAL) response data to log.
	 */
	public function getLogLines($aResponseData=null)
	{
		return array(
			'[1/2] - ' . $this->mRequestMethod . ' ' .
				'URL [' . $this->mURL, ']; req headers=[' .
				$this->debugStr($this->mHeaders) .
				']; request data=' .
				$this->debugStr($this->mData)
			,
			'[2/2] - ' .
				'HTTP code [' . $this->mHttpCode . ']' .
				'; response data=', substr($aResponseData, 0, 1000)
			,
		);
	}
	
	/**
	 * Debug log helper method.
	 * @param mixed $aResponseData - (OPTIONAL) response data to log.
	 */
	public function logReqDebug($aResponseData=null)
	{
		$theLines = $this->getLogLines($aResponseData);
		foreach($theLines as $theLine) {
			$this->logStuff(__METHOD__, ' ', $theLine);
		}
	}
	
	/**
	 * Error log helper method.
	 * @param mixed $aResponseData - (OPTIONAL) response data to log.
	 */
	public function logReqError($aResponseData=null)
	{
		$theLines = $this->getLogLines($aResponseData);
		foreach($theLines as $theLine) {
			$this->logErrors(__METHOD__, ' ', $theLine);
		}
	}
	
	/**
	 * If the webserver detects that gzip compression is acceptable to the
	 * browser and the webserver supports gzip compression, the output
	 * will be sent compressed and then sent to the browser.
	 * @param mixed $aContent - the content to be sent to the browser.
	 */
	static public function outputContent( &$aContent, $bTryCompression=false )
	{
		if ( headers_sent() ) {
			throw new \Exception('Cannot send Content-Length header; headers already sent.');
		}
		//outer buffer our output so we can create the Content-Length header.
		ob_start();
		//inner buffer our output to enable possible deflation with gzip.
		if ( !$bTryCompression || !ob_start('ob_gzhandler') ) {
			ob_start();
		}
		//output our content.
		print($aContent);
		//flush the inner output so we can get the size of it.
		ob_end_flush();
		//grab the possibly compressed size of our output
		$theLen = ob_get_length();
		//$this->logStuff(__METHOD__, ' slen='.strlen($aContent).' oblen='.$theLen); //DEBUG
		//send the Content-Length header
		header(Strings::createHttpHeader('Content-Length', $theLen));
		//flush the outer output buffer
		ob_end_flush();
	}
	
}//end class
	
}//end namespace
