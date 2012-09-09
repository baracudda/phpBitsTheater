<?php
use com\blackmoonit\Widgets;
$recite->includeMyHeader();

echo "<h1 align=\"center\">Assign Rights for Group: {$v->group['group_name']}</h1>\n";
$res = $v->rights->getPermissionRes('right_values');
echo '<table border="0">';
echo '<tr><td align="right"><b>+</b> = </td><td>'.$res['allow']['label'].': '.$res['allow']['desc']."</td></tr>\n";
echo '<tr><td align="right"><b>-</b> = </td><td>'.$res['disallow']['label'].': '.$res['disallow']['desc']."</td></tr>\n";
echo '<tr><td align="right"><b>x</b> = </td><td>'.$res['deny']['label'].': '.$res['deny']['desc']."</td></tr>\n";
echo "</table>\n";
echo "<br/>\n";
$w = Widgets::createHiddenPost('group_id',$v->group['group_id']);
foreach ($v->right_groups as $ns => $nsInfo) {
	$v->_row_class = 1; //reset row counter back to 1 for each table created (resets the row formatting)
	$w .= "<h2>{$nsInfo['desc']}</h2>";
	$w .= '<table class="data-entry">'."\n";
	$w .= '  <thead><tr class="rowh">'."\n";
	$w .= '    <th>Right</th><th> Assign </th><th>Description</th>'."\n";
	$w .= "  </tr></thead>\n";
	$w .= "  <tbody>\n";
	foreach ($v->rights->getPermissionRes($ns) as $theRight => $theRightInfo) {
		//if (Auth::TYPE!='basic' && $ns=='auth' && $theRight!='modify') continue;
		$cellLabel = '<td width="20%" class="data-label">'.$theRightInfo['label'].'</td>';
		$cellInput = '<td width="20%" align="center">'.Widgets::createRadioSet($ns.'__'.$theRight,$v->getShortRightValues(),
				$v->getRightValue($v->assigned_rights,$ns,$theRight),'right',"&nbsp;&nbsp;").'</td>';
		$cellDesc = '<td align="left">'.$theRightInfo['desc'].'</td>';

		$w .= '  <tr class='.$v->_row_class.'>'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
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
