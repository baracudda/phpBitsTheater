<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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
use BitsTheater\costumes\ABitsCostume as BaseCostume;
{//namespace begin

/**
 * HTTP Authorization headers have different members based on the scheme
 * being utilized, but any common characterizitics/methods would go here.
 */
class HttpAuthHeader extends BaseCostume
{
	use WornForHttpAuthBasic, WornForHttpAuthBroadway;
	
	/**
	 * The raw HTTP Auth header.
	 * @var string
	 */
	public $auth_header = null;
	/**
	 * The scheme name for the HTTP Auth header.
	 * @var string
	 */
	public $auth_scheme = null;
	
	/**
	 * Called during object construction.
	 * @param IDirected $aContext - used to get the Director object.
	 */
	public function setup(IDirected $aContext) {
		parent::setup($aContext);
		if ( !empty($_SERVER['HTTP_AUTHORIZATION']) )
		{ $this->setHttpAuthHeader($_SERVER['HTTP_AUTHORIZATION']); }
	}

	/**
	 * Construct a new object with an optional HTTP Auth parameter.
	 * @param IDirected $aContext - used to get the Director object.
	 * @param string $aHttpAuthHeader - (optional) HTTP Auth header.
	 * @return HttpAuthHeader Returns the newly constructed object.
	 */
	static public function fromHttpAuthHeader(IDirected $aContext, $aHttpAuthHeader=null) {
		$theClassName = get_called_class();
		$o = new $theClassName($aContext);
		return $o->setHttpAuthHeader($aHttpAuthHeader);
	}

	/**
	 * Set the header and determine the scheme in use.
	 * @param string $aHttpAuthHeader - the HTTP Auth header.
	 * @return HttpAuthHeader Returns $this for chaining.
	 */
	public function setHttpAuthHeader($aHttpAuthHeader) {
		if (!empty($aHttpAuthHeader)) {
			$this->auth_header = $aHttpAuthHeader;
			$this->auth_scheme = strstr($this->auth_header, ' ', true);
			$this->parseAuthData();
		}
		return $this;
	}
	
	/**
	 * Parse out the Auth data according to the Auth scheme.
	 */
	protected function parseAuthData() {
		$theAuthData = base64_decode(substr($this->auth_header, strlen($this->auth_scheme)+1));
		switch ($this->auth_scheme) {
			case 'Basic':
				$this->parseAuthHeaderAsAuthBasic($theAuthData);
				break;
			case 'Broadway':
				$this->parseAuthHeaderAsAuthBroadway($theAuthData);
				break;
		}//switch
	}
	
}//end class

}//end namespace
