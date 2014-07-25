<?php
use BitsTheater\scenes\Rights as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= "<h2>{$v->getRes('permissions/title_groups')}</h2>";

$w .= "<br />\n";
$w .= $v->renderMyUserMsgsAsString();
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
}
$w .= "<br />\n";

$w .= '<table>';
$w .= '<thead><tr class="rowh">';
$w .= "<th>{$v->getRes('permissions/colheader_group_id')}</th>";
$w .= "<th>{$v->getRes('permissions/colheader_group_name')}</th>";
$w .= "<th>{$v->getRes('permissions/colheader_group_parent')}</th>";
$w .= '</tr></thead>'."\n";
$w .= "<tbody>\n";
foreach ($v->groups as $theGroup) {
	$r = '<tr class="'.$v->_rowClass.'">';
	
	$r .= '<td>'.$theGroup['group_id'].'</td>';
	
	if ($theGroup['group_id']==1) //super-admin group cannot be modified anyway
		$theLink = $theGroup['group_name'];
	else
		$theLink = '<a href="'.BITS_URL.'/rights/group/'.$theGroup['group_id'].'">'.$theGroup['group_name'].'</a>';
	$r .= '<td>'.$theLink.'</td>';

	$r .= '<td>';
	switch ($theGroup['group_type']) {
		case 1:
			$r .= $v->getRes('permissions/display_group_type_1');
			break;
		default:
			if (!empty($theGroup['parent_group_id'])) {
				$r .= $v->getRes('permissions/display_parent_group/'.$v->groups[$theGroup['parent_group_id']]['group_name']);
			}
	}//switch
	$r .= '</td>';
	
	$r .= "</tr>\n";
	$w .= $r;
}//end foreach
$w .= "  </tbody>\n";
$w .= "</table><br/>\n";

print($w);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
