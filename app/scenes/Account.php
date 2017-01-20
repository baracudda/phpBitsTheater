<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace BitsTheater\scenes;
use BitsTheater\Scene as MyScene;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use ReflectionClass;
{//namespace begin

class Account extends MyScene {
	protected $KEY_userinfo = '';
	protected $KEY_pwinput = '';
	protected $KEY_cookie = '';
	public $jsCode = ''; //used in account list page render

	protected function setupDefaults() {
		parent::setupDefaults();
		$dbAuth = $this->getProp('Auth');
		$theMetaAuth = new ReflectionClass($dbAuth);
		if ($theMetaAuth->hasConstant('KEY_userinfo'))
			$this->KEY_userinfo = $theMetaAuth->getConstant('KEY_userinfo');
		if ($theMetaAuth->hasConstant('KEY_pwinput'))
			$this->KEY_pwinput = $theMetaAuth->getConstant('KEY_pwinput');
		if ($theMetaAuth->hasConstant('KEY_cookie'))
			$this->KEY_cookie = $theMetaAuth->getConstant('KEY_cookie');
		$theMetaAuth = null;
		$this->returnProp($dbAuth);
	}
	
	public function getUsernameKey() {
		return $this->KEY_userinfo;
	}
	
	public function getUsername() {
		$theKey = $this->getUsernameKey();
		return $this->$theKey;
	}
	
	public function getPwInputKey() {
		return $this->KEY_pwinput;
	}
	
	public function getPwInput() {
		$theKey = $this->getPwInputKey();
		return $this->$theKey;
	}
	
	public function getUseCookieKey() {
		return $this->KEY_cookie;
	}
	
	public function getUseCookie() {
		$theKey = $this->getUseCookieKey();
		return $this->$theKey;
	}
	
	/**
	 * Returns the human label used for a field.
	 * @param string $aFieldName - one of the property names defined
	 *     for AuthAccount costume.
	 */
	public function getColHeaderLabel($aFieldName)
	{
		switch ($aFieldName) {
			case 'account_id':   return $this->getRes('account/colheader_account_id');
			case 'account_name': return $this->getRes('account/colheader_account_name');
			case 'external_id':  return $this->getRes('account/colheader_account_extid');
			case 'auth_id':      return $this->getRes('account/colheader_auth_id');
			case 'email':        return $this->getRes('account/colheader_email');
			case 'verified_ts':  return $this->getRes('account/colheader_verified_ts');
			case 'is_active':    return $this->getRes('account/colheader_account_is_active');
			case 'created_by':   return $this->getRes('account/colheader_created_by');
			case 'updated_by':   return $this->getRes('account/colheader_updated_by');
			case 'created_ts':   return $this->getRes('account/colheader_created_ts');
			case 'updated_ts':   return $this->getRes('account/colheader_updated_ts');
		}//end switch
	}
	
	/**
	 * Construct the URL used for column headers that support sorting.
	 * @param string $aViewName - the name of the view with the table.
	 * @param string $aFieldName - the field name to sort on.
	 * @return string Returns the URL to be used to sort on the field provided.
	 */
	public function getColHeaderHrefForSortableField($aViewName, $aFieldName)
	{
		return $this->getMyUrl($aViewName, array(
				'orderby' => $aFieldName,
				'orderbyrvs' => ($this->orderby != $aFieldName || $this->orderbyrvs ? 0 : 1),
		));
	}

	/**
	 * Construct the HTML used for a column header for a particular field.
	 * @param string $aStyle - the style for the header column.
	 * @param string $aViewName - the name of the view with the table.
	 * @param string $aFieldName - the field name to sort on.
	 * @return string Returns the HTML to use.
	 */
	public function getColHeaderTextForSortableField($aStyle, $aViewName,
			$aFieldName)
	{
		return '<th style="'.$aStyle.'"><a href="'
				. $this->getColHeaderHrefForSortableField($aViewName, $aFieldName)
				.'">' . $this->getColHeaderLabel($aFieldName) . '</a></th>'
				;
	}
	
	/**
	 * Construct the HTML necessary to convert a UTC timestamp into the
	 * local-to-browser timezone value via JS code.
	 * @param number/string $aTime - either a UTC timestamp or a MySQL datetime string.
	 * @return string Returns the HTML needed to display local time.
	 */
	public function getLocalTimestampValue($aTime)
	{
		$theTsId = Strings::createUUID();
		$this->jsCode .= Widgets::cnvUtcTs2LocalStr($theTsId, $aTime)."\n";
		return '<span id="'.$theTsId.'" data-orig="'.$aTime.'">'.$aTime.'</span>';
	}

}//end class

}//end namespace
