<?php
use BitsTheater\scenes\Install as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= '<div align="left">'."\n";
if (!empty($recite->connected)) {
	$w .= 'Database connection successful!<br/>'."\n";
	$w .= '<br/>The next step will create the database and prepare it for use.<br/>'."\n";
	$w .= $recite->continue_button;
} else if ($recite->permission_denied) {
	$w .= 'Write Permission Denied, please give all files/folders Write access during install.<br />'."\n";
	$w .= 'It is safe to grant Write access now and then refresh this page.<br />'."\n";
} else {
	$w .= 'The database information failed to result in a connection. Please correct the information and try again.';
	$w .= $recite->back_button;
	$w .= "<br />\n";
	$w .= $recite->_dbError;
	$w .= $recite->old_vals;
}
$w .= '</div>';
//even though there is no inputs here for the form, we need one for the Continue button to function
$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print($form_html);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
