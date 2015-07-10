<?php
use BitsTheater\scenes\Install as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= '<div align="left">'."\n";
if (!$v->permission_denied) {
	$w .= 'All done!  Please set the file permissions back to normal.'."<br/>\n";
	$w .= '<br/>';
	$w .= $v->continue_button;
} else {
	$w .= '<br/>';
	$w .= 'Write Permission Denied, please give all files/folders Write access during install.<br />';
	$w .= 'It is safe to grant Write access now and then refresh this page.<br />';
}
$w .= '</div>';

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print($form_html);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
