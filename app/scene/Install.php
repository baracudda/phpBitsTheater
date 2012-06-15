<?php
namespace app\scene; 
use app\Scene;
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
		
		$this->table_prefix = 'bits_';
		$this->dbhost = 'localhost';
		$this->dbtype = 'mysql';
		$this->dbname = '';
		$this->dbuser = '';
		$this->dbpwrd = '';
		
	}
	
	//jquery form validation rules - not really needed with html5
	public function validate_form_db1() {
		//using microsoft jquery validator
		return array(
			'rules' => array(
					'table_prefix' => 'required',
					'dbhost' => 'required',
					'dbname' => 'required',
					'dbuser' => 'required',
					'dbpwrd' => 'required',
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

