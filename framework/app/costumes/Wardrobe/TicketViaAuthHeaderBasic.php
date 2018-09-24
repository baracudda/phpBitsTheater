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
use BitsTheater\costumes\Wardrobe\TicketViaRequest as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\HttpAuthHeader;
use BitsTheater\Scene;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Class used to help manage logging in via HTTP "Basic" Authorization Header.
 * @since BitsTheater [NEXT]
 */
class TicketViaAuthHeaderBasic extends BaseCostume
{
	/** @var string The HTTP Authorization scheme. */
	const AUTH_SCHEME = 'Basic';
	/** @var string The raw HTTP Auth header. */
	protected $auth_header = null;
	/** @var string The scheme name for the HTTP Auth header. */
	protected $auth_scheme = null;
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		$this->auth_header = Strings::getHttpHeaderValue('Authorization');
		if ( !empty($this->auth_header) ) {
			$this->auth_scheme = strstr($this->auth_header, ' ', true);
			if ( $this->auth_scheme == $this::AUTH_SCHEME ) {
				//decode the header data
				$theAuthData = base64_decode(
						substr($this->auth_header, strlen($this->auth_scheme)+1)
				);
				list($this->ticket_name, $this->ticket_secret) = explode(':', $theAuthData);
				//keeping lightly protected pw in memory can be bad, clear out usage asap.
				$theServerKey = Strings::httpHeaderNameToServerKey('Authorization');
				unset($_SERVER[$theServerKey]);
			}
		}
		return $this;
	}
	
}//end class

}//end namespace
