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
{//begin namespace

/**
 * A set of methods helpful for dealing with a REST service.
 */
trait WornForRestService
{
	
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
			$theConfigPort = $this->getConfigSetting($aConfigNamespace.'/port');
			if ( !empty($theConfigPort) )
			{
				$theUrlParts['port'] = $theConfigPort;
			}
			$theConfigUser = $this->getConfigSetting($aConfigNamespace.'/username');
			$theConfigPw = $this->getConfigSetting($aConfigNamespace.'/password');
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
		$theHosts = explode(',', $this->getConfigSetting($aConfigNamespace.'/host_list'));
		foreach ($theHosts as $theHost) {
			$theUrl = $this->constructHostString($theHost, $aConfigNamespace, $aDefaultPort);
			if (!empty($theUrl))
				$theResults[] = $theUrl;
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
	
}//end class

}//end namespace
