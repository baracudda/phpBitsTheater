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

namespace BitsTheater\costumes ;
use com\blackmoonit\Strings;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
{//begin namespace

/**
 * A set of methods helpful for dealing with a REST service.
 */
trait WornForRestService
{
	
	protected $mConfigNameForURL = 'host_list';
	protected $mConfigNameForPort = 'port';
	protected $mConfigNameForUsername = 'username';
	protected $mConfigNameForPassword = 'password';
	
	/**
	 * Given a base host name, add on the user/pw and port parts.
	 */
	protected function constructHostString($aHost, $aConfigNamespace, $aDefaultPort=null)
	{
		$theUrlParts = parse_url(rtrim(trim($aHost), '/'));
		if (!empty($theUrlParts))
		{
			if ( !empty($theUrlParts['path']) && empty($theUrlParts['host']) )
			{
				$thePathParts = explode('/', $theUrlParts['path']);
				$theUrlParts['host'] = array_shift($thePathParts);
				$theUrlParts['path'] = implode('/', $thePathParts);
				if ( !empty($theUrlParts['path']) )
					$theUrlParts['path'] = '/'.$theUrlParts['path'];
			}
			$theUrlParts['port'] = $aDefaultPort;
			$theConfigPort = $this->getConfigSetting($aConfigNamespace.'/'.$this->mConfigNameForPort);
			if ( !empty($theConfigPort) )
			{
				$theUrlParts['port'] = $theConfigPort;
			}
			$theConfigUser = $this->getConfigSetting($aConfigNamespace.'/'.$this->mConfigNameForUsername);
			$theConfigPw = $this->getConfigSetting($aConfigNamespace.'/'.$this->mConfigNameForPassword);
			if ( !empty($theConfigUser) &&
					!empty($theConfigPw) )
			{
				$theUrlParts['user'] = $theConfigUser;
				$theUrlParts['pass'] = $theConfigPw;
			}
			//$this->debugLog(__METHOD__.' '.Strings::recombineUrl($theUrlParts));
			return Strings::recombineUrl($theUrlParts);
		}
	}
	
	/**
	 * The Host list may contain a comma separated list of hosts/brokers we
	 * may need to connect with. Generate this list by taking user/pw/port into
	 * account as well as the host name itself.
	 * @return string[] Returns the generated hosts as an array.
	 */
	public function generateHostList($aConfigNamespace, $aDefaultPort=null)
	{
		$theResults = array();
		$theConfigValue = $this->getConfigSetting($aConfigNamespace.'/'.$this->mConfigNameForURL);
		if (!empty($theConfigValue)) {
			$theHosts = explode(',', $theConfigValue);
			if (!empty($theHosts)) {
				foreach ($theHosts as $theHost) {
					$theUrl = $this->constructHostString($theHost, $aConfigNamespace, $aDefaultPort);
					if (!empty($theUrl))
						$theResults[] = $theUrl;
				}
			}
		}
		return $theResults;
	}

	/**
	 * The Host list may contain a comma separated list of hosts/brokers we
	 * may need to connect with. Generate this list by taking user/pw/port into
	 * account as well as the host name itself.
	 * @return string Returns the generated comma separated host string.
	 */
	public function generateHostListString($aConfigNamespace, $aDefaultPort=null)
	{
		$theResults = $this->generateHostList($aConfigNamespace, $aDefaultPort);
		if (!empty($theResults))
			return implode(',', $theResults);
	}
	
	/**
	 * If the config settings are not fully defined, you may toss this
	 * generic exception.
	 * @param string $aConfigNamespace - the namespace of the config settings.
	 * @throws BitsTheater\BrokenLeg
	 */
	public function tossWhenNotDefined($aConfigNamespace) {
		$theCondition = strtoupper($aConfigNamespace) . '_NOT_DEFINED';
		$theDirector = $this->getDirector();
		throw BrokenLeg::pratfallRes($theDirector, $theCondition, 412,
				'generic/errmsg_x_not_defined',
				$theDirector->getRes('config/namespace')[$aConfigNamespace]->label
		);
	}
	
	/**
	 * Sends a POST request to the Rest Service API.
	 * @param string $aAction - the action to be performed.
	 * @param string $aData - the POST data to accompany the request.
	 * @param number $aTimeout - the timeout value for the request.
	 * @return \Joka\costumes\APIResponse - the response from the endpoint,
	 *  encapsulated in a known container.
	 */
	protected function sendRequestToRestService($aPostURL, $aAction=null, $aData=null, $aTimeout=45)
	{
		$theResult = new APIResponse() ;
		if (empty($aPostURL))
			throw BrokenLeg::toss( $this, BrokenLeg::ACT_SERVICE_UNAVAILABLE ) ;
		
		$thePostURL = (Strings::endsWith($aPostURL, '/')) ? $aPostURL : $aPostURL . '/';
		$thePostURL .= $aAction ;
		$theRequest = curl_init() ;
		curl_setopt( $theRequest, CURLOPT_URL, $thePostURL ) ;
		curl_setopt( $theRequest, CURLOPT_RETURNTRANSFER, true ) ;
		if (!empty($aData)) {
			$theEncodedData = json_encode($aData) ;
			curl_setopt( $theRequest, CURLOPT_CUSTOMREQUEST, 'POST' ) ;
			curl_setopt( $theRequest, CURLOPT_POSTFIELDS, $theEncodedData ) ;
			curl_setopt( $theRequest, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($theEncodedData)
			));
		}
		else {
			curl_setopt( $theRequest, CURLOPT_CUSTOMREQUEST, 'GET' ) ;
		}
		curl_setopt( $theRequest, CURLOPT_CONNECTTIMEOUT, $aTimeout ) ;

		$theRawResponse = curl_exec($theRequest) ;
		$theRespData = json_decode($theRawResponse) ;
		$theRespCode = curl_getinfo( $theRequest, CURLINFO_HTTP_CODE ) ;
		curl_close($theRequest) ;
//		$this->debugLog( __METHOD__ . ' DEBUG - URL [' . $thePostURL
//				. ']; request data [' . json_encode($aData)
//				. ']; response data [' . $theRawResponse
//				. ']; HTTP code ['. $theRespCode . ']' )
//				;
		if( $theRespCode >= 200 && $theRespCode < 300 )
		{ // Success!
			$theResult->status = APIResponse::STATUS_SUCCESS ;
			$theResult->data = $theRespData ;
		}
		else
		{ // Failure!
			$theResult->status = APIResponse::STATUS_FAILURE ;
			$theResult->data = $theRespData ;
			$theErrorMessage = $this->getRes('generic/errmsg_failure');
			if( isset($theRespData->error) && isset($theRespData->error->message) )
				$theErrorMessage = $theRespData->error->message ;
			$theFailure = BrokenLeg::pratfall(strtoupper($this->getRes('generic/errmsg_failure')),
					$theRespCode, $theErrorMessage ) ;
			$theResult->error = $theFailure ;
		}
		return $theResult ;
	}
	
}//end class

}//end namespace
