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

namespace com\blackmoonit\bits_theater\app\scene; 
use com\blackmoonit\bits_theater\app\Scene;
{//namespace begin

class Install extends Scene {
	const INSTALL_PW_FILENAME = 'install.pw';

	protected function setupDefaults() {
		parent::setupDefaults();
		$this->defineProperty('installpw',null,$this->on_set_session_var,$this->_director['installpw']);
		$this->permission_denied = false;
		$this->lang_type = 'en/US';
		if ($this->_director->canGetRes()) {
			$theText = $this->getRes('install/continue_button_text');
		} else {
			$theText = '&gt;&gt; Continue';
		}
		$this->continue_button = '<br/><input name="submit" type="submit" class="mainoption" style="font: bold 14px Arial" '.
				'value="'.$theText.'">'."\n";
		if ($this->_director->canGetRes()) {
			$theText = $this->getRes('install/back_button_text');
		} else {
			$theText = 'Back &lt;&lt;';
		}
		$this->back_button = '<br /><input name="submit" type="submit" class="mainoption" style="font: bold 14px Arial" '.
				'value="'.$theText.'">'."\n";
		
		$this->auth_type = 'Basic';
		
		$this->table_prefix = 'webapp_';
		$this->dbhost = 'localhost';
		$this->dbtype = 'mysql';
		$this->dbname = '';
		$this->dbuser = '';
		$this->dbpwrd = '';
		
	}
	
	protected function getDefinedPw() {
		$thePW = BITS_ROOT; //default pw is the folder path (since outsiders should not know it)
		$thePwFilePath = BITS_ROOT.¦.self::INSTALL_PW_FILENAME;
		if (file_exists($thePwFilePath)) {
			//load as pw
			$thePW = trim(file_get_contents($thePwFilePath));
		}
		//Strings::debugLog('file:'.$aPwFile.', '.$thePW);
		return $thePW;
	}
	
	public function checkInstallPw() {
		//check to see if posted the correct pw
		$theDefinedPw = $this->getDefinedPw();
		$theInputPw = $this->installpw;
		//print('args: '.$theDefinedPw.', '.$theInputPw.' = '.($theDefinedPw===$theInputPw?'true':'false')); exit;
		return ($theDefinedPw===$theInputPw);
	}
	
	//jquery form validation rules - not really needed with html5
	public function validate_form_db1() {
		//using microsoft jquery validator
		return array(
			'rules' => array(
					//'table_prefix' => 'required',
					'dbhost' => 'required',
					//'dbname' => 'required',
					//'dbuser' => 'required',
					//'dbpwrd' => 'required',
			),'messages' => array(
					'table_prefix' => 'A table prefix will prevent table name collisions.',
					'dbhost' => 'Where is the database located?',
					'dbname' => 'What is the database called?',
					'dbuser' => 'Who am I requesting data as?',
					'dbpwrd' => 'What is your quest?',
			),
		);
	}
	
	public function getDbTypes() {
		//return $this->getRes('install/db_types');
		$theList = \PDO::getAvailableDrivers();
		return array_combine($theList,$theList);
	}

}//end class

}//end namespace

