<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

$w = '<div align="left">'."\n";
if (empty($recite->_dbError)) {
	$w .= 'Database setup successful!<br/>'."\n";
	$w .= '<br/>';
	$w .= $recite->continue_button;
} else {
	$w .= 'The database failed to setup correctly. Please review logs and/or seek help.<br/>'."\n";
	$w .= $recite->_dbError;
	$w .= $recite->old_vals;
	//$w .= $recite->back_button;
}
$w .= '</div>';
//even though there is no inputs here for the form, we need one for the Continue button to function
$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print $form_html;

print(str_repeat('<br />',3));
$recite->includeMyFooter();
