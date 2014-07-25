<?php
use BitsTheater\scenes\Rights as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= "<h1>{$v->getRes('permissions/title_group/'.$v->group['group_name'])}</h1>\n";
$res = $v->getPermissionRes('right_values');
$w .= '<table border="0">';
$w .= '<tr><td align="right"><b>+</b> = </td><td>'.$res['allow']['label'].': '.$res['allow']['desc']."</td></tr>\n";
$w .= '<tr><td align="right"><b>-</b> = </td><td>'.$res['disallow']['label'].': '.$res['disallow']['desc']."</td></tr>\n";
$w .= '<tr><td align="right"><b>x</b> = </td><td>'.$res['deny']['label'].': '.$res['deny']['desc']."</td></tr>\n";
$w .= "</table>\n";
$w .= "<br/>\n";
$w .= Widgets::createHiddenPost('group_id',$v->group['group_id']);
foreach ($v->right_groups as $ns => $nsInfo) {
	$v->_rowClass = 1; //reset row counter back to 1 for each table created (resets the row formatting)
	$thePermissionRows = '';
	//build rows first in case there are none so we can skip header too
	foreach ($v->getPermissionRes($ns) as $theRight => $theRightInfo) {
		$thePermissionValue = $v->getRightValue($v->assigned_rights,$ns,$theRight);
		if ($thePermissionValue=='deny-disable')
			continue;
		//if (Auth::TYPE!='basic' && $ns=='auth' && $theRight!='modify') continue;
		$cellLabel = '<td style="width:20em" class="data-label">'.$theRightInfo['label'].'</td>';
		$cellInput = '<td style="width:12em" align="center">'.Widgets::createRadioSet($ns.'__'.$theRight,
				$v->getShortRightValues(), $thePermissionValue,	'right',"&nbsp;&nbsp;").'</td>';
		$cellDesc = '<td style="width:40em;text-align:left" >'.$theRightInfo['desc'].'</td>';
	
		$thePermissionRows .= '<tr class="'.$v->_rowClass.'">'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
	}//end foreach
	if (!empty($thePermissionRows)) {
		$w .= "<h2>{$nsInfo['desc']}</h2>";
		$w .= '<table class="data-entry">'."\n";
		$w .= '<thead><tr class="rowh">'."\n";
		$w .= '<th>'.$v->getRes('permissions/colheader_right_name').'</th>';
		$w .= '<th>'.$v->getRes('permissions/colheader_right_value').'</th>';
		$w .= '<th>'.$v->getRes('permissions/colheader_right_desc').'</th>';
		$w .= "</tr></thead>\n";
		$w .= "<tbody>\n";
		$w .= $thePermissionRows;
		$w .= "</tbody>\n";
		$w .= "</table><br/>\n";
	}
}//end foreach
$w .= '<div style="text-align:left">'."<br/>\n";
$w .= $v->save_button;
$w .= '</div>'."<br/>\n";
$w .= "<br/>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print($form_html);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
