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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
use BitsTheater\BrokenLeg as BaseApiException;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Standard API response object to use as $v->results when returning a response.
 * This will also be used in the case that BrokenLeg exception is caught by
 * the framework just before rendering the response as JSON so that errors will
 * also use this object to return the error response as well.
 */
class APIResponse extends BaseCostume
{
	const STATUS_SUCCESS = 'SUCCESS';
	const STATUS_FAILURE = 'FAILURE';
	public $status = self::STATUS_SUCCESS;
	public $data = null;
	public $error = null;
	
	/**
	 * Everything went OK, respond with data attached to the standard
	 * API response object.
	 * @param mixed $aData - the data to return.
	 * @param integer $aRespCode (optional) an HTTP response code to be set; if
	 *  omitted, then the current response code will be retained
	 * @return $this Returns the created object.
	 */
	static public function resultsWithData( $aData, $aRespCode=null )
	{
		$theClassName = get_called_class() ;
		$o = new $theClassName() ;
		$o->data = $aData ;
		if ( !empty($aRespCode) ) {
			http_response_code( intval($aRespCode) ) ;
		}
		return $o ;
	}
	
	/**
	 * If an exception is caught, set the API response as a failure
	 * and return the error information.
	 * @param BaseApiException $aError - the standard API error class.
	 * @param boolean $bSetResponseCode specifies whether to overwrite the
	 *   response code of the ongoing HTTP transaction with the error code of the
	 *   `BrokenLeg` instance. Defaults to true, but you might want to set as
	 *   false if you're using an `APIResponse` object as an interim data
	 *   structure for some more elaborate transaction.
	 * @return $this Returns $this for chaining.
	 */
	public function setError( BaseApiException $aError, $bSetResponseCode=true )
	{
		$this->status = self::STATUS_FAILURE ;
		$this->error = $aError->toResponseObject() ;
		if( $bSetResponseCode ) {
			http_response_code( $aError->getCode() ) ;
		}
		return $this;
	}
	
	/**
	 * Constructs a canonical response for 204 NO CONTENT -- that is, null.
	 * @return NULL
	 */
	static public function noContentResponse()
	{
		http_response_code(204) ;
		return null ;
	}

	/**
	 * Prints the data set first, then the success and error fields, in case
	 * something goes wrong while we're in the act of serializing the data.
	 * @param int $aEncodeOptions - (optional) options for `json_encode()`
	 * @return $this Returns $this for chaining.
	 */
	public function printAsJson( int $aEncodeOptions=0 )
	{
		if ( !empty($this->data) && is_object($this->data) && method_exists($this->data, 'printAsJson') )
		{
			print( '{"data":' ) ;
			try {
				$this->data->printAsJson($aEncodeOptions) ;
				print( ',"status":"' . $this->status
						. '","error":'
					);
				if( !empty($this->error) )
					print( $this->error->toJson($aEncodeOptions) ) ;
				else
					print( 'null' ) ;
			}
			catch( \Exception $x ) {
				Strings::errorLog( __METHOD__
						. ' caught an exception while serializing: '
						. $x->getMessage()
					);
				print( ',"status":"' . self::STATUS_FAILURE
						. '","error":'
					);
				$theError = $x ;
				if( ! ( $x instanceof BaseApiException ) )
				{
					$theError = BaseApiException::pratfall( 'RESPONSE_FAILED',
							BaseApiException::HTTP_INTERNAL_SERVER_ERROR,
							$x->getMessage() ) ;
				}
				print( $theError->toJson($aEncodeOptions) ) ;
			}
			finally {
				print('}');
			}
		}
		else {
			print( json_encode( $this, $aEncodeOptions ) ) ;
		}
		return $this ;
	}

}//end class

}//end namespace
