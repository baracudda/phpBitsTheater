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
{//begin namespace

/**
 * A set of methods helpful for dealing with a REST service.
 * NOTE: for use with $this implementing IDirected.
 */
trait WornForRestService
{
	
	protected $mConfigNameForURL = 'host_list';
	protected $mConfigNameForPort = 'port';
	protected $mConfigNameForUsername = 'username';
	protected $mConfigNameForPassword = 'password';
	
	/**
	 * Given some URL parts, fill in any gaps and return the result.
	 * @param string[] $aUrlParts - the result of a parse_url() call.
	 * @param string $aConfigNamespace - the config namespace to use.
	 * @param number $aDefaultPort - (optional) the port to use if not part of $aHost.
	 * @return string[] The URL parts all ready for a recombineUrl() call.
	 */
	protected function constructHostUrlParts($aUrlParts, $aConfigNamespace, $aDefaultPort=null)
	{
		if ( !empty($aUrlParts['path']) && empty($aUrlParts['host']) ) {
			$thePathParts = explode('/', $aUrlParts['path']);
			$aUrlParts['host'] = array_shift($thePathParts);
			$aUrlParts['path'] = implode('/', $thePathParts);
			if ( !empty($aUrlParts['path']) )
				$aUrlParts['path'] = '/'.$aUrlParts['path'];
		}
		
		$theConfigPort = $this->getConfigSetting($aConfigNamespace.'/'.$this->mConfigNameForPort);
		$aUrlParts['port'] = ( !empty($theConfigPort) ) ? $theConfigPort : $aDefaultPort;
		
		$theConfigUser = $this->getConfigSetting($aConfigNamespace.'/'.$this->mConfigNameForUsername);
		$theConfigPw = $this->getConfigSetting($aConfigNamespace.'/'.$this->mConfigNameForPassword);
		if ( !empty($theConfigUser) && !empty($theConfigPw) ) {
			$aUrlParts['user'] = $theConfigUser;
			$aUrlParts['pass'] = $theConfigPw;
		}
		return $aUrlParts;
	}
	
	/**
	 * Given a base host name, add on the user/pw and port parts.
	 * @param string $aHost - the host string to parse.
	 * @param string $aConfigNamespace - the config namespace to use.
	 * @param number $aDefaultPort - (optional) the port to use if not part of $aHost.
	 * @return string Return the parsed/constructed host string to use.
	 */
	protected function constructHostString($aHost, $aConfigNamespace, $aDefaultPort=null)
	{
		$theUrlParts = parse_url(rtrim(trim($aHost), '/'));
		if ( !empty($theUrlParts) ) {
			$theUrlParts = $this->constructHostUrlParts($theUrlParts, $aConfigNamespace, $aDefaultPort);
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
	 * @throws \BitsTheater\BrokenLeg
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
	 * @return APIResponse - the response from the endpoint,
	 *  encapsulated in a known container.
	 * @throws BrokenLeg::ACT_SERVICE_UNAVAILABLE if $aPostURL is empty.
	 */
	protected function sendRequestToRestService($aPostURL, $aAction=null, $aData=null, $aTimeout=45)
	{
		$theResult = new APIResponse() ;
		$theRequest = CurlRequestBuilder::withURL($this, $aPostURL, $aAction)
			->encodeDataAsJSON($aData)->setTimeout($aTimeout)
			;
		try {
			$theRawResponse = $theRequest->execute();
			//$theRequest->logReqDebug($theRawResponse); //DEBUG
			$theRespData = json_decode($theRawResponse) ;
			if ( $theRequest->isResponseSuccessful() )
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
						$theRequest->getResponseCode(), $theErrorMessage ) ;
				$theResult->error = $theFailure ;
			}
			return $theResult ;
		}
		finally {
			$theRequest->closeRequest();
		}
	}
	
}//end class

}//end namespace
