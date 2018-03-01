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
use BitsTheater\Scene as MyScene;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\WornForSqlSanitize;
use BitsTheater\costumes\AuthGroup;
use com\blackmoonit\Widgets;
{//namespace begin

class Permissions extends MyScene implements ISqlSanitizer
{
	use WornForSqlSanitize;
	
	
	protected function setupDefaults() {
		parent::setupDefaults();
		$this->redirect = BITS_URL.'/rights';
		$this->groups = array();
		$this->rights = null;
		$this->right_groups = null;
		$theText = $this->getRes('generic/save_button_text');
		$this->save_button = '<br/>'.Widgets::createSubmitButton('submit_save',$theText)."\n";
	}
	
	/**
	 * Use "namespace" to retrieve all the different namespaces for permissions.
	 */
	public function getPermissionRes($aNamespace) {
		return $this->getRes('Permissions/'.$aNamespace);
	}
	
	public function getRightValues() {
		$res = $this->getPermissionRes('right_values');
		$theResult = array();
		foreach ($res as $key => $keyInfo) { //allow, disallow, deny
			$theResult[$key] = $keyInfo->label;
		}
		return $theResult;
	}
	
	public function getShortRightValues() {
		return array('allow'=>'+','disallow'=>'-','deny'=>'x');
	}
	
	public function getRightValue($aAssignedRights, $aNamespace, $aRightName) {
		$theResult = 'disallow';
		if (!empty($aAssignedRights[$aNamespace]) && !empty($aAssignedRights[$aNamespace][$aRightName]))
			$theResult = $aAssignedRights[$aNamespace][$aRightName];
		return $theResult;
	}
	
	/**
	 * @return string[] Returns the array of defined fields available.
	 */
	static public function getDefinedFields()
	{ return AuthGroup::getDefinedFields() ; }
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	public function isFieldSortable($aFieldName)
	{
		switch ($aFieldName) {
			case 'group_id':
			case 'group_name':
			case 'parent_group_id':
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
	{ return array( 'group_name' => true, 'group_id' => true ) ; }
	
	/**
	 * Returns the human label used for a field.
	 * @param string $aFieldName - one of the property names defined
	 *     for AuthAccount costume.
	 */
	public function getColHeaderLabel($aFieldName)
	{
		switch ($aFieldName) {
			case 'group_id':        return $this->getRes('AuthGroup/colheader_group_id');
			case 'group_name':      return $this->getRes('AuthGroup/colheader_group_name');
			case 'parent_group_id': return $this->getRes('AuthGroup/colheader_parent_group_id');
			default:                return parent::getColHeaderLabel($aFieldName);
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
			case 'group_id':
				return '<td style="align:center">'.$aDataRow->group_id.'</td>';
			case 'group_name':
				return '<td>'.htmlentities($aDataRow->group_name).'</td>';
			case 'parent_group_id':
				return '<td style="align:center">'.$aDataRow->parent_group_id.'</td>';
			default:
				return '<td>'.parent::getColCellValue($aFieldName, $aDataRow).'</td>';
		}//end switch
	}
	
}//end class

}//end namespace
