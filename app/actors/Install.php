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

namespace BitsTheater\actors; 
use BitsTheater\Actor;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use com\blackmoonit\database\DbUtils;
use \PDOException;
{//namespace begin

class Install extends Actor {
	const DEFAULT_ACTION = 'install';

	/**
	 * Similar to file_put_contents, but forces all parts of the folder path to exist first.
	 * @param string $aDestFile - path and filename of destination.
	 * @param string $aFileContents - contents to be saved in $aDestFile.
	 * @return Returns false on failure, else num bytes stored.
	 */
	protected function file_force_contents($aDestFile, $aFileContents) {
		$theFolders = dirname($aDestFile);
		if (!is_dir($theFolders)) {
			//umask(0777);
			mkdir($theFolders,0777,true);
			chmod($aDestFile, 0777);
		}
		return file_put_contents($aDestFile, $aFileContents);
     }
	
	protected function installTemplate($aDestPath, $aTemplateName, $aNewExtension, $aVars) {
		//copy the .tpl to .php and fill in the vars
		$dst = $aDestPath.$aTemplateName.$aNewExtension;
		if (file_exists($dst))
			return $dst;
		$src = BITS_RES_PATH.'templates'.¦.$aTemplateName.'.tpl';
		$tpl = file_get_contents($src);
		if ($tpl) {
			foreach ($aVars as $theVarName) {
				$tpl = str_replace('%'.$theVarName.'%',$this->scene->$theVarName,$tpl);
			}
			if ($this->file_force_contents($dst,$tpl)) {
				return $dst;
			}
		}
		return false;
	}

	protected function installConfigTpl($aTemplateName, $aNewExtension, $aVars) {
		return $this->installTemplate(BITS_CFG_PATH,$aTemplateName,$aNewExtension,$aVars);
	}

	public function install() {
		//avoid installing more than once
		if ($this->director->canCheckTickets() && $this->director->isInstalled()) {
				return $this->getHomePage();
		}
	
		//make sure we start off with a fresh session
		$this->director->resetSession();
		
		//ask for something only installer would know, like detail of install file that cannot be accessed from web
		
		//next action in the install sequence
		if (!$this->director->canGetRes())
			$this->scene->next_action = $this->scene->getSiteURL('install/lang1');
		elseif (!$this->director->canConnectDb())
			$this->scene->next_action = $this->scene->getSiteURL('install/db1');
		elseif (!$this->director->canCheckTickets())
			$this->scene->next_action = $this->scene->getSiteURL('install/auth1');
		else
			$this->scene->next_action = $this->scene->getSiteURL('install/setupDb');
	}
	
	protected function getLangTypes() {
		$theTypes = array();
		$theLangPattern = BITS_RES_PATH.'i18n'.DIRECTORY_SEPARATOR.'??';
		foreach (glob($theLangPattern,GLOB_ONLYDIR+GLOB_ERR) as $theLangFolder) {
			$theRegionPattern = $theLangFolder.DIRECTORY_SEPARATOR.'*';
			foreach (glob($theRegionPattern,GLOB_ONLYDIR+GLOB_ERR) as $theRegionFolder) {
				$theValueFile = $theRegionFolder.DIRECTORY_SEPARATOR.'lang_item_choice.html';
				if (is_file($theValueFile)) {
					$theKey = basename($theLangFolder).'/'.basename($theRegionFolder);
					$theResUrl = $this->scene->getSiteURL('res/i18n/'.$theKey);
					$theValue = str_replace('%path%',$theResUrl,file_get_contents($theValueFile));
					$theTypes[$theKey] = $theValue;
				}
			}
		}
		return $theTypes;
	}

	public function lang1() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL('install');
		$this->scene->next_action = $this->scene->getSiteURL('install/lang2');
		$this->scene->lang_types = $this->getLangTypes();
	}
	
	protected function installLang($aLangType) {
		$theVarNames = array('default_lang','default_region');
		$sa = explode('/',$this->scene->lang_type);
		$this->scene->default_lang = $sa[0];
		$this->scene->default_region = $sa[1];
		
		return $this->installConfigTpl('I18N','.php',$theVarNames);
	}
	
	public function lang2() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->lang_types = $this->getLangTypes();
		$this->scene->permission_denied = !$this->installLang($this->scene->lang_type);
		if ($this->scene->permission_denied) {
			$this->scene->next_action = $this->scene->getSiteURL('install/lang1');
		} else {
			$this->scene->next_action = $this->scene->getSiteURL('install/db1');
		}
	}
	
	public function db1() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->next_action = $this->scene->getSiteURL('install/db2');
		$this->scene->db_types = $this->scene->getDbTypes();
	}

	public function db2() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		if (!$v->checkInstallPw()) { 
			return $v->getSiteURL();
		}
		$v->strip_spaces('table_prefix');
		switch ($v->dns_scheme) {
			case 'customuri':
				unset($v->dns_alias);
				unset($v->dbpwrd); //some browsers auto-fill this field, unset if not using "ini" scheme
				break;
			case 'alias':
				unset($v->dns_customuri);
				unset($v->dbpwrd); //some browsers auto-fill this field, unset if not using "ini" scheme
				break;
			default: //ini
				unset($v->dns_alias);
				unset($v->dns_customuri);
		}//switch
		$theVarNames = array('table_prefix','dns_scheme','dns_alias','dns_customuri','dbhost','dbtype','dbname','dbuser','dbpwrd');
		if ($dst = $this->installConfigTpl('dbconn-webapp','.ini',$theVarNames)) {
			//copy completed, now try to connect to the db and prove it works
			try {
				$v->connected = $this->director->getDbConnInfo('webapp')->connect();;
				$v->next_action = $v->getSiteURL('install/auth1');
			} catch (PDOException $e) {
				$ex = new DbException($e);
				$v->next_action = $v->getSiteURL('install/db1');
				$v->connected = false;
				$v->_dbError = $ex->getDebugDisplay('Connection error');
				$v->old_vals = $v->createHiddenPosts(array('dns_scheme', 'dns_alias', 'dns_customuri', 
						'table_prefix','dbhost','dbtype','dbname','dbuser','dbpwrd'));
			}
			if (empty($v->connected) && empty($v->do_not_delete_failed_config)) {
				//if db connection failed, delete the file so it can be attempted again
				$v->permission_denied = !unlink($dst);
			}
		} else {
			$v->permission_denied = true;
		}
	}

	protected function getAuthTypes() {
		$theAuthTypes = array();
		$defAuthClassPattern = BITS_RES_PATH.'templates'.¦.'Auth_*.tpl';
		//Strings::debugLog($defAuthClassPattern);
		//Strings::debugLog(Strings::debugStr(glob($defAuthClassPattern)));
		foreach (glob($defAuthClassPattern) as $auth_filename) {
			$authtype = str_replace('.tpl','',str_replace('Auth_','',basename($auth_filename)));
		    $theAuthTypes[$authtype] = $authtype;
		}
		return $theAuthTypes;
	}

	public function auth1() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->next_action = $this->scene->getSiteURL('install/auth2');
		$this->scene->auth_types = $this->getAuthTypes();
	}

	protected function installAuth($aAuthType) {
		//copy the auth type class out of lib/authtype into app/model
		$src = BITS_RES_PATH.'templates'.¦.'Auth_'.$aAuthType.'.tpl';
		$dst = BITS_APP_PATH.'model'.¦.'Auth.php';
		return copy($src,$dst);
	}
	
	public function auth2() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->next_action = $this->scene->getSiteURL('install/setupDb');
		$this->scene->permission_denied = !$this->installAuth($this->scene->auth_type);
		if ($this->director->canCheckTickets()) {
			$this->scene->auth_model = $this->director->getProp('Auth');
			$this->scene->auth_install_options = $this->scene->auth_model->renderInstallOptions($this);
		}
	}
	
	public function setupDb() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->next_action = $this->scene->getSiteURL('install/allFinished');
		//auth and db configs are installed, lets create our database
		$theSetupDb = $this->director->getProp('SetupDb');
		try {
			$theSetupDb->setupModels($this->scene);
		} catch (DbException $dbe) {
			$this->scene->_dbError = $dbe->getDebugDisplay();
			$this->scene->popDbResults();
		}
		$this->director->returnProp($theSetupDb);
	}
	
	/**
	 * URL: %site%/install/resetup_db
	 */
	public function resetupDb() {
		if (!$this->director->isInstalled()) return $this->scene->getSiteURL();
		if (!$this->isAllowed('config','modify')) return $this->scene->getSiteURL();
		//lets re-create our database
		$theSetupDb = $this->director->getProp('SetupDb');
		$theSetupDb->setupModels($this->scene);
		$this->director->returnProp($theSetupDb);
		return $this->scene->getSiteURL('install/allFinished');
	}
	
	protected function installSettings($aNewAppId) {
		$this->scene->app_id = $aNewAppId;
		$theVarNames = array('app_id');
		return $this->installConfigTpl('Settings','.php',$theVarNames) &&
				$this->installTemplate(BITS_RES_PATH,'MenuInfo','.php',$theVarNames);
	}

	public function allFinished() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->next_action = $this->scene->getSiteURL('config');
		//do something to signify finished
		if ($this->installSettings(Strings::createGUID())) {
			//see where to go from here
			$accounts = $this->getProp('Accounts');
			if ($accounts->isEmpty()) {
				$dbAuth = $this->getProp('Auth');
				if ($dbAuth->isRegistrationAllowed()) {
					$this->scene->next_action = $this->scene->getSiteURL($this->config['auth/register_url']);
				} else {
					$this->scene->next_action = $this->scene->getSiteURL($this->config['auth/login_url']);
				}
				$this->returnProp($dbAuth);
			}
			$this->director->returnProp($accounts);
		} else {
			$this->scene->permission_denied = true;
		}
	}
	
	public function resetDb($pw) {
		//debug function, does nothing now
	}
		
}//end class

}//end namespace

