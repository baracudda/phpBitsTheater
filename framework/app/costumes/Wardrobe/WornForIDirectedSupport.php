<?php
/*
 * Copyright (C) 2023 Blackmoon Info Tech Services
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
use BitsTheater\costumes\LogMessage as Logger;
use BitsTheater\Director;
use BitsTheater\Model;
use Exception;
{//begin namespace

trait WornForIDirectedSupport
{
	/**
	 * Return the director object.
	 * @return Director Returns the site director object.
	 */
	public function getDirector(): Director {
		return $this->director;
	}
	
	/**
	 * @inheritDoc
	 * @return Logger Returns the logger instance.
	 * @see Directed::getLogger()
	 */
	public function getLogger(): Logger
	{ return $this->getDirector()->getLogger(); }

	/**
	 * @inheritDoc
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see Directed::isAllowed()
	 */
	public function isAllowed( string $aNamespace, string $aPermission, array $aAcctInfo=null ): bool
	{ return $this->getDirector()->isAllowed($aNamespace, $aPermission, $aAcctInfo); }
	
	/**
	 * @inheritDoc
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see Directed::isGuest()
	 */
	public function isGuest(): bool
	{ return $this->getDirector()->isGuest(); }
	
	/**
	 * @inheritDoc
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see Directed::checkAllowed()
	 */
	public function checkAllowed( string $aNamespace, string $aPermission, array $aAcctInfo=null ): bool
	{ return $this->getDirector()->checkAllowed($aNamespace, $aPermission, $aAcctInfo); }
	
	/**
	 * @inheritDoc
	 * @return $this Returns $this for chaining.
	 * @see Directed::checkPermission()
	 */
	public function checkPermission( string $aNamespace, string $aPermission, array $aAcctInfo=null ): self
	{
		$this->getDirector()->checkPermission($aNamespace, $aPermission, $aAcctInfo);
		return $this;
	}
	
	/**
	 * @inheritDoc
	 * @return Model Returns the model object.
	 * @throws Exception when model cannot be found or there is a connection error.
	 * @see Directed::getProp()
	 */
	public function getProp( string|\ReflectionClass $aModelClass, string $aOrgID=null ): Model
	{ return $this->getDirector()->getProp($aModelClass, $aOrgID); }
	
	/**
	 * @inheritDoc
	 * @return $this Returns $this for chaining.
	 * @see Directed::returnProp()
	 */
	public function returnProp( ?Model $aProp ): self
	{
		$this->getDirector()->returnProp($aProp);
		return $this;
	}

	/**
	 * @inheritDoc
	 * @return mixed Returns the language resource desired.
	 * @see Director::getRes()
	 */
	public function getRes( string $aResName ): mixed
	{ return call_user_func_array(array($this->getDirector(), 'getRes'), func_get_args()); }
	
	/**
	 * @inheritDoc
	 * @return string Returns the relative path URL.
	 * @see Director::getSiteUrl()
	 */
	public function getSiteUrl( array|string $aRelativeURL='' ): string
	{ return call_user_func_array(array($this->getDirector(), 'getSiteUrl'), func_get_args()); }
	
	/**
	 * @inheritDoc
	 * @return mixed Returns the config setting.
	 * @see Director::getConfigSetting()
	 * @throws Exception
	 */
	public function getConfigSetting( string $aSetting, string $aOrgID=null ): mixed
	{ return $this->getDirector()->getConfigSetting($aSetting, $aOrgID); }
	
}//end trait

}//end namespace
