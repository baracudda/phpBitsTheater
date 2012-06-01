<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

if ($recite->_dbError) {
	echo $recite->_dbError;
} else {

$w = '<div align="left" class="contentHeader"><div class="contentHeaderText">Login</div>';
$w .= 'Name: '.Widgets::createTextBox('username',$recite->username,true)."<br/>\n";
$w .= 'Pass: '.Widgets::createPassBox('password')."<br/>\n";
$w .= $recite->continue_button;
$w .= "<br/>\n";
$w .= '<a href="'.BITS_URL.'/account/register">Register</a>'."<br/>\n";
$w .= "</div>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print $form_html;

}
$recite->includeMyFooter();
