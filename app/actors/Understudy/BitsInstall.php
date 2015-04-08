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
use BitsTheater\scenes\Install as MyScene; /* @var $v MyScene */
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\database\DbConnOptions;
use com\blackmoonit\database\DbConnSettings;
use com\blackmoonit\database\DbConnInfo; /* @var $theDbConnInfo DbConnInfo */
use com\blackmoonit\database\DbUtils;
use BitsTheater\models\SetupDb; /* @var $dbSetupDb SetupDb */
use BitsTheater\models\Accounts; /* @var $dbAccounts Accounts */
use com\blackmoonit\Strings;
use com\blackmoonit\FileUtils;
use \PDOException;
{//namespace begin

class BitsInstall extends BaseActor {
	const DEFAULT_ACTION = 'install';

	/**
	 * Similar to file_put_contents, but forces all parts of the folder path to exist first.
	 * @param string $aDestFile - path and filename of destination.
	 * @param string $aFileContents - contents to be saved in $aDestFile.
	 * @return Returns false on failure, else num bytes stored.
	 */
	protected function file_force_contents($aDestFile, $aFileContents) {
		return FileUtils::file_force_contents($aDestFile, $aFileContents, strlen($aFileContents));
	}
	
	protected function copyFileContents($aSrcFilePath, $aDestFilePath, $aVarList) {
		if (file_exists($aDestFilePath))
			return $aDestFilePath;
		$theReplacements = array();
		foreach ($aVarList as $theReplacementName => $theVarName) {
			if (is_int($theReplacementName)) {
				$theReplacementName = $theVarName;
			}
			$theReplacements[$theReplacementName] = $this->scene->$theVarName;
		}
		if (FileUtils::copyFileContents($aSrcFilePath, $aDestFilePath, $theReplacements))
			return $aDestFilePath;
		else
			return false;
	}
	
	protected function installTemplate($aTemplateName, $aNewName, $aVars) {
		//copy the .tpl and fill in the vars
		$src = BITS_RES_PATH.'templates'.¦.$aTemplateName.'.tpl';
		return $this->copyFileContents($src, $aNewName, $aVars);
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
		
		return $this->installTemplate('I18N', BITS_CFG_PATH.'I18N.php', $theVarNames);
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
	
	/**
	 * Websites with multiple db connections would override this method
	 * and not call parent. 
	 * @return DbConnInfo[]
	 */
	public function getDbConns() {
		$db_conns = array();
	
		$theDbConnInfo = DbConnInfo::asSchemeINI('webapp');
		$theDbConnInfo->dbConnSettings->dbname = '';
		$theDbConnInfo->dbConnSettings->host = '';
		$theDbConnInfo->dbConnSettings->username = '';
		$db_conns[] = $theDbConnInfo;
	
		return $db_conns;
	}
	
	public function db1() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		if (!$v->checkInstallPw())
			return $v->getSiteURL();
		$v->next_action = $v->getSiteURL('install/db2');
		$v->db_types = $v->getDbTypes();
		$v->db_conns = $this->getDbConns();
	}

	public function db2() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		if (!$v->checkInstallPw()) {
			return $v->getSiteURL();
		}
		
		$v->db_conns = $this->getDbConns();
		foreach($v->db_conns as $theDbConnInfo) {
			$theFormIdPrefix = $theDbConnInfo->myDbConnName;
			
			$theWidgetName = $theFormIdPrefix.'_table_prefix';
			$v->strip_spaces($theWidgetName);
			
			$theWidgetName = $theFormIdPrefix.'_dns_scheme';
			if (!empty($theDbConnInfo->dbConnOptions->dns_scheme)) {
				$v->$theWidgetName = $theDbConnInfo->dbConnOptions->dns_scheme;
			}
			$theDnsScheme = $v->$theWidgetName;
			switch ($theDnsScheme) {
				case DbConnOptions::DB_CONN_SCHEME_INI:
					$theWidgetName = $theFormIdPrefix.'_dns_alias';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_uri';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_custom';
					unset($v->$theWidgetName);
					break;
				case DbConnOptions::DB_CONN_SCHEME_ALIAS:
					$theWidgetName = $theFormIdPrefix.'_dns_alias';
					$v->strip_spaces($theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_uri';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_custom';
					unset($v->$theWidgetName);
					//some browsers auto-fill this field, unset if not using "ini" scheme
					$theWidgetName = $theFormIdPrefix.'_dbpwrd';
					unset($v->$theWidgetName);
					break;
				case DbConnOptions::DB_CONN_SCHEME_URI:
					$theWidgetName = $theFormIdPrefix.'_dns_alias';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_uri';
					$v->strip_spaces($theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_custom';
					unset($v->$theWidgetName);
					//some browsers auto-fill this field, unset if not using "ini" scheme
					$theWidgetName = $theFormIdPrefix.'_dbpwrd';
					unset($v->$theWidgetName);
					break;
				default:
					$theWidgetName = $theFormIdPrefix.'_dns_alias';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_uri';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_custom';
					$v->strip_spaces($theWidgetName);
					//some browsers auto-fill this field, unset if not using "ini" scheme
					$theWidgetName = $theFormIdPrefix.'_dbpwrd';
					unset($v->$theWidgetName);
			}//switch
			$theVarList = array(
					'table_prefix' => $theFormIdPrefix.'_table_prefix',
					'dns_scheme' => $theFormIdPrefix.'_dns_scheme',
					'dns_alias' => $theFormIdPrefix.'_dns_alias',
					'dns_uri' => $theFormIdPrefix.'_dns_uri',
					'dns_custom' => $theFormIdPrefix.'_dns_custom',
					'dbhost' => $theFormIdPrefix.'_dbhost',
					'dbtype' => $theFormIdPrefix.'_dbtype',
					'dbname' => $theFormIdPrefix.'_dbname',
					'dbuser' => $theFormIdPrefix.'_dbuser',
					'dbpwrd' => $theFormIdPrefix.'_dbpwrd',
			);
			$theDbConnFilePath = strtolower($theDbConnInfo->dbConnOptions->ini_filename);
			if (!empty($theDbConnFilePath)) {
				if (!Strings::endsWith($theDbConnFilePath, '.ini'))
					$theDbConnFilePath .= '.ini';
			} else {
				$theDbConnFilePath = 'dbconn-webapp.ini';
			}
			$theDbConnFilePath = BITS_CFG_PATH.$theDbConnFilePath;
			if ($dst = $this->installTemplate('dbconn-webapp', $theDbConnFilePath, $theVarList)) {
				//copy completed, now try to connect to the db and prove it works
				try {
					$theDbConnInfo->loadDbConnInfoFromIniFile($theDbConnFilePath);
					$v->connected = $theDbConnInfo->getPDOConnection();
					$v->next_action = $v->getSiteURL('install/auth1');
				} catch (PDOException $e) {
					$ex = new DbException($e,$theDbConnFilePath.' caused: ');
					$ex->setCssFileUrl(BITS_RES.'/style/bits.css')->setFileRoot(realpath(BITS_ROOT));
					$v->next_action = $v->getSiteURL('install/db1');
					$v->connected = false;
					$v->_dbError = $ex->getDebugDisplay('Connection error');
				}
				if (empty($v->connected)) {
					if (empty($v->do_not_delete_failed_config)) {
						//if db connection failed, delete the file so it can be attempted again
						$v->permission_denied = !unlink($dst);
					}
					break; //break out of foreach loop
				}
			} else {
				$v->permission_denied = true;
			}
		}//foreach
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
		if (!$this->director->getProp('Auth')) {
			$this->scene->next_action = $this->scene->getSiteURL('install/auth2');
			$this->scene->auth_types = $this->getAuthTypes();
		} else {
			return $this->scene->getSiteURL('install/setupDb');
		}
	}

	protected function installAuth($aAuthType) {
		//copy the auth type class out of lib/authtype into app/model
		$src = BITS_RES_PATH.'templates'.¦.'Auth_'.$aAuthType.'.tpl';
		$dst = BITS_APP_PATH.'models'.¦.'Auth.php';
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
		//$this->scene->next_action = $this->scene->getSiteURL('install/siteid');
		//since AuthGroups are now editable along with Reg Codes, modifiable site id is not important
		$this->scene->next_action = $this->scene->getSiteURL('install/allFinished');
		//auth and db configs are installed, lets create our database
		$theSetupDb = $this->getProp('SetupDb');
		try {
			$theSetupDb->setupModels($this->scene);
		} catch (DbException $dbe) {
			$dbe->setCssFileUrl(BITS_RES.'/style/bits.css')->setFileRoot(realpath(BITS_ROOT));
			$this->scene->_dbError = $dbe->getDebugDisplay();
			$this->scene->popDbResults();
		}
		$this->returnProp($theSetupDb);
	}
	
	/**
	 * URL: %site%/install/resetup_db
	 */
	public function resetupDb() {
		if (!$this->director->isInstalled()) return $this->scene->getSiteURL();
		if (!$this->isAllowed('config','modify')) return $this->scene->getSiteURL();
		//lets re-create our database
		$theSetupDb = $this->getProp('SetupDb');
		$theSetupDb->setupModels($this->scene);
		$this->returnProp($theSetupDb);
		return $this->scene->getSiteURL('install/allFinished');
	}
	
	public function siteid() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->site_id = $this->director->app_id;
		$this->scene->next_action = $this->scene->getSiteURL('install/allFinished');
	}
	
	protected function installSettings($aNewAppId) {
		$this->scene->app_id = (!empty($aNewAppId)) ? $aNewAppId : $this->director->app_id;
		$this->scene->landing_page = $this->getRes('install/landing_page');
		$theVarNames = array('app_id', 'landing_page');
		return $this->installTemplate('Settings', BITS_CFG_PATH.'Settings.php', $theVarNames)
				//&& $this->installTemplate('MenuInfo', BITS_RES_PATH.'MenuInfo.php', $theVarNames)
				;
	}

	public function allFinished() {
		if (!$this->scene->checkInstallPw()) return $this->scene->getSiteURL();
		$this->scene->next_action = $this->scene->getSiteURL('config');
		//do something to signify finished
		if ($this->installSettings($this->scene->site_id)) {
			//we are now considered "installed and ready to go!" except we are beyond the
			//  point at which we load an actor's config property, manually load it now
			$this->config = $this->director->getProp('Config');
			//see where to go from here by reading config settings
			$dbAccounts = $this->getProp('Accounts');
			if ($dbAccounts->isEmpty()) {
				$this->scene->next_action = $this->scene->getSiteURL($this->getConfigSetting('auth/register_url'));
				$dbAuth = $this->getProp('Auth');
				if (!empty($dbAuth) && !$dbAuth->isRegistrationAllowed()) {
					$this->scene->next_action = $this->scene->getSiteURL($this->getConfigSetting('auth/login_url'));
				}
				$this->returnProp($dbAuth);
			}
			$this->returnProp($dbAccounts);
			//$this->debugLog('allFin, next='.$this->scene->next_action.' cfg='.$this->debugStr($this->config));
		} else {
			$this->scene->permission_denied = true;
		}
	}
	
	public function resetDb($pw) {
		//debug function, does nothing now
	}
	
}//end class

}//end namespace

