<?php
use BitsTheater\scenes\Rights as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use BitsTheater\models\AuthGroups as AuthGroupsDB;

$theDenyVal = AuthGroupsDB::FORM_VALUE_Deny;
$theAllowVal = AuthGroupsDB::FORM_VALUE_Allow;
$theDisallowVal = AuthGroupsDB::FORM_VALUE_Disallow;
$css = <<<EOM
input[type="radio"]+label {
	min-width: 2em;
}
input[type="radio"][value="{$theDenyVal}"]:checked+label {
	background: tomato;
}
input[type="radio"][value="{$theAllowVal}"]:checked+label {
	background: lime;
}

EOM;
$h = $v->createCssTagBlock($css);
$h .= $v->cueActor('Fragments', 'get', 'csrf_header_jquery');
$recite->includeMyHeader($h);
$w = '';

$w .= '<h1>' . Strings::format($v->getRes('permissions/title_group'),htmlentities($v->group['group_name'])) . "</h1>\n";
$w .= $v->renderMyUserMsgsAsString();
print($w);

$res = $v->getPermissionRes('right_values');
//$v->debugLog($v->debugStr($res));
?>
<div class="row">
<div class="col-sm-6">
	<div class="panel panel-info">
		<div class="panel-heading">
		<span style="font-size:larger;"><?php print($v->getRes('generic/label_note_title')); ?></span>
		</div>
		<div class="panel-body"><table><tbody>
<tr><td align="right"><b>+</b>&nbsp;=&nbsp;</td><td><?php print($res[$theAllowVal]->label.': '.$res[$theAllowVal]->desc); ?></td></tr>
<tr><td align="right"><b>-</b>&nbsp;=&nbsp;</td><td><?php print($res[$theDisallowVal]->label.': '.$res[$theDisallowVal]->desc); ?></td></tr>
<tr><td align="right"><b>x</b>&nbsp;=&nbsp;</td><td><?php print($res[$theDenyVal]->label.': '.$res[$theDenyVal]->desc); ?></td></tr>
		</tbody></table></div>
	</div>
</div>
<div class="col-sm-6"></div>
</div>
<br />
<?php
$w = '';
$w .= Widgets::createHiddenPost('group_id',$v->group['group_id']);
$w .= Widgets::createHiddenPost('post_key', $v->post_key);
//copy the save button so we can put it on top and bottom of the page
$theSaveButton = Widgets::buildSubmitButton('submit_save', $v->getRes('generic/save_button_text'))
		->addClass('btn-primary')->render()
		;
$w .= $theSaveButton;
foreach ($v->right_groups as $ns => $nsInfo) {
	$v->_rowClass = 1; //reset row counter back to 1 for each table created (resets the row formatting)
	$thePermissionRows = '';
	//build rows first in case there are none so we can skip header too
	foreach ($v->getPermissionRes($ns) as $theRight => $theRightInfo) {
		$thePermissionValue = $v->getRightValue($v->assigned_rights,$ns,$theRight);
		if ( $thePermissionValue == AuthGroupsDB::FORM_VALUE_DoNotShow )
			continue;
		//if (Auth::TYPE!='basic' && $ns=='auth' && $theRight!='modify') continue;
		$cellLabel = '<td style="width:20em" class="db-field-label">'.htmlentities($theRightInfo->label).'</td>';
		$cellInput = '<td style="width:15em;text-align:center">'.Widgets::createRadioSet($ns.'__'.$theRight,
				$v->getShortRightValues(), $thePermissionValue,	'right',"&nbsp;&nbsp;").'</td>';
		$cellDesc = '<td style="width:40em">'.htmlentities($theRightInfo->desc).'</td>';
	
		$thePermissionRows .= '<tr class="'.$v->_rowClass.'">'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
	}//end foreach
	if (!empty($thePermissionRows)) {
		$w .= '<table class="db-entry">'."\n";
		$w .= '<caption>' . htmlentities($nsInfo->desc) . '</caption>';
		$w .= '<thead><tr class="rowh">'."\n";
		$w .= '<th class="text-right">'.$v->getRes('permissions/colheader_right_name').'</th>';
		$w .= '<th class="text-center">'.$v->getRes('permissions/colheader_right_value').'</th>';
		$w .= '<th class="text-left">'.$v->getRes('permissions/colheader_right_desc').'</th>';
		$w .= "</tr></thead>\n";
		$w .= "<tbody>\n";
		$w .= $thePermissionRows;
		$w .= "</tbody>\n";
		$w .= "</table><br/>\n";
	}
}//end foreach
$w .= '<div style="text-align:left">'."<br/>\n";
$w .= $theSaveButton;
$w .= '</div>'."<br/>\n";
$w .= "<br/>\n";

$theForm = Widgets::buildForm($recite->next_action)->setName($recite->form_name)
		->setRedirect($v->redirect)->append($w)
		;
print( $theForm->render() );
print(str_repeat('<br />',3));
$recite->includeMyFooter();
