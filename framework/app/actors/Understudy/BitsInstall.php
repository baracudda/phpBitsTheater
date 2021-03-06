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
use BitsTheater\BrokenLeg;
use BitsTheater\scenes\Install as MyScene; /* @var $v MyScene */
use BitsTheater\costumes\APIResponse;
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\costumes\DbAdmin;
use BitsTheater\costumes\PropsMaster;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\database\DbConnOptions;
use com\blackmoonit\Strings;
use com\blackmoonit\FileUtils;
{//namespace begin

class BitsInstall extends BaseActor
{
	const DEFAULT_ACTION = 'install';

	/**
	 * {@inheritDoc}
	 * @return MyScene Returns a newly created scene descendant.
	 * @see \BitsTheater\Actor::createMyScene()
	 */
	protected function createMyScene($anAction)
	{ return new MyScene($this, $anAction); }

	/**
	 * Similar to file_put_contents, but forces all parts of the folder path to exist first.
	 * @param string $aDestFile - path and filename of destination.
	 * @param string $aFileContents - contents to be saved in $aDestFile.
	 * @return int|boolean Returns false on failure, else num bytes stored.
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

	/**
	 * First step in the install process. Try to prevent install from being run after install finishes.
	 * @param string $aOverride - sometimes install is not smooth; provide means to force db setup.
	 * @return null|string May return a redirect URL, otherwise NULL.
	 */
	public function install($aOverride=null) {
		//avoid installing more than once
		if ($this->director->canCheckTickets() && $this->director->isInstalled() && $aOverride!==$this->director->app_id) {
				return $this->getHomePage();
		}
		
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
	public function getDbConns()
	{
		$db_conns = array();
	
		$theDbConnInfo = DbConnInfo::asSchemeINI(PropsMaster::DB_CONN_NAME_FOR_AUTH);
		$theDbConnInfo->dbConnSettings->dbname = '';
		$theDbConnInfo->dbConnSettings->host = '';
		$theDbConnInfo->dbConnSettings->username = '';
		$db_conns[] = $theDbConnInfo;
	
		return $db_conns;
	}
	
	/**
	 * Wizard page dedicated to gathering the database connection information.
	 * @return null|string May return a redirect URL, otherwise NULL.
	 */
	public function db1()
	{
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		if ( !$v->checkInstallPw() ) return $v->getSiteURL();
		$v->next_action = $v->getSiteURL('install/db2');
		$v->db_types = $v->getDbTypes();
		$v->db_conns = $this->getDbConns();
	}
	
	/**
	 * Given the database connection form vars, test the connection(s) and
	 * save the info in our special files protected from being downloaded.
	 * @throws DbException if connection fails for whatever reason.
	 */
	protected function installDbConns()
	{
		$v = $this->getMyScene();
		$v->db_conns = $this->getDbConns();
		foreach ($v->db_conns as $theDbConnInfo) {
			/* @var $theDbConnInfo DbConnInfo */
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
					//supply default for optional settings
					$theWidgetName = $theFormIdPrefix.'_table_prefix';
					if (empty($v->$theWidgetName))
						$v->$theWidgetName = $theDbConnInfo->dbConnOptions->table_prefix;
					$theWidgetName = $theFormIdPrefix.'_dbtype';
					if (empty($v->$theWidgetName))
						$v->$theWidgetName = $theDbConnInfo->dbConnSettings->driver;
					//remove var that make no sense for this scheme
					$theWidgetName = $theFormIdPrefix.'_dns_alias';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_uri';
					unset($v->$theWidgetName);
					$theWidgetName = $theFormIdPrefix.'_dns_custom';
					unset($v->$theWidgetName);
					break;
				case DbConnOptions::DB_CONN_SCHEME_ALIAS:
					//remove var that make no sense for this scheme
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
					//remove var that make no sense for this scheme
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
					//remove var that make no sense for this scheme
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
			if ( !empty($theDbConnFilePath) ) {
				if ( !Strings::endsWith($theDbConnFilePath, '.ini') )
					$theDbConnFilePath .= '.ini';
			} else {
				$theDbConnFilePath = 'dbconn-webapp.ini';
			}
			$theDbConnFilePath = BITS_CFG_PATH . $theDbConnFilePath;
			if ( is_file($theDbConnFilePath) )
				continue; //skip to the next dbconn to be setup
			if ( $dst = $this->installTemplate('dbconn-webapp', $theDbConnFilePath, $theVarList) ) {
				$v->connected = false;
				//copy completed, now try to connect to the db and prove it works
				try {
					$theDbConnInfo->loadDbConnInfoFromIniFile($theDbConnFilePath);
					//test db conn
					if ( !$theDbConnInfo->getPDOConnection() ) {
						$this->logErrors(__METHOD__, $theDbConnFilePath . ' failed to connect.');
						throw new DbException(null, $theDbConnFilePath . ' failed to connect.');
					}
					$v->connected = true;
					//if connection works, see if we have DBA creds
					$theWidgetName = $theFormIdPrefix.'_admin_dbuser';
					$v->admin_dbuser = $v->$theWidgetName;
					$theWidgetName = $theFormIdPrefix.'_admin_dbpwrd';
					$v->admin_dbpass = $v->$theWidgetName;
					if ( !empty($v->admin_dbuser) && !empty($v->admin_dbpass) ) {
						$v->table_prefix = $theDbConnInfo->dbConnOptions->table_prefix;
						$v->dbname = $theDbConnInfo->dbConnSettings->dbname;
						$v->dbtype = $theDbConnInfo->dbConnSettings->driver;
						$v->dbhost = $theDbConnInfo->dbConnSettings->host;
						$v->dbport = $theDbConnInfo->dbConnSettings->port;
						$v->dbuser = $theDbConnInfo->dbConnSettings->username;
						$v->dbpass = base64_decode($theDbConnInfo->dbConnSettings->password);
						$v->dbcharset = $theDbConnInfo->dbConnSettings->charset;
						//$this->logStuff(__METHOD__, ' create user & db with v=', $v); //DEBUG
						$theDbAdmin = new DbAdmin($this);
						$theDbAdmin->createDbFromUserInput($v, $theDbConnInfo);
					}
				}
				catch ( \PDOException $pdox ) {
					$this->logErrors(__METHOD__, ' ', $pdox);
					throw new DbException($pdox, $theDbConnFilePath . ' failed to connect.');
				}
				finally {
					if ( !$v->connected ) {
						if ( !filter_var($v->do_not_delete_failed_config, FILTER_VALIDATE_BOOLEAN) ) {
							//if db connection failed, delete the file so it can be attempted again
							$v->permission_denied = !unlink($dst);
						}
					}
				}
			} else {
				$v->permission_denied = true;
			}
		}//foreach
	}

	/**
	 * Wizard page dedicated to testing the database connection information.
	 * @return null|string May return a redirect URL, otherwise NULL.
	 */
	public function db2()
	{
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		if ( !$v->checkInstallPw() ) return $v->getSiteURL();
		try {
			$this->installDbConns();
			$v->next_action = $v->getSiteURL('install/auth1');
		} catch ( DbException $dbx ) {
			$dbx->setCssFileUrl(BITS_RES.'/style/bits.css')->setFileRoot(realpath(BITS_ROOT));
			$v->next_action = $v->getSiteURL('install/db1');
			$v->connected = false;
			$v->_dbError = $dbx->getDebugDisplay('Connection error');
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
	
	/**
	 * Append a success message to the API reponse object.
	 * @param APIResponse $aApiResponse - the API response object.
	 * @param string $aSegment - the getRes('install', $aSegment) to use.
	 */
	protected function appendSuccessMsg(APIResponse $aApiResponse, $aSegment)
	{
		$theMsg = $this->getRes('install',
				'msg_install_segment_x_success',
				$this->getRes('install', $aSegment)
		);
		array_push($aApiResponse->data['messages'], $theMsg);
	}
	
	/**
	 * Append an "already done" message to the API reponse object.
	 * @param APIResponse $aApiResponse - the API response object.
	 * @param string $aSegment - the getRes('install', $aSegment) to use.
	 */
	protected function appendAlreadyDoneMsg(APIResponse $aApiResponse, $aSegment)
	{
		$theMsg = $this->getRes('install',
				'msg_install_segment_x_already_done',
				$this->getRes('install', $aSegment)
		);
		array_push($aApiResponse->data['messages'], $theMsg);
	}
	
	/**
	 * Part of the setupWebsite endpoint, install the language setting class.
	 * @param APIResponse $aApiResponse - the result object.
	 */
	protected function setupWebsite_language(APIResponse $aApiResponse) {
		$v =& $this->scene;
		$theDestFilePath = BITS_CFG_PATH.'I18N.php';
		if (filter_var($v->install_language_settings, FILTER_VALIDATE_BOOLEAN) &&
				!empty($v->lang_type))
		{
			if (!file_exists($theDestFilePath))
			{
				if (!$this->installLang($v->lang_type))
				{
					$theMsg = $this->getRes('install',
							'errmsg_forbidden_write_access', $theDestFilePath
					);
					throw BrokenLeg::pratfall('FORBIDDEN_WRITE_ACCESS', 403,
							str_replace(BITS_PATH, '[%site]', $theMsg)
					);
				}
				$this->appendSuccessMsg($aApiResponse, 'install_segment_language');
			}
			else {
				$this->appendAlreadyDoneMsg($aApiResponse, 'install_segment_language');
			}
		}
		
	}

	/**
	 * Part of the setupWebsite endpoint, install the auth model class.
	 * @param APIResponse $aApiResponse - the result object.
	 */
	protected function setupWebsite_auth_model(APIResponse $aApiResponse) {
		$v =& $this->scene;
		$theDestFilePath = BITS_APP_PATH.'models'.DIRECTORY_SEPARATOR.'Auth.php';
		if (filter_var($v->install_auth_model, FILTER_VALIDATE_BOOLEAN) &&
				isset($v->auth_type))
		{
			if (!file_exists($theDestFilePath))
			{
				if (!$this->installAuth($v->auth_type))
				{
					$theMsg = $this->getRes('install',
							'errmsg_forbidden_write_access', $theDestFilePath
					);
					throw BrokenLeg::pratfall('FORBIDDEN_WRITE_ACCESS', 403,
							str_replace(BITS_PATH, '[%site]', $theMsg)
					);
				}
				$this->appendSuccessMsg($aApiResponse, 'install_segment_auth');
			}
			else {
				$this->appendAlreadyDoneMsg($aApiResponse, 'install_segment_auth');
			}
		}
	}
	
	/**
	 * Part of the setupWebsite endpoint, install the database connection file(s).
	 * @param APIResponse $aApiResponse - the result object.
	 */
	protected function setupWebsite_db_conn_files(APIResponse $aApiResponse) {
		$v =& $this->scene;
		if (filter_var($v->install_db_conn_files, FILTER_VALIDATE_BOOLEAN))
		try {
			if (!$this->getDirector()->canConnectDb())
			{
				$this->installDbConns();
				$this->appendSuccessMsg($aApiResponse, 'install_segment_dbconn');
			}
			else {
				$this->appendAlreadyDoneMsg($aApiResponse, 'install_segment_dbconn');
			}
		} catch (DbException $dbe) {
			throw BrokenLeg::toss($this, BrokenLeg::ACT_DB_EXCEPTION, $dbe->getErrorMsg());
		}
	}
	
	/**
	 * Part of the setupWebsite endpoint, install the database.
	 * @param APIResponse $aApiResponse - the result object.
	 */
	protected function setupWebsite_database(APIResponse $aApiResponse) {
		$v =& $this->scene;
		if (filter_var($v->install_database, FILTER_VALIDATE_BOOLEAN))
		{
			if (!$this->getDirector()->canConnectDb())
				throw BrokenLeg::toss($this, BrokenLeg::ACT_DB_CONNECTION_FAILED);
			
			$theSetupDb = $this->getProp('SetupDb');
			$theSetupDb->setupModels($v);
			$this->appendSuccessMsg($aApiResponse, 'install_segment_create_db');
		}
	}
	
	/**
	 * Part of the setupWebsite endpoint, install the settings class file.
	 * The presence of this particular class is how the framework determines
	 * if a website is successfully installed or not.
	 * @param APIResponse $aApiResponse - the result object.
	 */
	protected function setupWebsite_settings_class(APIResponse $aApiResponse) {
		$v =& $this->scene;
		$theDestFilePath = BITS_CFG_PATH.'Settings.php';
		if (filter_var($v->install_settings_class, FILTER_VALIDATE_BOOLEAN))
		{
			if (!file_exists($theDestFilePath))
			{
				$theSiteId = (!empty($v->site_id)) ? $v->site_id : Strings::createUUID();
				if (!$this->installSettings($theSiteId))
				{
					$theMsg = $this->getRes('install',
							'errmsg_forbidden_write_access', $theDestFilePath
					);
					throw BrokenLeg::pratfall('FORBIDDEN_WRITE_ACCESS', 403,
							str_replace(BITS_PATH, '[%site]', $theMsg)
					);
				}
				$aApiResponse->data['site_id'] = $theSiteId;
				$this->appendSuccessMsg($aApiResponse, 'install_segment_settings_class');
			}
			else
			{
				$aApiResponse->data['site_id'] = $this->getDirector()->app_id;
				$this->appendAlreadyDoneMsg($aApiResponse, 'install_segment_settings_class');
			}
		}
	}
	
	/**
	 * Part of the setupWebsite endpoint, allows config settings to be updated automatically.
	 * @param APIResponse $aApiResponse - the result object.
	 */
	protected function setupWebsite_config_settings(APIResponse $aApiResponse) {
		$v =& $this->scene;
		if (filter_var($v->install_config_settings, FILTER_VALIDATE_BOOLEAN))
		{
			if (!$this->getDirector()->canConnectDb())
				throw BrokenLeg::toss($this, BrokenLeg::ACT_DB_CONNECTION_FAILED);
				
			$dbConfig = $this->getProp('Config');
			foreach ((array)$v->config_settings as $theConfigName => $theConfigValue) {
				$theOldValue = $dbConfig[$theConfigName]; //so it also loads the default in case value="?"
				$dbConfig[$theConfigName] = strval($theConfigValue);
				array_push($aApiResponse->data['messages'],
						$this->getRes('install', 'msg_config_x_updated', $theConfigName)
				);
				$theOldValue; //no-op to avoid warning of not using var
			}
		}
	}
	
	/**
	 * Part of the setupWebsite endpoint, install whatever else that needs to be done.
	 * @param APIResponse $aApiResponse - the result object.
	 */
	protected function setupWebsite_others(APIResponse $aApiResponse) {
		$v =& $this->scene;
		//descendants put stuff here
	}
	
	/**
	 * API to use if a bot is installing the website rather than a human via the page wizard.
	 */
	public function setupWebsite() {
		$v =& $this->scene;
		$this->viewToRender('results_as_json');
		try {
			if ($v->checkInstallPw())
			{
				$v->installpw = ''; //clear out the cached installpw since this is the only step
				$v->results = APIResponse::resultsWithData(array(
						'site_id' => null,
						'messages' => array(),
				));
				$this->setupWebsite_language($v->results);
				$this->setupWebsite_auth_model($v->results);
				$this->setupWebsite_db_conn_files($v->results);
				$this->setupWebsite_database($v->results);
				$this->setupWebsite_settings_class($v->results);
				$this->setupWebsite_config_settings($v->results);
				$this->setupWebsite_others($v->results);
				if (empty($v->results->data['messages'])) {
					throw BrokenLeg::pratfallRes($this, 'MISSING_VALUES', 400,
							'install/msg_install_segment_nothing_to_do');
				}
			}
			else {
				throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN);
			}
		} catch (BrokenLeg $bl) {
			//API calls need to eat the exception and give a sane HTTP Response
			$bl->setErrorResponse($v);
		} catch ( \Exception $x ) {
			$bl = BrokenLeg::tossException($this, $x);
			$bl->setErrorResponse($v);
		}
	}
	
}//end class

}//end namespace

