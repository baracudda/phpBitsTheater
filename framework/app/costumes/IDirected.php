<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
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
{//begin namespace

interface IDirected {
	/**
	 * Returns the director object.
	 * @return \BitsTheater\Director Returns the director in charge of the website.
	 */
	public function getDirector();
	
	/**
	 * Determine if the current logged in user or guest has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|NULL $acctInfo - (optional) check specified account
	 *   instead of currently logged in user.
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 */
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null);

	/**
	 * Determine if there is even a user logged into the system or not.
	 * @return boolean Returns TRUE if no user is logged in.
	 */
	public function isGuest();
	
	/**
	 * Determine if the current logged in user or guest has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|NULL $acctInfo - (optional) check specified account
	 *   instead of currently logged in user.
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @throws \BitsTheater\BrokenLeg 403 if not allowed and logged in or 401
	 *   if not allowed and guest.
	 */
	public function checkAllowed($aNamespace, $aPermission, $acctInfo=null);
	
	/**
	 * Determine if the current logged in user or guest has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|NULL $acctInfo - (optional) check specified account
	 *   instead of currently logged in user.
	 * @return $this Returns $this for chaining purposes.
	 * @throws \BitsTheater\BrokenLeg 403 if not allowed and logged in or 401
	 *   if not allowed and guest.
	 */
	public function checkPermission($aNamespace, $aPermission, $acctInfo=null);
	
	/**
	 * Return a Model object, creating it if necessary.
	 * @param string $aName - name of the model object.
	 * @return \BitsTheater\Model Returns the model object.
	 */
	public function getProp($aName);
	
	/**
	 * Let the system know you do not need a Model anymore so it
	 * can close the database connection as soon as possible.
	 * @param \BitsTheater\Model $aProp - the Model object to be returned to
	 *   the prop closet.
	 */
	public function returnProp($aProp);

	/**
	 * Get a resource based on its combined 'namespace/resource_name'.
	 * Alternatively, you can pass each segment in as its own parameter.
	 * @param string $aName - The 'namespace/resource[/extras]' name to retrieve.
	 */
	public function getRes($aName);
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param mixed $aRelativeUrl - array of path segments OR a bunch of
	 *   string parameters equating to path segments.
	 * @return string - returns the site domain + relative path URL.
	 */
	public function getSiteUrl($aRelativeUrl='', $_=null);

	/**
	 * Get the setting from the configuration model.
	 * @param string $aSetting - setting in form of "namespace/setting"
	 * @throws \Exception
	 */
	public function getConfigSetting($aSetting);

}//end interface

}//end namespace
