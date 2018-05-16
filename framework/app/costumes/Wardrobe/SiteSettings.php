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
use stdClass as BaseClass;
{//begin namespace

class SiteSettings extends BaseClass
{
	/**
	 * @var string
	 * The install-generated UUID/string used to differentiate websites
	 * residing on the same server (used to segregate PHP-session vars).
	 */
	const APP_ID = '%app_id%';
	/**
	 * @return string Return the install-generated UUID/string used to
	 * differentiate websites residing on the same server. Used mainly to
	 * segregate PHP-session variables and set some config defaults.
	 */
	static public function getAppId()
	{ return static::APP_ID; }
	
	/**
	 * @var string
	 * The Home Page of the website if nothing was provided in the URL.
	 * Filled in by the res "install/landing_page" string.
	 */
	const PAGE_Landing = '%landing_page%';
	/**
	 * @return string
	 * The Home Page of the website if nothing was provided in the URL.
	 */
	static function getLandingPage()
	{ return static::PAGE_Landing; }
	
}//end class

}//end namespace
