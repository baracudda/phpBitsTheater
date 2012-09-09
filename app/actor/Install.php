<?php
namespace com\blackmoonit\bits_theater\app\actor; 
use com\blackmoonit\bits_theater\app\Actor;
use com\blackmoonit\bits_theater\app\DbException;
use com\blackmoonit\Strings;
use com\blackmoonit\database\DbUtils;
{//namespace begin

class Install extends Actor {
	const DEFAULT_ACTION = 'install';
	
	protected function getDefinedPw($aPwFile) {
		$thePW = BITS_ROOT; //default pw is the folder path
		if (file_exists($aPwFile)) {
			//load first line as pw
			$thePW = trim(file_get_contents($aPwFile));
		}
		//Strings::debugLog('file:'.$aPwFile.', '.$thePW);
		return $thePW;
	}
		
	protected function checkInstallPw() {
		//check to see if posted the correct pw
		$theDefinedPw = $this->getDefinedPw($this->scene->INSTALL_PW_FILENAME);
		$theInputPw = $this->scene->installpw;
		//Strings::debugLog('d:'.$this->director['installpw'].' s:'.$_SESSION['installpw'].' p:'.$_POST['installpw']);
		//Strings::debugLog('sess:'.session_id().':'.$this->director['installpw'].' s:'.$_SESSION['installpw']);
		//Strings::debugLog('args:'.$theDefinedPw.', '.$theInputPw.', '.$this->scene->installpw);
		if ($theDefinedPw==$theInputPw) 
			return $theDefinedPw;
		else 
			return false;
	}
	
	protected function installTemplate($aDestPath, $aTemplateName, $aNewExtension, $aVars) {
		//copy the .tpl to .php and fill in the vars
		$dst = $aDestPath.$aTemplateName.$aNewExtension;
		if (file_exists($dst))
			return $dst;
		$src = BITS_RES_PATH.'templates'.DIRECTORY_SEPARATOR.$aTemplateName.'.tpl';
		$tpl = file_get_contents($src);
		if ($tpl) {
			foreach ($aVars as $theVarName) {
				$tpl = str_replace('%'.$theVarName.'%',$this->scene->$theVarName,$tpl);
			}
			if (file_put_contents($dst,$tpl)) {
				return $dst;
			}
		}
		return false;
	}

	protected function installConfigTpl($aTemplateName, $aNewExtension, $aVars) {
		return $this->installTemplate(BITS_APP_PATH.'config'.¦,$aTemplateName.$aNewExtension,$aVars);
	}

	public function install() {
		//avoid installing more than once
		if ($this->director->canConnectDb() && $this->director->canCheckTickets() && $this->director->isInstalled()) {
			try {
				$config = $this->director->getProp('Config');
				$b = $config->exists();
				$this->director->returnProp($config);
				if ($b)
					return $this->getHomePage();
			} catch (DbException $dbe) {
				return $this->getHomePage();
			}
		}	
	
		//make sure we start off with a fresh session
		$this->director->resetSession();
		
		//ask for something only installer would know, like detail of install file that cannot be accessed from web
		
		//next action in the install sequence
		if (!$this->director->canGetRes())
			$this->scene->next_action = BITS_URL.'/install/lang1';
		elseif (!$this->director->canConnectDb())
			$this->scene->next_action = BITS_URL.'/install/db1';
		elseif (!$this->director->canCheckTickets())
			$this->scene->next_action = BITS_URL.'/install/auth1';
		else
			$this->scene->next_action = BITS_URL.'/install/setupDb';
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
					$theResUrl = BITS_URL.'/res/i18n/'.$theKey;
					$theValue = str_replace('%path%',$theResUrl,file_get_contents($theValueFile));
					$theTypes[$theKey] = $theValue;
				}
			}
		}
		return $theTypes;
	}

	public function lang1() {
		if (!$this->checkInstallPw()) return BITS_URL;
		$this->scene->next_action = BITS_URL.'/install/lang2';
		$this->scene->lang_types = $this->getLangTypes();
	}
	
	protected function installLang($aLangType) {
		$theVarNames = array('lang','region','path_lang','path_region',
				'default_lang','default_region','default_path_lang','default_path_region');
		$sa = explode('/',$this->scene->lang_type);
		$this->scene->lang = $sa[0];
		$this->scene->region = $sa[1];
		$this->scene->path_lang = BITS_RES_PATH.'i18n'.¦.$this->scene->lang.¦;
		$this->scene->path_region = $this->scene->path_lang.¦.$this->scene->region.¦;
		$this->scene->default_lang = 'en';
		$this->scene->default_region = 'US';
		$this->scene->default_path_lang = BITS_RES_PATH.'i18n'.¦.$this->scene->default_lang.¦;
		$this->scene->default_path_region = $this->scene->default_path_lang.¦.$this->scene->default_region.¦;
		
		return $this->installConfigTpl('I18N','.php',$theVarNames);
	}
	
	public function lang2() {
		if (!$this->checkInstallPw()) return BITS_URL;
		$this->scene->lang_types = $this->getLangTypes();
		$this->scene->permission_denied = !$this->installLang($this->scene->lang_type);
		if ($this->scene->permission_denied) {
			$this->scene->next_action = BITS_URL.'/install/lang1';
		} else {
			$this->scene->next_action = BITS_URL.'/install/db1';
		}
	}
	
	public function db1() {
		if (!$this->checkInstallPw()) return BITS_URL;
		$this->scene->next_action = BITS_URL.'/install/db2';
		$this->scene->db_types = $this->scene->getDbTypes();
	}

	public function db2() {
		if (!$this->checkInstallPw()) return BITS_URL;

		$this->scene->strip_spaces('table_prefix');
		$theVarNames = array('table_prefix','dns_scheme','dns_value','dbhost','dbtype','dbname','dbuser','dbpwrd');
		if ($dst = $this->installConfigTpl('_dbconn_','.ini',$theVarNames)) {
			//copy completed, now try to connect to the db and prove it works
			try {
				$this->scene->connected = DbUtils::getPDOConnection(DbUtils::readDbConnInfo(BITS_DB_INFO));
				$this->scene->next_action = BITS_URL.'/install/auth1';
			} catch (\PDOException $e) {
				$this->scene->next_action = BITS_URL.'/install/db1';
				$this->scene->connected = false;
				$this->scene->_dbError = $e->getDebugDisplay('Connection error');
				$this->scene->old_vals = $this->scene->createHiddenPosts(array('dns_scheme', 'dns_value', 
						'table_prefix','dbhost','dbtype','dbname','dbuser','dbpwrd'));
			}
			if (empty($this->scene->connected)) {
				//if dbconn failed, delete the file so it can be attempted again
				$this->scene->permission_denied = !unlink($dst);
			}
		} else {
			$this->scene->permission_denied = true;
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
		if (!$this->checkInstallPw()) return BITS_URL;
		$this->scene->next_action = BITS_URL.'/install/auth2';
		$this->scene->auth_types = $this->getAuthTypes();
	}

	protected function installAuth($aAuthType) {
		//copy the auth type class out of lib/authtype into app/model
		$src = BITS_RES_PATH.'templates'.¦.'Auth_'.$aAuthType.'.tpl';
		$dst = BITS_APP_PATH.'model'.¦.'Auth.php';
		return copy($src,$dst);
	}
	
	public function auth2() {
		if (!$this->checkInstallPw()) return BITS_URL;
		$this->scene->next_action = BITS_URL.'/install/setupDb';
		$this->scene->permission_denied = !$this->installAuth($this->scene->auth_type);
		if ($this->director->canCheckTickets()) {
			$this->scene->auth_model = $this->director->getProp('Auth');
			$this->scene->auth_install_options = $this->scene->auth_model->renderInstallOptions($this);
		}
	}
	
	public function setupDb() {
		if (!$this->checkInstallPw()) return BITS_URL;
		$this->scene->next_action = BITS_URL.'/install/allFinished';
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
	
	protected function installSettings($aNewAppId) {
		$this->scene->app_id = $aNewAppId;
		$theVarNames = array('app_id');
		return $this->installConfigTpl('Settings','.php',$theVarNames) &&
				$this->installTemplate(BITS_RES_PATH,'MenuInfo','.php',$theVarNames);
	}

	public function allFinished() {
		if (!$this->checkInstallPw()) return BITS_URL;
		$this->scene->next_action = BITS_URL.'/rights';
		//do something to signify finished
		if ($this->installSettings(Strings::createGUID())) {
			//see where to go from here
			$accounts = $this->getProp('Accounts');
			if ($accounts->isEmpty()) {
				$dbAuth = $this->getProp('Auth');
				if ($dbAuth->ALLOW_REGISTRATION) {
					$this->scene->next_action = $this->config['auth/register_url'];
				} else {
					$this->scene->next_action = $this->config['auth/login_url'];
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

