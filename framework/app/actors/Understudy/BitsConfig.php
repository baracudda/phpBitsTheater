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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\Scene as MyScene; /* @var $v MyScene */
use BitsTheater\models\Config as ConfigModel; /* @var $dbConfig ConfigModel */
use BitsTheater\costumes\ConfigNamespaceInfo; /* @var $theNamespaceInfo ConfigNamespaceInfo */
use BitsTheater\costumes\ConfigSettingInfo; /* @var $theSettingInfo ConfigSettingInfo */
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use Exception;
{//namespace begin

class BitsConfig extends BaseActor {
	const DEFAULT_ACTION = 'edit';

	/**
	 * Website endpoint allowing the view/editing of website settings.
	 * @return string Return the URL to redirect to, if any.
	 */
	public function edit() {
		if (!$this->isAllowed('config','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		//in order to protect this endpoint from malicious JavaScript being executed from
		//  another domain trying to read sensitive settings (user/pw settings), the
		//  code that retrieved the data has been moved to a ajajGetSettings() and will
		//  be called from the "edit" view and displayed when the data is returned.

		$v->redirect = $this->getMyUrl('edit');
		$v->next_action = $this->getMyUrl('ajajModifyThenRedirect');
		$v->save_button_text = $this->getRes('generic/save_button_text');
	}

	/**
	 * Save the settings and then redirect to some other website endpoint.
	 * @return string Return the URL to redirect to, if any.
	 */
	public function ajajModifyThenRedirect() {
		if (!$this->isAllowed('config','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		try {
			$theResponse = $this->ajajModify();
			$v->addUserMsg($this->getRes('config/msg_save_applied'), $v::USER_MSG_NOTICE);
		} catch (Exception $e) {
			if ( !($e instanceof BrokenLeg && $e->getCode()==400 && $e->getCondition()=='NO_UPDATES') )
				$this->errorLog(__METHOD__.' '.$this->debugStr($e));
			$v->addUserMsg($this->getRes('config/msg_save_aborted'), $v::USER_MSG_ERROR);
		}
		return $v->redirect;
	}

	/**
	 * Return all the defined settings for the website.
	 * @return APIResponse Returns the standard API response object.
	 */
	public function ajajGetSettings() {
		$v =& $this->scene;
		if ($this->checkAllowed('config','modify')) {
			try {
				$dbConfig = $this->getProp('Config');
				$theConfigSettings = $dbConfig->getDefinedSettings();
				$theResults = array();
				foreach ($theConfigSettings as $theNamespaceInfo) {
					$theResults[] = $theNamespaceInfo->exportData();
				}
				$v->results = APIResponse::resultsWithData($theResults);
			} catch (Exception $e) {
				throw BrokenLeg::tossException($this, $e);
			}
		}
	}
	
	/**
	 * Return the standard API Response to indicate success/failure of saving settings.
	 * @return APIResponse Returns the standard API response object.
	 */
	public function ajajModify() {
		$v =& $this->scene;
		if ($this->checkAllowed('config','modify')) {
			$theResults = array();
			$bSaved = false;
			try {
				//CSRF token might get updated, remove the current one in use
				$theCsrfToken = null;
				$dbAuth = $this->getProp('Auth');
				list( $theCsrfCookieName, $theCsrfHeaderName) = $dbAuth->getCsrfCookieHeaderNames();
				if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
					$theCsrfToken = $this->getDirector()[$theCsrfHeaderName];
					$dbAuth->clearCsrfTokenCookie();
				}
				
				$dbConfig = $this->getProp('Config');
				$theConfigAreas = $dbConfig->getConfigAreas();
				foreach ($theConfigAreas as &$theNamespaceInfo) {
					$theNamespaceInfo->settings_list = $dbConfig->getConfigSettings($theNamespaceInfo);
					foreach ($theNamespaceInfo->settings_list as $theSettingName => $theSettingInfo) {
						$theWidgetName = $theSettingInfo->getWidgetName();
						if (isset($v->$theWidgetName)) {
							$theNewValue = $theSettingInfo->getInputValue($v);
							$theOldValue = $theSettingInfo->getCurrentValue();
							//if ($theSettingInfo->key==='security')
							//$this->debugLog(__METHOD__.' ov='.$theOldValue.' nv='.$this->debugStr($theNewValue));
							if ($theNewValue !== $theOldValue) {
								$dbConfig->setConfigValue($theSettingInfo->ns, $theSettingInfo->key, $theNewValue);
								$theResults[$theSettingInfo->ns][$theSettingInfo->key] = $theSettingInfo->getValueAsInputType();
								$bSaved = true;
							}
						}
					}
				}

				//update the CSRF token that is in use and may now be redefined
				$dbAuth->setCsrfTokenCookie($theCsrfToken);
			} catch (Exception $e) {
				throw BrokenLeg::tossException($this, $e);
			}
			if ($bSaved) {
				$v->results = APIResponse::resultsWithData($theResults);
			} else {
				throw BrokenLeg::pratfallRes($this, 'NO_UPDATES', 400, 'config/errmsg_nothing_to_update');
			}
		}
	}

}//end class

}//end namespace

