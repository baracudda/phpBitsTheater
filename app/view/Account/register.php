<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

if ($recite->_dbError) {
	echo $recite->_dbError;
} else {

$w = '<div align="left" class="contentHeader"><div class="contentHeaderText">Register</div>';
$w .= 'name: '.Widgets::createTextBox('username',$recite->username,true)."<br/>\n";
$w .= 'email: '.Widgets::createTextBox('username',$recite->email,true)."<br/>\n";
$w .= 'pass: '.Widgets::createTextBox('password',$recite->password,true)."<br/>\n";
$w .= 'confirm pass: '.Widgets::createTextBox('password_confirm',$recite->password_confirm,true)."<br/>\n";
$w .= $recite->continue_button;
$w .= "</div>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print $form_html;

}
$recite->includeMyFooter();
