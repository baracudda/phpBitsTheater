<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

echo '<div align="center" class="contentHeader">';
echo '  <div class="contentHeaderText">Assign Rights for Group: '.$v->group['group_name'].'</div>';
echo "</div>\n";
echo '<div align="left">'."\n";
$res = $v->rights->getPermissionRes('right_values');
echo '<table border="0">';
echo '<tr><td align="right"><b>+</b> = </td><td>'.$res['allow']['label'].': '.$res['allow']['desc']."</td></tr>\n";
echo '<tr><td align="right"><b>-</b> = </td><td>'.$res['disallow']['label'].': '.$res['disallow']['desc']."</td></tr>\n";
echo '<tr><td align="right"><b>x</b> = </td><td>'.$res['deny']['label'].': '.$res['deny']['desc']."</td></tr>\n";
echo "</table>\n";
echo "</div><br/>\n";
$w = Widgets::createHiddenPost('group_id',$v->group['group_id']);
foreach ($v->right_groups as $ns => $nsInfo) {
	$w .= '<div align="left" class="contentHeader">';
	//$w .= '  <div class="contentHeaderText">'.$nsInfo['label'].' ('.$nsInfo['desc'].')</div>';
	$w .= '  <div class="contentHeaderText">'.$nsInfo['desc'].'</div>';
	$w .= "</div>\n";
	$w .= '<table border="0" align="center" width="100%" cellspacing="1" cellpadding="5" class="dataOutline" style="margin-top: 1px;">'."\n";
	$w .= '  <thead><tr class="listHeader">'."\n";
	$w .= '    <th>Right</th><th> Assign </th><th>Description</th>'."\n";
	$w .= "  </tr></thead>\n";
	$w .= "  <tbody>\n";
	$i = 0;
	foreach ($v->rights->getPermissionRes($ns) as $theRight => $theRightInfo) {
		if (app\model\Auth::TYPE!='basic' && $ns=='auth' && $theRight!='modify')
			continue;
		$cellLabel = '<td align="right" width="20%">'.$theRightInfo['label'].'</td>';
		$cellInput = '<td align="center" width="20%">'.Widgets::createRadioSet($ns.'__'.$theRight,$v->getShortRightValues(),
				$v->getRightValue($v->assigned_rights,$ns,$theRight),'right').'</td>';
		$cellDesc = '<td align="left">'.$theRightInfo['desc'].'</td>';

		//next statement alternates the row color
		$bcolor = 'class="row'.((++$i%2)+1).'"';
		$w .= '  <tr '.$bcolor.'>'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
	}//end foreach
	$w .= "  </tbody>\n";
    $w .= "</table><br/>\n";
}//end foreach
$w .= '<div align="left">'."<br/>\n";
$w .= $v->save_button;
$w .= '</div>'."<br/>\n";
$w .= "<br/>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print $form_html;

$recite->includeMyFooter();
