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
use BitsTheater\costumes\LogMessage as Logger;
use BitsTheater\BrokenLeg;
use BitsTheater\Director;
use BitsTheater\Model;
use Exception;

{//begin namespace

interface IDirected {
	/**
	 * Returns the director object.
	 * @return Director Returns the director in charge of the website.
	 */
	public function getDirector(): Director;
	
	/**
	 * Getter for our director-wide modern LogMessage instance.
	 * @return Logger Returns the logger instance.
	 */
	public function getLogger(): Logger;

	/**
	 * Determine if the current logged in user or guest has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|null $aAcctInfo - (optional) check specified account
	 *   instead of currently logged in user.
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 */
	public function isAllowed( string $aNamespace, string $aPermission, array $aAcctInfo=null ): bool;

	/**
	 * Determine if there is even a user logged into the system or not.
	 * @return boolean Returns TRUE if no user is logged in.
	 */
	public function isGuest(): bool;
	
	/**
	 * Determine if the current logged in user or guest has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|null $aAcctInfo - (optional) check specified account
	 *   instead of currently logged in user.
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @throws BrokenLeg 403 if not allowed and logged in or 401
	 *   if not allowed and guest.
	 */
	public function checkAllowed( string $aNamespace, string $aPermission, array $aAcctInfo=null ): bool;
	
	/**
	 * Determine if the current logged in user or guest has a permission.
	 * @param string $aNamespace - namespace of the permission to check.
	 * @param string $aPermission - permission name to check.
	 * @param array|null $aAcctInfo - (optional) check specified account
	 *   instead of currently logged in user.
	 * @return $this Returns $this for chaining purposes.
	 * @throws BrokenLeg 403 if not allowed and logged in or 401
	 *   if not allowed and guest.
	 */
	public function checkPermission( string $aNamespace, string $aPermission, array $aAcctInfo=null ): self;
	
	/**
	 * Return a Model object for a given org, creating it if necessary.
	 * @param string|\ReflectionClass $aModelClass - name or classinfo of a model class object.
	 * @param string|null $aOrgID - (optional) the org ID whose data we want.
	 * @return Model Returns the model object.
	 * @throws Exception when model cannot be found or there is a connection error.
	 */
	public function getProp( string|\ReflectionClass $aModelClass, string $aOrgID=null ): Model;
	
	/**
	 * Let the system know you do not need a Model anymore so it
	 * can close the database connection as soon as possible.
	 * @param ?Model $aProp - the Model object to be returned to the prop closet.
	 * @return $this Returns $this for chaining purposes.
	 */
	public function returnProp( ?Model $aProp ): self;

	/**
	 * Get a resource based on its combined 'namespace/resource_name'.
	 * Alternatively, you can pass each segment in as its own parameter.
	 * @param string $aResName - The 'namespace/resource[/extras]' name to retrieve.
	 * @return mixed Returns the language resource desired.
	 */
	public function getRes( string $aResName ): mixed;
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param string[]|string $aRelativeURL - array of path segments
	 *   OR a bunch of string parameters equating to path segments.
	 * @return string - returns the site domain + relative path URL.
	 */
	public function getSiteUrl( array|string $aRelativeURL='' ): string;

	/**
	 * Get the setting from the configuration model.
	 * @param string $aSetting - setting in form of "namespace/setting"
	 * @param string|null $aOrgID - (optional) the org ID whose data we want.
	 * @return mixed Returns the configuration of the desired setting.
	 * @throws Exception
	 */
	public function getConfigSetting( string $aSetting, string $aOrgID=null ): mixed;

}//end interface

}//end namespace
