<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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
use BitsTheater\models\Auth as AuthDB;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\WornForSqlSanitize;
use BitsTheater\costumes\AuthAccount;
use com\blackmoonit\Widgets;
{//namespace begin

class AuthOrgAccount extends BaseScene
implements ISqlSanitizer
{
	use WornForSqlSanitize;
	
	public $jsCode = ''; //used in account list page render

	public function getUsernameKey()
	{ return AuthDB::KEY_userinfo; }
	
	public function getUsername()
	{ return $this->{$this->getUsernameKey()}; }
	
	public function getPwInputKey()
	{ return AuthDB::KEY_pwinput; }
	
	public function getPwInput()
	{ return $this->{$this->getPwInputKey()}; }
	
	public function getUseCookieKey()
	{ return AuthDB::KEY_cookie; }
	
	public function getUseCookie()
	{ return $this->{$this->getUseCookieKey()}; }
	
	/**
	 * @return string[] Returns the array of defined fields available.
	 */
	static public function getDefinedFields()
	{ return AuthAccount::getDefinedFields() ; }
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	public function isFieldSortable($aFieldName)
	{
		switch ($aFieldName) {
			case 'account_id':
			case 'account_name':
			case 'external_id':
			case 'auth_id':
			case 'email':
			case 'verified_ts':
			case 'is_active':
			case 'last_seen_ts':
				return true;
			default:
				return parent::isFieldSortable($aFieldName);
		}//end switch
	}
	
	/**
	 * Return the default columns to sort by and whether or not they should be ascending.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	public function getDefaultSortColumns()
	{ return array( 'account_name' => true ) ; }
	
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
			case 'edit_button':  return '';
			default:             return parent::getColHeaderLabel($aFieldName);
		}//end switch
	}
	
	/**
	 * Helper function to obtain specific field cell HTML for the display table.
	 * @param string $aFieldName - the fieldname of the data to display.
	 * @param object $aDataRow - the object containing the field data.
	 * @return string Returns the HTML for the table cell inside &lt;td&gt; tags.
	 */
	public function getColCellValue($aFieldName, $aDataRow)
	{
		switch ($aFieldName) {
			case 'edit_button':
				$w = '<td>';
				$w .= Widgets::buildButton()->addClass('btn-sm')->addClass('btn-default')
						->addClass('btn_edit_account')
						->setDataAttr('auth_id', $aDataRow->auth_id)
						->setDataAttr('account_id', $aDataRow->account_id)
						->setDataAttr('account_name', htmlentities($aDataRow->account_name))
						->setDataAttr('email', htmlentities($aDataRow->email))
						->setDataAttr('is_active', ($aDataRow->is_active) ? '1' : '0' )
						->setDataAttr('groups', htmlentities(json_encode($aDataRow->groups)))
						->append('<span class="glyphicon glyphicon-pencil"></span>')
						->render()
						;
				$w .= '</td>';
				return $w;
			case 'account_id':
				return '<td style="align:center">'.$aDataRow->account_id.'</td>';
			case 'account_name':
				return '<td>'.htmlentities($aDataRow->account_name).'</td>';
			case 'external_id':
				return '<td>'.$aDataRow->external_id.'</td>';
			case 'auth_id':
				return '<td>'.$aDataRow->auth_id.'</td>';
			case 'email':
				return '<td>'.htmlentities($aDataRow->email).'</td>';
			case 'verified_ts':
				return '<td>'.$this->getLocalTimestampValue($aDataRow->verified_ts).'</td>';
			case 'is_active':
		 		$w = '<td style="align:center">';
		 		$w .= $aDataRow->is_active
						? $this->getRes('account/label_is_active_true')
						: $this->getRes('account/label_is_active_false')
				;
				$w .= '</td>';
				return $w;
			default:
				return '<td>'.parent::getColCellValue($aFieldName, $aDataRow).'</td>';
		}//end switch
	}

}//end class

}//end namespace
