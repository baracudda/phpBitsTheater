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

namespace BitsTheater\scenes\Closet;
use BitsTheater\Scene as BaseScene;
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\costumes\venue\TicketViaInstallPw;
use com\blackmoonit\database\DbConnOptions;
use com\blackmoonit\Widgets;
{//namespace begin

class Install extends BaseScene
{
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
	}
	
	/**
	 * Check to see if we have a valid install passphrase submitted.
	 * @return boolean Returns TRUE if input matches the defined passphrase.
	 */
	public function checkInstallPw()
	{ return TicketViaInstallPw::checkInstallPwVsInput($this->installpw); }
	
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
					'table_prefix' => $this->getRes('install/validate_table_prefix'),
					'dbhost' => $this->getRes('install/validate_dbhost'),
					'dbname' => $this->getRes('install/validate_dbname'),
					'dbuser' => $this->getRes('install/validate_dbuser'),
					'dbpwrd' => $this->getRes('install/validate_dbpwrd'),
			),
		);
	}
	
	public function getDbTypes() {
		//return $this->getRes('install/db_types');
		$theList = \PDO::getAvailableDrivers();
		return array_combine($theList,$theList);
	}
	
	public function getDnsWidgets(DbConnInfo $aDbConnInfo, $aScene) {
		/* @var $v Install */
		$v =& $this;
		$theDnsScheme = $aDbConnInfo->dbConnOptions->dns_scheme;
		if (empty($theDnsScheme))
			return;
		$theFormIdPrefix = $aDbConnInfo->myDbConnName;
		$w = '';
		switch ($theDnsScheme) {
			case DbConnOptions::DB_CONN_SCHEME_INI:
				$w .= '<table class="db-entry" id="'.$theFormIdPrefix.'">';

				$w .= '<tr>';
				$theWidgetName = $theFormIdPrefix.'_dbhost';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnSettings->host))
					$v->$theWidgetName = $aDbConnInfo->dbConnSettings->host;
				$w .= '<td class="db-field-label">'.$this->getRes('install/label_dns_dbhost').': </td>';
				$theInput = Widgets::buildTextBox($theWidgetName)->setSize(30)
						->setPlaceholder($this->getRes('install','validate_dbhost'))
						->setContent($v->$theWidgetName)
						->setRequired(true)
						->renderInline();
				$w .= '<td>' . $theInput . '</td>';
				$w .= "</tr>\n";

				$w .= '<tr>';
				$theWidgetName = $theFormIdPrefix.'_dbtype';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnSettings->driver))
					$v->$theWidgetName = $aDbConnInfo->dbConnSettings->driver;
				$w .= '<td class="db-field-label">'.$this->getRes('install/label_dns_dbtype').': </td>';
				$theInput = Widgets::buildDropDown($theWidgetName)
						->setOptions($v->getDbTypes())->setSelectedValue($v->$theWidgetName)
						//->setPlaceholder($this->getRes('install','validate_dbtype'))
						->regenerate()
						->renderInline();
				$w .= '<td>' . $theInput . '</td>';
				$w .= "</tr>\n";
				
				$w .= '<tr>';
				$theWidgetName = $theFormIdPrefix.'_dbname';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnSettings->dbname))
					$v->$theWidgetName = $aDbConnInfo->dbConnSettings->dbname;
				$w .= '<td class="db-field-label">'.$this->getRes('install/label_dns_dbname').': </td>';
				$theInput = Widgets::buildTextBox($theWidgetName)->setSize(30)
						->setPlaceholder($this->getRes('install','validate_dbname'))
						->setContent($v->$theWidgetName)
						->setRequired(true)
						->renderInline();
				$w .= '<td>' . $theInput . '</td>';
				$w .= "</tr>\n";
				
				$w .= '<tr>';
				$theWidgetName = $theFormIdPrefix.'_dbuser';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnSettings->username))
					$v->$theWidgetName = $aDbConnInfo->dbConnSettings->username;
				$w .= '<td class="db-field-label">'.$this->getRes('install/label_dns_dbuser').': </td>';
				$theInput = Widgets::buildTextBox($theWidgetName)->setSize(30)
						->setPlaceholder($this->getRes('install','validate_dbuser'))
						->setContent($v->$theWidgetName)
						->renderInline();
				$w .= '<td>' . $theInput . '</td>';
				$w .= "</tr>\n";
				
				$w .= '<tr>';
				$theWidgetName = $theFormIdPrefix.'_dbpwrd';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnSettings->password))
					$v->$theWidgetName = $aDbConnInfo->dbConnSettings->password;
				$w .= '<td class="db-field-label">'.$this->getRes('install/label_dns_dbpwrd').': </td>';
				$theInput = Widgets::buildPassBox($theWidgetName)->setSize(30)
						->setPlaceholder($this->getRes('install','validate_dbpwrd'))
						->setContent($v->$theWidgetName)
						->renderInline();
				$w .= '<td>' . $theInput . '</td>';
				$w .= "</tr>\n";

				$w .= '<tr>';
				$theWidgetName = $theFormIdPrefix.'_admin_dbuser';
				$w .= '<td class="db-field-label">'.$this->getRes('install','label_dns_admin_dbuser').': </td>';
				$theInput = Widgets::buildTextBox($theWidgetName)->setSize(30)
						->setPlaceholder($this->getRes('install','validate_admin_dbuser'))
						->setContent($v->$theWidgetName)
						->renderInline();
				$w .= '<td>' . $theInput . '</td>';
				$w .= "</tr>\n";
				
				$w .= '<tr>';
				$theWidgetName = $theFormIdPrefix.'_admin_dbpwrd';
				$w .= '<td class="db-field-label">'.$this->getRes('install','label_dns_admin_dbpwrd').': </td>';
				$theInput = Widgets::buildPassBox($theWidgetName)->setSize(30)
						->setPlaceholder($this->getRes('install','validate_admin_dbpwrd'))
						->setContent($v->$theWidgetName)
						->renderInline();
				$w .= '<td>' . $theInput . '</td>';
				$w .= "</tr>\n";
				
				$w .= "</table>\n";
				break;
			case DbConnOptions::DB_CONN_SCHEME_ALIAS:
				$theWidgetName = $theFormIdPrefix.'_dns_alias';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnOptions->dns_value))
					$v->$theWidgetName = $aDbConnInfo->dbConnOptions->dns_value;
				$w .= $this->getRes('install/label_dns_alias').': '.Widgets::createTextBox($theWidgetName,$v->$theWidgetName)."<br/>\n";
				break;
			case DbConnOptions::DB_CONN_SCHEME_URI:
				$theWidgetName = $theFormIdPrefix.'_dns_uri';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnOptions->dns_value))
					$v->$theWidgetName = $aDbConnInfo->dbConnOptions->dns_value;
				$w .= $this->getRes('install/label_dns_uri').': '.Widgets::createTextBox($theWidgetName,$v->$theWidgetName)."<br/>\n";
				break;
			default:
				$theWidgetName = $theFormIdPrefix.'_dns_custom';
				if (empty($v->$theWidgetName) && !empty($aDbConnInfo->dbConnOptions->dns_value))
					$v->$theWidgetName = $aDbConnInfo->dbConnOptions->dns_value;
				$w .= $this->getRes('install/label_dns_custom').': '.Widgets::createTextBox($theWidgetName,$v->$theWidgetName)."<br/>\n";
				break;
		}//switch
		return $w;
	}
	
}//end class

}//end namespace
