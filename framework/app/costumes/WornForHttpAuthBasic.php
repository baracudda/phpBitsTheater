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
{ // begin namespace

/**
 * A set of methods useful for decoding the Basic scheme of a HTTP Auth header.
 * @since BitsTheater 3.6.1
 */
trait WornForHttpAuthBasic
{
	/** @var string The HTTP Authorization type */
	const AUTH_TYPE_BASIC = 'Basic';
	/**
	 * Basic HTTP Auth name of the account.
	 * @var string
	 */
	protected $account_name;
	/**
	 * Basic HTTP Auth password for the account.
	 * @var string
	 */
	protected $pw_input;

	protected function parseAuthHeaderAsAuthBasic($aAuthData) {
		list($this->account_name, $this->pw_input) = explode(':', $aAuthData);
	}
	
	/**
	 * @return string Return the account name specified in the header.
	 */
	public function getHttpAuthBasicAccountName() {
		return $this->account_name;
	}

	/**
	 * @return string Return the password specified in the header.
	 */
	public function getHttpAuthBasicAccountPw() {
		return $this->pw_input;
	}
	
} // end trait

} // end namespace
