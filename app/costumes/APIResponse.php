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

namespace BitsTheater\costumes;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
use BitsTheater\BrokenLeg;
{//namespace begin

/**
 * Standard API response object to use as $v->results when returning a response.
 * This will also be used in the case that BrokenLeg exception is caught by
 * the framework just before rendering the response as JSON so that errors will
 * also use this object to return the error response as well.
 */
class APIResponse extends BaseCostume {
	const STATUS_SUCCESS = 'SUCCESS';
	const STATUS_FAILURE = 'FAILURE';
	public $status = self::STATUS_SUCCESS;
	public $data = null;
	public $error = null;
	
	/**
	 * Everything went OK, respond with data attached to the standard
	 * API response object.
	 * @param unknown $aData - the data to return.
	 * @return \BitsTheater\costumes\APIResponse Returns the created
	 * object with the data attached to it appropriately.
	 */
	static public function resultsWithData($aData) {
		$theClassName = get_called_class();
		$o = new $theClassName();
		$o->data = $aData;
		return $o;
	}
	
	/**
	 * If an exception is caught, set the API response as a failure
	 * and return the error information.
	 * @param \BitsTheater\BrokenLeg $aError
	 */
	public function setError( BrokenLeg &$aError )
	{
		$this->status = self::STATUS_FAILURE ;
		$this->error = $aError->toJson() ;
		http_response_code( $aError->getCode() ) ;
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

}//end class
	
}//end namespace
