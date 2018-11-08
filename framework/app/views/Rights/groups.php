<?php
use BitsTheater\scenes\Rights as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use BitsTheater\models\AuthGroups as AuthGroupsDB;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;

$h = $v->cueActor('Fragments', 'get', 'csrf_header_jquery');
$recite->includeMyHeader($h);
$w = '';

$theParentGroups = array();
foreach ($v->groups as $gid => $row) {
	if ( !empty($gid) && $gid != AuthGroupsDB::UNREG_GROUP_ID) {
		$theParentGroups[$gid] = $row['group_name'];
	}
}
$theParentGroupsJS = json_encode($theParentGroups, JSON_FORCE_OBJECT);
$jsCode = <<<EOD
$(document).ready(function(){
	var rg = new BitsRightGroups('{$v->getSiteURL('rights/ajajSaveGroup')}',{$theParentGroupsJS}).setup();
});

EOD;

print($v->cueActor('Fragments', 'get', 'permissions-dialog_group'));

$w .= "<h1>{$v->getRes('permissions/title_groups')}</h1>";

$w .= $v->renderMyUserMsgsAsString();
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>' . "<br />\n";
}

$w .= '<div class="panel panel-default">';
//$w .= '<div class="panel-heading">Panel heading</div>';

$w .= '<table class="table">';
$w .= '<thead><tr class="rowh">';
$w .= '<th></th>';
$w .= "<th>{$v->getColHeaderLabel('group_num')}</th>";
$w .= "<th>{$v->getColHeaderLabel('group_name')}</th>";
$w .= "<th>{$v->getColHeaderLabel('parent_group_id')}</th>";
$w .= "<th>{$v->getRes('permissions/colheader_group_reg_code')}</th>";
$w .= '</tr></thead>'."\n";
$w .= "<tbody>\n";
foreach ($v->groups as $theGroupID => $theGroup) {
	$r = '<tr class="'.$v->_rowClass.'">';
	
	$r .= '<td>';
	$o = Widgets::buildButton()
			->addClass('btn-default')->addClass('btn-sm')->addClass('btn_edit_group')
			->setDataAttr('group_id', $theGroup['group_id'])
			->setDataAttr('group_num', $theGroup['group_num'])
			->setDataAttr('group_name', $theGroup['group_name'])
			->setDataAttr('group_parent', $theGroup['parent_group_id'])
			;
	if ( !empty($v->group_reg_codes[$theGroupID]) && !empty($v->group_reg_codes[$theGroupID]['reg_code']) ) {
		$o->setDataAttr('group_reg_code', $v->group_reg_codes[$theGroupID]['reg_code']);
	}
	$o->append('<span class="glyphicon glyphicon-pencil"></span>');
	$r .= $o->render();
	$r .= '</td>';
	
	$r .= '<td>'.$theGroup['group_num'].'</td>';
	
	$theLink = '<a href="'.BITS_URL.'/rights/group/'.$theGroupID.'">'.htmlentities($theGroup['group_name']).'</a>';
	$r .= '<td>'.$theLink.'</td>';

	$theGroupDesc = '';
	if ($theGroupID==AuthGroupsDB::UNREG_GROUP_ID) {
		$theGroupDesc = $v->getRes('permissions/display_group_0_desc');
	}
	if ( !empty($theGroup['parent_group_id']) ) {
		$s = Strings::format($v->getRes('permissions/display_parent_group'),
				$v->groups[$theGroup['parent_group_id']]['group_name']
		);
		if (!empty($theGroupDesc)) {
			$theGroupDesc .= ' ('.$s.')';
		} else {
			$theGroupDesc .= $s;
		}
	}
	$r .= '<td>'.htmlentities($theGroupDesc).'</td>';
	
	$r .= '<td>';
	if ( !empty($v->group_reg_codes[$theGroupID]) && !empty($v->group_reg_codes[$theGroupID]['reg_code']) ) {
		$r .= htmlentities($v->group_reg_codes[$theGroupID]['reg_code']);
	}
	$r .= '</td>';
	
	$r .= "</tr>\n";
	$w .= $r;
}//end foreach
$w .= "  </tbody>\n";
$w .= "</table><br/>\n";

$w .= '</div>'."\n";

$btn = Widgets::buildButton('btn_add_group')->addClass('btn-primary')
		->append($v->getRes('permissions/label_button_add_role'));
if (!$v->isAllowed('auth','create'))
	$btn->addClass('invisible');
$w .= $btn->render();

print($w);
print($v->createJsTagBlock($jsCode));
print(str_repeat('<br />',8));
$recite->includeMyFooter();
